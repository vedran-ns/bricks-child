<?php
    defined( 'ABSPATH' ) || die( "Can't access directly" );


add_action( 'woocommerce_order_status_processing', 'send_data_belunga',10, 1 );
 
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
