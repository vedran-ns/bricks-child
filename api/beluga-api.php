<?php
    defined( 'ABSPATH' ) || die( "Can't access directly" );

//add_action('woocommerce_checkout_process', 'validate_with_external_api');

//function validate_with_external_api() {

add_action('woocommerce_checkout_order_processed', 'validate_with_external_api',10,3);

function validate_with_external_api($order_id, $posted_data, $order) {

    //write_log($_POST);
    //write_log($posted_data);

    $product_name = '';
    $product_sku = '';
    $product_med_cat = '';
    $product_med_str = '';
    $product_med_qty = '';
    $product_med_ref = '';
    $current_med_use = '';
    $weekly_dose_preference = '';
    $visit_type = '';
    $pharmacy_id = '';

    foreach( $order->get_items( 'line_item' ) as $item_id => $item ) {

        // Get parent product object
        $product_id = $item['product_id'];
        $parent_product = wc_get_product( $product_id );
        $product_name = $parent_product->get_title();

        if( $item['variation_id'] > 0 ){
            $variation_id = !empty($posted_data['prescription_picture']) ? $item['variation_id'] : ($product_name == 'Semaglutide' ? 1630 : 1649); // Variation product            
        } else {            
            $variation_id = 0;
        }
        
        if(!in_array($product_name, ['Semaglutide','Tirzepatide']))  continue;

        // Get attributes from parent
        $pharmacy_id = $parent_product->get_attribute('pa_pharmacy-id');
        $visit_type = $parent_product->get_attribute('pa_visit-type');     
        

        if ($variation_id > 0) {
            $variation = wc_get_product( $variation_id );
            if ($variation instanceof WC_Product_Variation) {
                // SKU
                $product_sku = $variation->get_sku() ?: '';

                // Custom meta fields (medicine-related)
                $product_med_cat  = get_post_meta($variation_id, 'medicine_category', true) ?: '';
                $product_med_str  = get_post_meta($variation_id, 'medicine_strength', true) ?: 'N/A';
                $product_med_qty  = get_post_meta($variation_id, 'medicine_quantity', true) ?: '';
                $product_med_ref  = get_post_meta($variation_id, 'medicine_refills', true) ?: '0';            

                // Variation attributes
                $current_med_use = $variation->get_attribute('pa_current-medication-use');
                //$starter_dose = $product_name == 'Semaglutide' ? '0.25 mg' : ($product_name == 'Tirzepatide' ? '2.5 mg' : 'No Dose');
                $weekly_dose_preference =  $variation->get_attribute('pa_weekly-dose') ;
                $currentDose = 'current_dose_'.strtolower($product_name);
            }
        }

    }

    if(!in_array($product_name, ['Semaglutide','Tirzepatide']))  return;
 
    $args1 = [
            "formObj" => [
                "consentsSigned"=> true,
                "firstName"=> isset($posted_data['billing_first_name']) ? sanitize_text_field($posted_data['billing_first_name']) : '',
                "lastName"=> isset($posted_data['billing_last_name']) ? sanitize_text_field($posted_data['billing_last_name']) : '',
                "dob" => isset($posted_data['billing_birthdate']) ? date('m/d/Y', strtotime($posted_data['billing_birthdate'])) : '',
                "phone" =>  isset($posted_data['billing_phone']) ? sanitize_text_field($posted_data['billing_phone']) : '',
                "email" =>  isset($posted_data['billing_email']) ? sanitize_email($posted_data['billing_email']) : '',
                "address" => isset($posted_data['billing_address_1']) ? sanitize_text_field($posted_data['billing_address_1']) : '',
                "city" => isset($posted_data['billing_city']) ? sanitize_text_field($posted_data['billing_city']) : '',
                "state" =>  isset($posted_data['billing_state']) ? sanitize_text_field($posted_data['billing_state']) : '',
                "zip" => isset($posted_data['billing_postcode']) ? sanitize_text_field($posted_data['billing_postcode']) : '',
                "sex" => isset($posted_data['sex']) ? $posted_data['sex'] : '',
                "selfReportedMeds" => isset($posted_data['current_medications_list_dosages']) ? sanitize_text_field($posted_data['current_medications_list_dosages']) : '',
                "allergies" => isset($posted_data['current_allergies_list']) ? sanitize_text_field($posted_data['current_allergies_list']) : '',
                "medicalConditions" => isset($posted_data['current_medical_conditions']) ? sanitize_text_field($posted_data['current_medical_conditions']) : '',
                "currentWeightloss" => isset($posted_data['current_meds_sem_tirz']) ? sanitize_text_field($posted_data['current_meds_sem_tirz']) : '',
                "weightlossPreference" => isset($posted_data['new_dose']) ? sanitize_text_field($posted_data['new_dose']) : '',
                "currentDose" => isset($posted_data[$currentDose]) ? sanitize_text_field($posted_data[$currentDose]) : '',
                "patientPreference" => [
                    [
                        "name" => $product_name.' '.$weekly_dose_preference,
                        "strength" => $product_med_str,
                        "quantity" => $product_med_qty,
                        "refills" => $product_med_ref,
                        "medId" => $product_sku
                    ]
                ],
                "Q1" => "Are you currently pregnant, breastfeeding or planning to become pregnant? POSSIBLE ANSWERS: Yes; No",
                "A1" => isset($posted_data['pregnant']) ? $posted_data['pregnant'] : '',             
            ],
            "masterId" => "".$order_id."",
            "company" => 'soSoThin',
            "pharmacyId" => $pharmacy_id,
            "visitType" => $visit_type
     ];

       write_log($args1);  

    $response = wp_remote_post( 'https://api-staging.belugahealth.com/visit/createNoPayPhotos', 
        array(
          'method' => 'POST',
          'httpversion' => '1.0',
          'headers' => array(
           'Authorization' => '',
            'Content-Type' => 'application/json'),
          'body' => json_encode($args1, JSON_UNESCAPED_SLASHES)
           )
         );

    //write_log($response);

     // Handle API response
    if (is_wp_error($response)) {
        $error_message = 'There was an error connecting to the validation service. Please try again.';
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_code !== 200 || !isset($response_data['data']['masterId'], $response_data['data']['visitId'])) {
            $error_message = 'Your order could not be processed. Please check your details and try again.';
        }
    }

    // If API request failed, cancel order and throw error
    if (!empty($error_message)) {

         

        write_log($error_message);
        wp_delete_post($order_id, true);
    }
    else {

        // If API is successful, store masterId and visitId in order meta
        $order->update_meta_data('api_masterId', sanitize_text_field($response_data['data']['masterId']));
        $order->update_meta_data('api_visitId', sanitize_text_field($response_data['data']['visitId']));
        $order->update_meta_data('api_payload_data', serialize($args1));
        //$order->add_order_note( $response_data['info'].'. VisitId: '.$response_data['data']['visitId'],true );
        //$order->save(); // Ensure data is saved

        // Temporarily disable customer note email
        remove_action( 'woocommerce_new_customer_note', array( WC()->mailer()->emails['WC_Email_Customer_Note'], 'trigger' ) );

        // Add the order note as a customer note (visible in My Account)
        $order->add_order_note( $response_data['info'].'. VisitId: '.$response_data['data']['visitId'], true );
        $order->save();

        // Re-enable the customer note email action
        add_action( 'woocommerce_new_customer_note', array( WC()->mailer()->emails['WC_Email_Customer_Note'], 'trigger' ) );


        $image_fields = [];
        if(!empty($posted_data['prescription_picture'])) {
            $image_fields[] = 'prescription_picture';
        }
        if(!empty($posted_data['id_photo_front'])){
             $image_fields[] = 'id_photo_front';
        }
        if(!empty($posted_data['id_photo_back'])){
             $image_fields[] = 'id_photo_back';
        }
        if(!empty($posted_data['full_body_image'])){
             $image_fields[] = 'full_body_image';
        }

         
        $images_base64_codes = $image_fields ? compress_resize_encoded_images($image_fields,$posted_data) : array();
        //write_log($images_base64_codes);

        $args_imgs = [];
        foreach ($images_base64_codes as $base64_code) {
            $args_imgs[] = ["mime" => "image/jpeg","data" => "".$base64_code.""];
        }

        $args_photos = [
            "visitId" => "".$response_data['data']['masterId']."",
            "images" =>  $args_imgs
        ] ;

        $response2 = wp_remote_post( 'https://api-staging.belugahealth.com/external/receivePhotos', array(
          'method' => 'POST',
          'httpversion' => '1.0',
          'headers' => array(
           'Authorization' => '',
            'Content-Type' => 'application/json'),
          'body' => json_encode($args_photos, JSON_UNESCAPED_SLASHES)
           )
         );

    //write_log($response2);

    // Temporarily disable customer note email
        remove_action( 'woocommerce_new_customer_note', array( WC()->mailer()->emails['WC_Email_Customer_Note'], 'trigger' ) );

    if (is_wp_error($response2)) {        
        $order->add_order_note( "Error sending images",true );
        $order->save(); 
    } else {        
        $response_body = wp_remote_retrieve_body($response2);
        $response_data = json_decode($response_body, true); 
        $order->add_order_note( $response_data['info'],true );
        $order->save(); // Ensure data is saved       
    }

    // Re-enable the customer note email action
        add_action( 'woocommerce_new_customer_note', array( WC()->mailer()->emails['WC_Email_Customer_Note'], 'trigger' ) );

        
        
    

     // Handle API response

      }


   
}


