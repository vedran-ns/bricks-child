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

include(get_stylesheet_directory()."/api/beluga-api.php") ;
include(get_stylesheet_directory()."/api/google-sheet-products-api.php") ;



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



/*##################  Add custom fields to product variations ######################*/

// Add custom fields to variation edit screen
add_action('woocommerce_product_after_variable_attributes', 'add_custom_variation_fields', 10, 3);
function add_custom_variation_fields($loop, $variation_data, $variation) {
    /*woocommerce_wp_text_input([
        'id' => "medicine_concentration_{$loop}",
        'name' => "medicine_concentration[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_concentration', true),
        'label' => __('Concentration', 'woocommerce'),
        'placeholder' => __('1mg/ml', 'woocommerce'),
        'wrapper_class' => 'form-row form-row-first',
    ]);*/

    woocommerce_wp_text_input([
        'id' => "medicine_medinfo_{$loop}",
        'name' => "medicine_medinfo[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_medinfo', true),
        'label' => __('Medication Info', 'woocommerce'),
        'wrapper_class' => 'form-row form-row-first',
    ]);

    woocommerce_wp_text_input([
        'id' => "medicine_strength_{$loop}",
        'name' => "medicine_strength[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_strength', true),
        'label' => __('Strength', 'woocommerce'),
        'wrapper_class' => 'form-row form-row-last',
    ]);

    woocommerce_wp_text_input([
        'id' => "medicine_quantity_{$loop}",
        'name' => "medicine_quantity[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_quantity', true),
        'label' => __('Quantity', 'woocommerce'),
         'wrapper_class' => 'form-row form-row-first',
    ]);

     woocommerce_wp_text_input([
        'id' => "medicine_dispense_{$loop}",
        'name' => "medicine_dispense[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_dispense', true),
        'label' => __('Dispense', 'woocommerce'),
        'wrapper_class' => 'form-row form-row-last',
    ]);
   
    woocommerce_wp_text_input([
        'id' => "medicine_refills_{$loop}",
        'name' => "medicine_refills[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_refills', true),
        'label' => __('Refills', 'woocommerce'),
        'wrapper_class' => 'form-row form-row-first',
    ]);

    woocommerce_wp_text_input([
        'id' => "medicine_days_{$loop}",
        'name' => "medicine_days[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_days', true),
        'label' => __('Days', 'woocommerce'),
        'wrapper_class' => 'form-row form-row-last',
    ]);
    
     woocommerce_wp_textarea_input([
        'id' => "medicine_sig_{$loop}",
        'name' => "medicine_sig[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_sig', true),
        'label' => __('Sig', 'woocommerce'),
        'wrapper_class' => 'form-row form-row-first',

     ]);
    
    woocommerce_wp_textarea_input([
        'id' => "medicine_pharmacy_notes_{$loop}",
        'name' => "medicine_pharmacy_notes[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_pharmacy_notes', true),
        'label' => __('Pharmacy Notes', 'woocommerce'),
        'wrapper_class' => 'form-row form-row-last',
    ]);

    woocommerce_wp_select([
        'id' => "medicine_category_{$loop}",
        'name' => "medicine_category[{$loop}]",
        'value' => get_post_meta($variation->ID, 'medicine_category', true),
        'label' => __('Category', 'woocommerce'),
        'options' => [
            '' => __('Select a Category', 'woocommerce'),
            'Weightloss1' => __('Weightloss1', 'woocommerce'),
            'Weightloss2' => __('Weightloss2', 'woocommerce'),
            'Weightloss3' => __('Weightloss3', 'woocommerce'),
            'Weightloss4' => __('Weightloss4', 'woocommerce'),
            'Weightloss5' => __('Weightloss5', 'woocommerce'),
        ],
         'wrapper_class' => 'form-row form-row-first',
    ]);
}

// Save custom fields for product variations
add_action('woocommerce_save_product_variation', 'save_custom_variation_fields', 10, 2);
function save_custom_variation_fields($variation_id, $i) {
   $fields = [
        //'medicine_concentration',
        'medicine_strength',
        'medicine_quantity',
        'medicine_sig',
        'medicine_refills',
        'medicine_pharmacy_notes',
        'medicine_category',
        'medicine_days',
        'medicine_dispense',
        'medicine_medinfo',
    ];
    
    foreach ($fields as $field) {
        if (isset($_POST[$field][$i])) {
            $value = sanitize_text_field($_POST[$field][$i]);
            update_post_meta($variation_id, $field, $value);
        }
    }
}

