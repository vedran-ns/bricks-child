<?php
    defined( 'ABSPATH' ) || die( "Can't access directly" );

add_action('rest_api_init', function () {
    register_rest_route('medics/v1', '/get-medics', [
        'methods' => 'GET',
        'callback' => function ($request) {
            $protocol_type = sanitize_text_field($request->get_param('protocol_type'));
            $current_dose = sanitize_text_field($request->get_param('current_dose'));
            $regime_type = sanitize_text_field($request->get_param('regime_type'));
            $new_dose_prefer = sanitize_text_field($request->get_param('new_dose_prefer'));
            return get_google_sheet_row_by_product($protocol_type, $current_dose, $regime_type, $new_dose_prefer);
        },
        'permission_callback' => '__return_true',
    ]);
});


function get_google_sheet_row_by_product($protocol_type, $current_dose, $regime_type, $new_dose_prefer = null) {
    $theme_api_path = get_stylesheet_directory() . '/api';

    require_once $theme_api_path . '/vendor/autoload.php';

    $client = new \Google_Client();
    $client->setAuthConfig($theme_api_path . '/google-service-account.json');
    $client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);

    $service = new Google_Service_Sheets($client);

    $spreadsheetId = '11iv0EnMy7tUaWCXwFOZJjw8rMCXiY5A-bjjRmmxGs0A';
    $range = 'Sheet1!A2:O';

    try {
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $rows = $response->getValues();

        $parsed_rows = array_map(function($row) {
            $parsed = parse_favorite_name($row[0] ?? '');
            return [
                'original_row' => [
                    'Favorite Name'   => $row[0] ?? '',
                    'Medicine Id'     => $row[1] ?? '',
                    'Medication'      => $row[2] ?? '',
                    'Type'            => $row[3] ?? '',
                    'Strength'        => $row[4] ?? '',
                    'Sig'             => $row[5] ?? '',
                    'Quantity'        => $row[6] ?? '',
                    'Dispense'        => $row[7] ?? '',
                    'Refills'         => $row[8] ?? '',
                    'Days'            => $row[9] ?? '',
                    'Category'        => $row[10] ?? '',
                    'Pharmacy Notes'  => $row[11] ?? '',
                    'Visit Type'      => $row[12] ?? '',
                    'Pharmacy ID'     => $row[13] ?? '',
                    'Price'           => $row[14] ?? '',
                ],
                'parsed' => $parsed
            ];
        }, $rows);
        
        
         if ($protocol_type === 'standard') {
             $parsed_rows = array_filter($parsed_rows, fn($row) => $row['original_row']['Type'] === 'med');
        }
        
        
        // Rule 1: lowest dose standard
        if ($protocol_type === 'standard' && $regime_type === 'standard' && $current_dose === 'lowest') {
            $standard = array_filter($parsed_rows, fn($row) => $row['parsed']['regime_type'] === 'standard');          
			return get_lowest_dose_rows($standard);
        }

        // Rule 2: lowest dose alternative
        if ($protocol_type === 'individual' && $regime_type === 'alternative' && $current_dose === 'lowest') {
            $alternatives = array_filter($parsed_rows, fn($row) => $row['parsed']['regime_type'] === 'alternative');
            $lowest_alt = get_lowest_dose_rows($alternatives);

            if (!empty($lowest_alt)) return $lowest_alt;

            $standard_regime = array_filter($parsed_rows, fn($row) => $row['parsed']['regime_type'] === 'standard');
            return get_lowest_dose_rows($standard_regime);
        }
        
        // Rule 3: starter doses
        if ( $regime_type === 'standard' && in_array($current_dose, ['starter','starter-semaglutide','starter-tirzepatide']) ) {

            if(in_array($current_dose, ['starter-semaglutide','starter-tirzepatide'])) {

                $medicine_name = ucfirst(explode('-', $current_dose)[1]);
                $medics = $medicine_name == 'Semaglutide' ? ['Ozempic', 'Wegovy'] : ($medicine_name == 'Tirzepatide' ? ['Zepbound', 'Mounjaro'] : []);
                $filtered_rows = [];
                $starter_doses = [];
                foreach ($medics as $medic) {
                    $filtered_rows = array_filter($parsed_rows, fn($row) => $row['parsed']['medicine_name'] == $medic && stripos($row['original_row']['Favorite Name'], 'starter') !== false);
                    $starter_doses  = array_merge($filtered_rows, $starter_doses);
                }
                return array_values($starter_doses);
            }

            return array_values(array_filter($parsed_rows, function($row) {
                return stripos($row['original_row']['Favorite Name'], 'starter') !== false;
            }));

        }

        // Rule 4: new dose patient prefer (increase, decrease, same) standard
        if ($protocol_type === 'standard' && $regime_type === 'standard' && $current_dose !== 'starter' && isset($new_dose_prefer)) {
            $medicine_name = '';
            $dose = '';
            if (preg_match('/^([A-Za-z\s]+?)\s((\d+(\.\d+)?mg(\/\d+(\.\d+)?mg)?)|starter)/i', $current_dose, $matches)) {
                $medicine_name = trim($matches[1]);
                $dose = strtolower(trim($matches[2]));
            }

            $medics = $medicine_name == 'Semaglutide' ? ['Ozempic', 'Wegovy'] : ($medicine_name == 'Tirzepatide' ? ['Zepbound', 'Mounjaro'] : []);            

            $next_doses = [];
            foreach ($medics as $medic) {
                $filtered_rows = array_filter($parsed_rows, fn($row) => $row['parsed']['medicine_name'] == $medic);  
                $next_doses  = array_merge(get_next_dose( $filtered_rows,$dose,$new_dose_prefer), $next_doses);
            }

            if ('same' == $new_dose_prefer && empty($next_doses)) {
                $new_dose_prefer = 'increase';
                foreach ($medics as $medic) {
                    $filtered_rows = array_filter($parsed_rows, fn($row) => $row['parsed']['medicine_name'] == $medic);  
                    $next_doses  = array_merge(get_next_dose( $filtered_rows,$dose,$new_dose_prefer), $next_doses);
                }
            }

            return $next_doses;

            /*$filtered_rows = array_filter($parsed_rows, fn($row) => in_array($row['parsed']['medicine_name'],$medics));          
            return get_next_higher_dose($filtered_rows,$dose);*/
        }

        // Default response
        return $parsed_rows;

    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}




