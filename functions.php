<?php 
/**
 * Register/enqueue custom scripts and styles
 */
add_action( 'wp_enqueue_scripts', function() {
    // Enqueue your files on the canvas & frontend, not the builder panel. Otherwise custom CSS might affect builder)
    if ( ! bricks_is_builder_main() ) {
        wp_enqueue_style( 'bricks-child', get_stylesheet_uri(), ['bricks-frontend'], filemtime( get_stylesheet_directory() . '/style.css' ) );
    }
} );

/**
 * Register custom elements
 */
add_action( 'init', function() {
  $element_files = [
    __DIR__ . '/elements/title.php',
  ];

  foreach ( $element_files as $file ) {
    \Bricks\Elements::register_element( $file );
  }
}, 11 );

/**
 * Add text strings to builder
 */
add_filter( 'bricks/builder/i18n', function( $i18n ) {
  // For element category 'custom'
  $i18n['custom'] = esc_html__( 'Custom', 'bricks' );

  return $i18n;
} );

include(get_stylesheet_directory()."/belunga/belunga-api.php") ;



/*################## Display Billing birthdate field to checkout, order, and My Account addresses ######################*/

add_filter( 'woocommerce_checkout_fields', 'display_birthdate_billing_field', 20, 1 );
function display_birthdate_billing_field($fields) {
 
  // $current_user = wp_get_current_user(); // Get the current user
   // $birthdate = get_user_meta( $current_user->ID, 'billing_birthdate', true ); // Retrieve the birthdate

    $fields['billing']['billing_birthdate'] = array(
        'type'        => 'date',
        'label'       => __('Birthdate'),
        'class'       => array('form-row-wide'),
        'priority'    => 25,
        'required'    => true,
        'clear'       => true,
       // 'default'     => esc_attr( $birthdate ), // Use 'default' for WooCommerce field default values
        'custom_attributes' => array(
            'min' => date('Y-m-d', strtotime('-120 years')), 
            'max' => date('Y-m-d', strtotime('-18 years')),
        ),
    );

    return $fields;
}

//prefill the date field on checkout via JavaScript
add_action( 'wp_footer', 'prefill_birthdate_field_js' );
function prefill_birthdate_field_js() {
    if ( is_checkout() ) {
        $current_user = wp_get_current_user();
        $birthdate = get_user_meta( $current_user->ID, 'billing_birthdate', true );
        if ( ! empty( $birthdate ) ) {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const birthdateField = document.querySelector('[name="billing_birthdate"]');
                    if (birthdateField && !birthdateField.value) {
                        birthdateField.value = '<?php echo esc_js( $birthdate ); ?>';
                    }
                });
            </script>
            <?php
        }
    }
}


// Save Billing birthdate field value as user meta data
add_action( 'woocommerce_checkout_update_customer', 'save_account_billing_birthdate_field', 10, 2 );
function save_account_billing_birthdate_field( $customer, $data ){
    if ( isset($_POST['billing_birthdate']) && ! empty($_POST['billing_birthdate']) ) {
      $birthdate = sanitize_text_field($_POST['billing_birthdate']);
      if ( DateTime::createFromFormat('Y-m-d', $birthdate) ) { 
          $customer->update_meta_data( 'billing_birthdate', $birthdate );
      }
    }

}

// Admin orders Billing birthdate editable field and display
add_filter('woocommerce_admin_billing_fields', 'admin_order_billing_birthdate_editable_field');
function admin_order_billing_birthdate_editable_field( $fields ) {
    $fields['birthdate'] = array( 
      'label' => __('Birthdate', 'woocommerce'),
      'show'  => true,
      'wrapper_class' => 'form-field-wide',
      'style' => '',
      'value' => get_post_meta( get_the_ID(), 'billing_birthdate', true ) );

    return $fields;
}

// WordPress User: Add Billing birthdate editable field
add_filter('woocommerce_customer_meta_fields', 'wordpress_user_account_billing_birthdate_field');
function wordpress_user_account_billing_birthdate_field( $fields ) {
    $fields['billing']['fields']['billing_birthdate'] = array(
        'label'       => __('Birthdate', 'woocommerce'),
        'description' => __('', 'woocommerce')
    );
    return $fields;
}


// Add field to - my account
add_action( 'woocommerce_after_edit_address_form_billing', 'action_woocommerce_edit_account_form' );
function action_woocommerce_edit_account_form() {   
    woocommerce_form_field( 'billing_birthdate', array(
        'type'        => 'date',
        'label'       => __( 'Birthdate', 'woocommerce' ),
        'placeholder' => __( 'Date of Birth', 'woocommerce' ),
        'required'    => true,
        'custom_attributes' => array(
              'min' => date('Y-m-d', strtotime('-120 year')), // Set minimum date to today
              'max' => date('Y-m-d', strtotime('-18 year')) // Set maximum date to one year from today
          )
    ), esc_attr(get_user_meta( get_current_user_id(), 'billing_birthdate', true )));
}



// Validate on - my account
add_action( 'woocommerce_save_account_details_errors','action_woocommerce_save_account_details_errors', 10, 1 );
function action_woocommerce_save_account_details_errors( $args ){
    if ( isset($_POST['billing_birthdate']) && empty($_POST['billing_birthdate']) ) {
        $args->add( 'error', __( 'Please provide a birth date', 'woocommerce' ) );
    }
}


// Save Birthdate Field in My Account -> Billing Address
add_action('woocommerce_customer_save_address', 'save_billing_birthdate_on_account', 10, 2);
function save_billing_birthdate_on_account($user_id, $address_type) {   
    if ($address_type === 'billing' && isset($_POST['billing_birthdate']) && !empty($_POST['billing_birthdate'])) {
        $birthdate = sanitize_text_field($_POST['billing_birthdate']);
        if (DateTime::createFromFormat('Y-m-d', $birthdate)) { 
            update_user_meta($user_id, 'billing_birthdate', $birthdate);
        }
    }
}


// Save on - my account
/*add_action( 'woocommerce_save_account_details', 'action_woocommerce_save_account_details', 10, 1 );
function action_woocommerce_save_account_details( $user_id ) {  
    if( isset($_POST['billing_birthdate']) && ! empty($_POST['billing_birthdate']) ) {
        update_user_meta( $user_id, 'billing_birthdate', sanitize_text_field($_POST['billing_birthdate']) );
    }
}*/