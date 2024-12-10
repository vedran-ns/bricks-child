<?php
    defined( 'ABSPATH' ) || die( "Can't access directly" );


add_action( 'woocommerce_order_status_processing', 'send_data_belunga',10, 1 );
 
function send_data_belunga( $order_id){

    $product =  wc_get_order($order_id);      
        
//USER DETAILS
        
        $first_name = $product->get_shipping_first_name() ? $product->get_shipping_first_name() :  $product->get_billing_first_name();
        $last_name = $product->get_shipping_last_name() ? $product->get_shipping_last_name() : $product->get_billing_last_name();
        $company = $product->get_shipping_company() ? $product->get_shipping_company() : $product->get_billing_company();
        $address_1 = $product->get_shipping_address_1() ? $product->get_shipping_address_1() : $product->get_billing_address_1();        
        $city = $product->get_shipping_city() ? $product->get_shipping_city() : $product->get_billing_city();
        $state = $product->get_shipping_state() ? $product->get_shipping_state() : $product->get_billing_state();
        $postcode = $product->get_shipping_postcode() ? $product->get_shipping_postcode() : $product->get_billing_postcode();
        $email = $product->get_billing_email();
        $phone =  $product->get_billing_phone();
    
      
     $args1 = array(
                'consentsSigned' => true,
                'firstName' => $first_name,
                'lastName' => $last_name,               
                'address' => $address_1,                
                'city' => $city,
                'state' => $state,
                'zip' => $postcode,
                'email' => $email,
                'phone' => $phone,
                'masterId' => $order_id
            );


    $args = json_encode($args1);

    $response = wp_remote_post( 'https://api-staging.belugahealth.com}/visit/createNoPayPhotos', array(
          'method' => 'POST',
          'httpversion' => '1.0',
          'headers' => array(
           'Authorization' => 'Bearer z17DZCRW9jjUwuG3uRNr',
            'Content-Type' => 'application/json'             ),
          'body' => $args
           )
         );

    // Check answer code
    $response_code    = wp_remote_retrieve_response_code( $response );
    $response_message = wp_remote_retrieve_response_message( $response );
    $response_body    = json_decode(wp_remote_retrieve_body( $response ));

    if( 200 == $response_code ) {
        echo 'The form has been submited successfully';
      }

    if ( 200 != $response_code && ! empty( $response_message ) ) {
        return echo  $response_code.' -- '.$response_message ;
    }
     if (is_wp_error($response) || 200 != $response_code) {
        return echo $response->get_error_message();
    }
    if( ! $response_body ) {
        return new WP_Error( 'nodata', 'No data on the movie or no such movie in the database' );
    }    


};