function get_lowest_dose_rows($rows) {
    if (empty($rows)) return [];

    // Exclude titration rows
   /* $non_titration_rows = array_filter($rows, function($row) {
        return !($row['parsed']['titration'] ?? false);
    });

    if (empty($non_titration_rows)) return [];*/

    // Map numeric dose values
    $dose_map = array_map(function($row) {
        $dose = $row['parsed']['dose'];		
        $numbers = array_map('floatval', explode('/', str_ireplace('mg', '', $dose)));
        return ['min' => min($numbers), 'data' => $row];
    }, $rows);

    // Sort by dose
    usort($dose_map, fn($a, $b) => $a['min'] <=> $b['min']);
    $lowest = $dose_map[0]['min'];

    // Return rows matching the lowest dose
    return array_values(array_map(
        fn($item) => $item['data'],
        array_filter($dose_map, fn($item) => $item['min'] == $lowest)
    ));
}


function get_next_dose($filtered_rows, $current_dose_str,$new_dose_prefer) {
    if (empty($filtered_rows)) return [];

    // Normalize current dose to float
    $current_dose_value = floatval(str_ireplace('mg', '', $current_dose_str));

    // Build array of numeric doses
    $dose_map = [];

    foreach ($filtered_rows as $row) {
        $parsed = $row['parsed'];        

        $dose_value = floatval(str_ireplace('mg', '', $parsed['dose']));
        if ($dose_value <= 0) continue;

        $dose_map[] = [
            'dose_value' => $dose_value,
            'row' => $row
        ];
    }

    if('increase' == $new_dose_prefer) {
        // Sort doses ascending
        usort($dose_map, fn($a, $b) => $a['dose_value'] <=> $b['dose_value']);
    }
    elseif ('decrease' == $new_dose_prefer) {
        // Sort doses descending
        usort($dose_map, fn($a, $b) => $b['dose_value'] <=> $a['dose_value']);
    }

    // Group rows by dose value
    $grouped = [];
    foreach ($dose_map as $item) {
        $key = number_format($item['dose_value'], 2, '.', '');
        $grouped[$key][] = $item['row'];
    }

    
    // Find the next higher or lower dose 
    foreach (array_keys($grouped) as $dose_val) {
        if ( ((float)$dose_val > $current_dose_value && 'increase' == $new_dose_prefer) 
            || 
            ((float)$dose_val < $current_dose_value && 'decrease' == $new_dose_prefer) ) {
            return $grouped[$dose_val]; // return all rows with next dose            
        }
    }
    

    // If no higher dose found, return same dose rows
   /* $current_key = number_format($current_dose_value, 2, '.', '');
    return $grouped[$current_key] ?? [];*/

   
    if('increase' == $new_dose_prefer) {
         // No higher dose found → return the highest available dose
        $highest_key = array_key_last($grouped);      
        return (float)$highest_key >= $current_dose_value ? $grouped[$highest_key] : [];
    }
    elseif ('decrease' == $new_dose_prefer) {
        // No lower dose → return the lowest available dose
        $lowest_key = array_key_last($grouped);
        return (float)$lowest_key <= $current_dose_value ? $grouped[$lowest_key] : [];
    }
    elseif ('same' == $new_dose_prefer) {
        $current_key = number_format($current_dose_value, 2, '.', '');
        return $grouped[$current_key] ?? [];
    }

}