//add_action( 'woocommerce_order_status_processing', 'send_data_belunga',10, 1 );
 
function send_data_belunga( $order_id){

    $order =  wc_get_order($order_id);      
        
//USER DETAILS
        
        $first_name = $order->get_shipping_first_name() ? $order->get_shipping_first_name() :  $order->get_billing_first_name();
        $last_name = $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name();
        $company = $order->get_shipping_company() ? $order->get_shipping_company() : $order->get_billing_company();
        $address_1 = $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1();        
        $city = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();
        $state = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
        $postcode = $order->get_shipping_postcode() ? $order->get_shipping_postcode() : $order->get_billing_postcode();
        $email = $order->get_billing_email();
        $phone =  $order->get_billing_phone();
        $product_sku = null;
        foreach( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if( $item['variation_id'] > 0 ){
                $product_id = $item['variation_id']; // variable product
            } else {
                $product_id = $item['product_id']; // simple product
            }
            // Get the product object
            $product = wc_get_product( $product_id );
            $product_sku = $product->get_sku();
        }
      
     $args1 = [
            "formObj" => [
                'consentsSigned' => true,
                'firstName' => $first_name,
                'lastName' => $last_name,               
                'address' => $address_1,                
                'city' => $city,
                'state' => $state,
                'zip' => $postcode,
                'email' => $email,
                'phone' => $phone,                
            ],
            'masterId' => $order_id,
            'company' => 'soSoThin'
     ];


    $args = json_encode($args1);

    $response = wp_remote_post( 'https://api-staging.belugahealth.com/visit/createNoPayPhotos', array(
          'method' => 'POST',
          'httpversion' => '1.0',
          'headers' => array(
           'Authorization' => 'Bearer z17DZCRW9jjUwuG3uRNr',
            'Content-Type' => 'application/json'             ),
          'body' => $args
           )
         );

    write_log($response);

    // Check answer code
    $response_code    = wp_remote_retrieve_response_code( $response );
    $response_message = wp_remote_retrieve_response_message( $response );
    $response_body    = json_decode(wp_remote_retrieve_body( $response ));

    if( 200 == $response_code ) {
        //echo 'The form has been submited successfully';
        write_log('The form has been submited successfully');
      }

    if ( 200 != $response_code && ! empty( $response_message ) ) {
         //echo  $response_code.' -- '.$response_message ;
         write_log($response_code.' -- '.$response_message);
    }
     if (is_wp_error($response) || 200 != $response_code) {
         //echo $response->get_error_message();
         write_log($response->get_error_message());
    }
    if( ! $response_body ) {
        
    }    


};