add_filter('woocommerce_add_cart_item_data', 'add_custom_variation_fields_to_cart', 10, 3);
function add_custom_variation_fields_to_cart($cart_item_data, $product_id, $variation_id) {
    // Get the variation ID
    $variation_id = !empty($variation_id) ? $variation_id : $product_id;

    // Retrieve custom fields from post meta
    $custom_fields = [
        //'medicine_concentration',
        //'medicine_strength',
        'medicine_quantity',
        //'medicine_sig',
        //'medicine_refills',
        //'medicine_days',
        //'medicine_dispense',
        //'medicine_medinfo',
    ];

    foreach ($custom_fields as $field) {
        $value = get_post_meta($variation_id, $field, true);
        
        if (!empty($value)) {
            $cart_item_data['custom_' . $field] = $value; // Prefix to avoid conflicts
        }
    }
    //error_log(print_r($cart_item_data, true));
    return $cart_item_data;
}


// Display custom fields in cart and order details (optional)
add_filter('woocommerce_get_item_data', 'display_custom_variation_fields_in_cart', 10, 2);
function display_custom_variation_fields_in_cart($item_data, $cart_item) {
    $fields = [
        //'custom_medicine_concentration' => __('Concentration', 'woocommerce'),
        //'custom_medicine_strength' => __('Strength', 'woocommerce'),
        'custom_medicine_quantity' => __('Quantity', 'woocommerce'),
        //'custom_medicine_sig' => __('Sig', 'woocommerce'),
        //'custom_medicine_refills' => __('Refills', 'woocommerce'),
        //'custom_medicine_days' => __('Days', 'woocommerce'),
        //'custom_medicine_dispense' => __('Dispense', 'woocommerce'),
        //'custom_medicine_medinfo' => __('Medication Info', 'woocommerce'),
        //'medicine_pharmacy_notes' => __('Pharmacy Notes', 'woocommerce'),
       // 'medicine_category' => __('Category', 'woocommerce'),
    ];

    foreach ($fields as $meta_key => $label) {
        //print_r($cart_item);
        if (!empty($cart_item[$meta_key]) || $cart_item[$meta_key] == '0') {
            $item_data[] = [
                'name'  => $label,
                'value' => $meta_key == 'custom_medicine_quantity' ? $cart_item[$meta_key]  : $cart_item[$meta_key],
            ];
        }
    }

    return $item_data;
}



// Add custom fields to the variation data on the frontend
add_filter('woocommerce_available_variation', 'add_custom_fields_to_variation_frontend');
function add_custom_fields_to_variation_frontend($variation_data) {
    $fields = [
        //'medicine_concentration' => 'Concentration',
        'medicine_medinfo' => 'Medication Info',
        'medicine_strength' => 'Strength',
        'medicine_quantity' => 'Quantity', 
        'medicine_dispense' => 'Dispense',      
        'medicine_refills' => 'Refills',
        'medicine_days' => 'Days', 
        'medicine_sig' => 'Sig',
        //'medicine_pharmacy_notes' => 'Pharmacy Notes',
        //'medicine_category' => 'Category',
    ];

    foreach ($fields as $meta_key => $label) {
        $value = get_post_meta($variation_data['variation_id'], $meta_key, true);
        if (!empty($value) || $value == '0') {
            $variation_data[$meta_key] = $meta_key == 'medicine_quantity' ? $value : $value;
        }
    }

    return $variation_data;
}