function parse_favorite_name($favorite_name) {
    $result = [
        'medicine_name'    => '',
        'dose'             => '',
        'regime_type'             => 'standard',
        'duration_months'  => 1,
        'titration'        => false,
    ];

    // Extract duration like (3 month)
    if (preg_match('/\((\d+)\s*month\)/i', $favorite_name, $matches)) {
        $result['duration_months'] = (int)$matches[1];
        $favorite_name = trim(str_replace($matches[0], '', $favorite_name));
    }

    // Define known regime types
    $allowed_types = ['alternative', 'biweekly', 'rapid'];

    // Extract and remove regime type from string
    $maybe_type = '';
    foreach ($allowed_types as $type) {
        if (preg_match('/\b' . preg_quote($type, '/') . '\b/i', $favorite_name)) {
            $maybe_type = strtolower($type);
            $favorite_name = trim(preg_replace('/\b' . preg_quote($type, '/') . '\b/i', '', $favorite_name));
            break;
        }
    }

    // Extract the first dose-like pattern (e.g., 1.5mg or 1.5mg/3mg)
    if (preg_match('/^([A-Za-z\s]+?)\s((\d+(\.\d+)?mg(\/\d+(\.\d+)?mg)?)|starter)/i', $favorite_name, $matches)) {
        $result['medicine_name'] = trim($matches[1]);
        $result['dose'] = strtolower(trim($matches[2]));

        // Detect titration
        if (preg_match('/^\d+(\.\d+)?mg\/\d+(\.\d+)?mg$/i', $result['dose'])) {
            $result['titration'] = true;
        }

        /*if ($result['dose'] === 'starter') {
            $result['titration'] = true;
        }*/
    }

    // Set regime type if found
    if (!empty($maybe_type)) {
        $result['regime_type'] = $maybe_type;
    }

    return $result;
}
