<?php
    defined( 'ABSPATH' ) || die( "Can't access directly" );

add_action('rest_api_init', function () {
    register_rest_route('medics/v1', '/get-medics', [
        'methods' => 'GET',
        'callback' => function ($request) {
            $current_dose = sanitize_text_field($request->get_param('current_dose'));
			$regime_type = sanitize_text_field($request->get_param('regime_type'));
			$new_dose_prefer = sanitize_text_field($request->get_param('new_dose_prefer'));
            return get_google_sheet_row_by_product($current_dose, $regime_type);
        },
        'permission_callback' => '__return_true',
    ]);
});


function get_google_sheet_row_by_product($current_dose, $regime_type, $new_dose_prefer = null) {
    $theme_api_path = get_stylesheet_directory() . '/api';

    require_once $theme_api_path . '/vendor/autoload.php';

    $client = new \Google_Client();
    $client->setAuthConfig($theme_api_path . '/google-service-account.json');
    $client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);

    $service = new Google_Service_Sheets($client);

    $spreadsheetId = '11iv0EnMy7tUaWCXwFOZJjw8rMCXiY5A-bjjRmmxGs0A';
    $range = 'Sheet1!A2:N';

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
                ],
                'parsed' => $parsed
            ];
        }, $rows);

        // Rule 1: lowest dose alternative
        if ($regime_type === 'alternative' && $current_dose === 'lowest') {
            $alternatives = array_filter($parsed_rows, fn($row) => $row['parsed']['type'] === 'alternative');
            $lowest_alt = get_lowest_dose_rows($alternatives);

            if (!empty($lowest_alt)) return $lowest_alt;

            $standard = array_filter($parsed_rows, fn($row) => $row['parsed']['type'] === 'standard');
            return get_lowest_dose_rows($standard);
        }

        // Rule 2: starter doses
        if ($regime_type === 'standard' && $current_dose === 'starter') {
            return array_values(array_filter($parsed_rows, function($row) {
                return stripos($row['original_row']['Favorite Name'], 'starter') !== false;
            }));
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
    $non_titration_rows = array_filter($rows, function($row) {
        return !($row['parsed']['titration'] ?? false);
    });

    if (empty($non_titration_rows)) return [];

    // Map numeric dose values
    $dose_map = array_map(function($row) {
        $dose = $row['parsed']['dose'];
        $numbers = array_map('floatval', explode('/', str_ireplace('mg', '', $dose)));
        return ['min' => min($numbers), 'data' => $row];
    }, $non_titration_rows);

    // Sort by dose
    usort($dose_map, fn($a, $b) => $a['min'] <=> $b['min']);
    $lowest = $dose_map[0]['min'];

    // Return rows matching the lowest dose
    return array_values(array_map(
        fn($item) => $item['data'],
        array_filter($dose_map, fn($item) => $item['min'] == $lowest)
    ));
}


function parse_favorite_name($favorite_name) {
    $result = [
        'medicine_name'    => '',
        'dose'             => '',
        'type'             => 'standard',
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

        if ($result['dose'] === 'starter') {
            $result['titration'] = true;
        }
    }

    // Set regime type if found
    if (!empty($maybe_type)) {
        $result['type'] = $maybe_type;
    }

    return $result;
}