// Display custom fields on the product page (optional)
add_action('woocommerce_single_variation', 'display_custom_fields_on_product_page', 20);
function display_custom_fields_on_product_page() {
    global $product;
    ?>
    <h4 id="var-info" style="margin: 20px 0;display:none;">Info</h4>
    <div class="variation-custom-fields">
        <div id="custom-variation-fields"></div>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            // Update custom fields dynamically based on the selected variation
            $('form.variations_form').on('show_variation', function (event, variation) {
                let fieldsHtml = '';

                if (variation.medicine_medinfo) {
                    fieldsHtml += '<p><strong>Medication Info:</strong> ' + variation.medicine_medinfo + '</p>';
                }
                if (variation.medicine_concentration) {
                    fieldsHtml += '<p><strong>Concentration:</strong> ' + variation.medicine_concentration + '</p>';
                }
                if (variation.medicine_strength) {
                    fieldsHtml += '<p><strong>Strength:</strong> ' + variation.medicine_strength + '</p>';
                }
                if (variation.medicine_quantity) {
                    fieldsHtml += '<p><strong>Quantity:</strong> ' + variation.medicine_quantity + '</p>';
                }
                if (variation.medicine_dispense) {
                    fieldsHtml += '<p><strong>Dispense:</strong> ' + variation.medicine_dispense + '</p>';
                }
                
                if (variation.medicine_refills) {
                    fieldsHtml += '<p><strong>Refills:</strong> ' + variation.medicine_refills + '</p>';
                }
                 if (variation.medicine_days) {
                    fieldsHtml += '<p><strong>Days:</strong> ' + variation.medicine_days + '</p>';
                }
                if (variation.medicine_sig) {
                    fieldsHtml += '<p><strong>Sig:</strong> ' + variation.medicine_sig + '</p>';
                }                
                if (variation.medicine_pharmacy_notes) {
                    fieldsHtml += '<p><strong>Pharmacy Notes:</strong> ' + variation.medicine_pharmacy_notes + '</p>';
                }
                if (variation.medicine_category) {
                    fieldsHtml += '<p><strong>Category:</strong> ' + variation.medicine_category + '</p>';
                }

                $('#custom-variation-fields').html(fieldsHtml);
                $('#var-info').show();
            });

            // Clear custom fields when no variation is selected
            $('form.variations_form').on('hide_variation', function () {
                $('#custom-variation-fields').html('');
            });
        });
    </script>
    <?php
}


/*##################  Limit purchase to the only one product ######################*/
add_filter('woocommerce_add_to_cart_validation', 'limit_cart_to_one_product', 10, 2);
function limit_cart_to_one_product($passed, $product_id) {
    // Get the cart items
    $cart = WC()->cart;

    // Check if there's already a product in the cart
    if ($cart->get_cart_contents_count() > 0) {
         if( has_term( 'glp-1', 'product_cat', $product_id ) ) {     
            // Empty the cart
            $cart->empty_cart();
            wc_add_notice(__('Your cart has been updated to only allow one GLP-1 weight loss medication at a time.', 'woocommerce'), 'notice');
        }
        else {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {             
                //if(in_array($cart_item['data']->name, ['Semaglutide','Tirzepatide']))  {    
                if( has_term( 'glp-1', 'product_cat', $cart_item['product_id'] ) ) {                           
                    $cart->remove_cart_item($cart_item_key); 
                    wc_add_notice(sprintf(__('Your cart has been updated. GLP-1 weight loss medication (%s) has been removed from the cart.', 'woocommerce') , $cart_item['data']->name ), 'notice');
                    break;     
                }  
            }

        }       
    }

    return $passed;
}

add_action('template_redirect', 'first_order_add_bag');
function first_order_add_bag() { 
    if (  WC()->cart->get_cart_contents_count() > 1 ) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {             
            //if(in_array($cart_item['data']->name, ['Semaglutide','Tirzepatide']))  {    
            if( has_term( 'glp-1', 'product_cat', $cart_item['product_id'] ) ) {                           
                WC()->cart->remove_cart_item($cart_item_key);       
                wc_add_notice(__('Your cart has been updated to only allow one GLP-1 weight loss medication at a time.', 'woocommerce'), 'notice');
                break;     
            }  
        }
    }

}