/* Custom Api Endpoint */

add_action('rest_api_init', 'custom_api');

function custom_api(){
    
    register_rest_route( 'order', 'ordering_status', array( 
      'methods' => 'POST',
      'callback' => 'order_post'
     ));
}

function order_post($data){
//    var_dump($data->get_headers());exit;
   $token_ = $data->get_header("authorisation"); // should be sent as Authorisation in header (rewrite is done in .htaccess)

//    var_dump(substr($token_, 0, 13));exit;

//    if(is_null($token_) || base64_decode(substr($token_, 0, -5)) != "ApiAccess2019"){
   if(is_null($token_) || $token_ !== "Token ababababababab"){

        return 'Access denied';
        exit;
    }
    elseif(isset($data['order_id']) && isset($data['shipper']) && isset($data['tracking_number']) && isset($data['ship_date'])){
        /*wp_insert_post(
            array(
                'post_type' => 'ordering',
                'post_status' => 'publish',
                'post_title' => sanitize_text_field($data['order_id']),
                'meta_input' => array(
                  'shipper' =>sanitize_text_field($data['shipper']),
                  'tracking_number' =>sanitize_text_field($data['tracking_number']),
                  'ship_date' =>sanitize_text_field($data['ship_date'])
                )
            )
        );*/

        
    $product =  wc_get_order($data['order_id']);
    $email = $product->get_billing_email();
    
      
    send_email($email,$data['shipper'],  $data['tracking_number'], $data['ship_date']);


    return '200';
    }else{
        return 'All field must be filed';
    }
}