/*##################  add script for calculating BMI on the checkout fields ######################*/
add_action( 'wp_footer', 'woocommerce_checkout_scripts' );
function woocommerce_checkout_scripts() {
    if (is_checkout()) {
        $product_name = '';
        $current_med_use = '';
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {  
            $product_name = lcfirst($cart_item['data']->get_title());
            if ($cart_item['data'] instanceof WC_Product_Variation) {
                foreach ($cart_item['data']->get_variation_attributes() as $atr_key => $atr_value) {
                    if ($atr_key=='attribute_pa_current-medication-use'){
                        $current_med_use = $atr_value;
                        break;
                    }               
                }
            }
            break;
        }
        //$current_med_use_input_id = $current_med_use == 'yes' ? '#current_meds_sem_tirz_'.$product_name : '#current_meds_sem_tirz_neither';
        $current_med_use_input_id = $current_med_use == 'yes' ? '#current_meds_sem_tirz_semaglutide,#current_meds_sem_tirz_tirzepatide' : '#current_meds_sem_tirz_neither';

                
    ?>
        <script type="text/javascript">
            
        jQuery( document ).ready(function( $ ) {  
            $('#bmi').attr('readonly', true);
            //$('#current_meds_sem_tirz_field input:not(<?php //echo  $current_med_use_input_id ?>)').attr('disabled', true);   
            //$('#current_meds_sem_tirz_field input:not(<?php //echo  $current_med_use_input_id ?>)').css('display', 'none');           
            <?php if($current_med_use == 'yes') { ?>
                    //$('#current_meds_sem_tirz_field label[for="current_meds_sem_tirz_neither"]').hide();
            <?php } 
                  else { ?>
                   //$('#current_meds_sem_tirz_field label:not(label[for="<?php //echo  $current_med_use_input_id ?>"]):not(label[for="current_meds_sem_tirz_0"])').hide();
                  <?php } ?>
            $('#condition_noneoftheabove').change(function() {
              if(this.checked) $('input[name^="condition_"]').not(this).prop('checked',false).trigger('change');
            });
            $('input[name^="condition_"]').not('#condition_noneoftheabove').change(function() {
                if (this.checked && $('#condition_noneoftheabove').prop('checked')) {
                    $('#condition_noneoftheabove').prop('checked', false).trigger('change');
                }
            });



            $('#feet, #inches, #pounds').on('keyup mouseup', function () {
                // Get values from the input fields
                const feet = parseFloat(jQuery('#feet').val()) || 0;
                const inches = parseFloat(jQuery('#inches').val()) || 0;
                const pounds = parseFloat(jQuery('#pounds').val()) || 0;

                // Convert feet and inches to total inches
                const totalInches = (feet * 12) + inches;

                if (totalInches > 0) {
                    // Calculate BMI
                    const bmi = (pounds / (totalInches * totalInches)) * 703;

                    // Display BMI in the #bmi_field element
                    //jQuery('#bmi_field').html('<h5 style="font-weight: 400;padding-top: 10px;"> Your BMI is: ' + bmi.toFixed(2) + '</h5>');
                    jQuery('#bmi').val(bmi.toFixed(2)).trigger('change');
                } else {
                    // Handle invalid height input
                    jQuery('#bmi').html('<h5 style="font-weight: 400;padding-top: 10px;"> Your BMI is: ' + 'Enter valid height.'+ '</h5>');
                }
            });
            
            $('#commonly_side_effects_field input').change(function() {
                let regime_type = '';
                let current_dose = '';

                if ($('#current_meds_sem_tirz_field input:checked').val() === 'neither') {
                  if ($('#commonly_side_effects_field input:checked').val() === 'Yes') {
                    regime_type = 'alternative';
                    current_dose = 'lowest';
                  } 
                  else {
                    regime_type = 'standard';
                    current_dose = 'starter';        
                   }

                    $.ajax({
                      url: '/wp-json/medics/v1/get-medics',
                      method: 'GET',
                      data: {
                        regime_type: regime_type,
                        current_dose: current_dose
                      },
                      success: function(response) {
                        //console.log('Google Sheet Response:', response);

                        // Clear old options
                        $('#prefered_dose').empty();

                        // Loop through returned items
                        response.forEach(function(item) {
                          const favName = item.original_row['Favorite Name'] || 'Unnamed';
                          const medId = item.original_row['Medicine Id'] || '';
                          const strength = item.original_row['Strength'] || '';
                          const refills = item.original_row['Refills'] || '';
                          const pharmacyId = item.original_row['Pharmacy ID'] || '';

                          // Construct option value (you can change format if needed)
                          const optionValue = `${medId};${strength};${refills};${pharmacyId}`;

                          $('#prefered_dose').append(new Option(favName, optionValue));
                        });
                      },
                      error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                      }
                    });

                }

                console.log('Selected Regime:', regime_type);
              });

        });
            
        //window.onload = function(){jQuery('input[name^="condition_"]').prop('checked',false).trigger('change');}
        window.onload = function(){
            setTimeout(function() {
                jQuery('input[name^="condition_"]').prop('checked',false).trigger('change');
                //jQuery('#<?php echo  $current_med_use_input_id ?>').click().trigger('change');
            }, 2000 );
            
            // Select the element to observe
            const targetElement = document.getElementById('not_eligible_field');

            // Create a MutationObserver instance
            const observer = new MutationObserver((mutationsList) => {
                mutationsList.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        // Check if the element is now visible
                        if (targetElement.style.display === 'none') {
                            //console.log('The element is now hidden');
                             document.getElementById('place_order').style.display="block";
                            
                            /*if (document.querySelector('#bmi').value.trim() !== "0") {
                                document.querySelectorAll('#consent_confirm_bmi_field, #consent_bmi_content_field, #consent_bmi_heading_field')
    .forEach(el => el.style.display = "block");
                            }*/
                            
                        }
                        else {
                          // console.log('The element is now visible');
                           document.getElementById('place_order').style.display="none";
                           /*document.querySelectorAll('#consent_confirm_bmi_field, #consent_bmi_content_field, #consent_bmi_heading_field')
    .forEach(el => el.style.display = "none");*/
                        }
                    }
                });
            });

            // Configure the observer to watch for style attribute changes
            observer.observe(targetElement, { attributes: true });

            // Optionally, stop observing when no longer needed
            // observer.disconnect();

        
        }
        </script>
    <?php
    }
}