function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

function compress_resize_encoded_images($image_fields,$posted_data) {
    
    $processed_images = [];

    // Get the upload directory
    $upload_dir = wp_upload_dir();
    $base_dir = trailingslashit($upload_dir['basedir']);

    foreach ($image_fields as $field) {
        if (empty($posted_data[$field])) {
           // wc_add_notice(sprintf(__('Missing file for %s.', 'woocommerce'), $field), 'error');
            continue;
        }

        $file_path = $base_dir . sanitize_file_name($posted_data[$field]);

        if (!file_exists($file_path)) {
            wc_add_notice(sprintf(__('File not found for %s.', 'woocommerce'), $field), 'error');
            continue;
        }

        // Process the image: Resize, compress, and convert to JPEG
        $image = imagecreatefromstring(file_get_contents($file_path));
        if (!$image) {
            wc_add_notice(sprintf(__('Invalid image for %s.', 'woocommerce'), $field), 'error');
            continue;
        }

        // Get original dimensions
        $orig_width = imagesx($image);
        $orig_height = imagesy($image);

        // Calculate new dimensions
        $new_width = min($orig_width, 1000);
        $new_height = ($orig_height / $orig_width) * $new_width;

        // Create a new image with the new dimensions
        $resized_image = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

        // Save the image as a JPEG
        $temp_file = sys_get_temp_dir() . '/' . uniqid() . '.jpg';
        imagejpeg($resized_image, $temp_file, 85); // Adjust quality (85) as needed
        imagedestroy($resized_image);
        imagedestroy($image);

        // Check the file size (<3MB)
        if (filesize($temp_file) > 3 * 1024 * 1024) {
            wc_add_notice(sprintf(__('The uploaded image for %s exceeds 3MB after compression.', 'woocommerce'), $field), 'error');
            unlink($temp_file); // Clean up
            continue;
        }

        // Convert to Base64 without MIME type
        $base64_image = base64_encode(file_get_contents($temp_file));
        $processed_images[$field] = $base64_image;

        unlink($temp_file); // Clean up temp file
    }

    // Save or process $processed_images as needed (e.g., store in database or session)
   /* if (!empty($processed_images)) {
        WC()->session->set('uploaded_images_base64', $processed_images);
    }*/
    return $processed_images;
}