/*##################  Exclude states Alaska AK, Louisiana LA, Mississippi MS, New Hampshire NH, New Mexico NM ######################*/
add_filter( 'woocommerce_states', 'remove_specific_states' );
function remove_specific_states( $states ) {
    $states_to_remove = ['AK', 'LA', 'MS', 'NH', 'NM']; // States to remove

    foreach ( $states_to_remove as $state ) {
        unset( $states['US'][$state] );
    }

    return $states;
}

add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    //print_r($item_data);
    foreach ($item_data as $key => $data) {
        if (isset($data['key']) && $data['key'] === 'Are you currently taking any GLP-1 weight loss medication or have you taken it in the past two months?') { // 'pa_' prefix is used for product attributes
            unset($item_data[$key]);        
        }
        //print_r($data);
    }
    return $item_data;
}, 10, 2);


add_filter('woocommerce_order_item_get_formatted_meta_data', function ($formatted_meta, $item) {
    foreach ($formatted_meta as $key => $meta) {
        if ($meta->key === 'pa_current-medication-use') {
            unset($formatted_meta[$key]);
        }
        //print_r($meta->key.', ');
    }
    return $formatted_meta;
}, 10, 2);




function remove_product_from_cart_programmatically($product_id){
  
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if ($cart_item['product_id'] == $product_id) {            
            WC()->cart->remove_cart_item($cart_item_key);            
        }
    }  
}

/*##################  prevent a custom conditional checkout plugin to be updated ######################*/
add_filter('site_transient_update_plugins', function ($transient) {
    if (isset($transient->response['conditional-checkout-fields-for-woocommerce-custom/conditional-checkout-fields-for-woocommerce.php'])) {
        unset($transient->response['conditional-checkout-fields-for-woocommerce-custom/conditional-checkout-fields-for-woocommerce.php']);
    }
    return $transient;
});





add_action( 'woocommerce_email_customer_details', 'add_beluga_api_response_messages', 10, 4 );
function add_beluga_api_response_messages( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $email->id == 'customer_on_hold_order' || $email->id == 'customer_processing_order' || $email->id == 'new_order' ) {
        //echo '<p>(Beluga Health message): '.$order->get_meta('api_response_visit_info').'. '. $order->get_meta('api_response_images_info').'.</p>'; 
        printf( __( '<p>(Beluga Health message): %s. %s</p><p style="font-size:13px;">Send a message to the doctor via the <a href="%s">order details page.</a></p>', 'sosothin' ), $order->get_meta('api_response_visit_info'),$order->get_meta('api_response_images_info'),$order->get_view_order_url() ) ;  
    }   
}



