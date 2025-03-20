<?php
    defined( 'ABSPATH' ) || die( "Can't access directly" );

add_action('woocommerce_before_checkout_process', 'validate_with_external_api');

function validate_with_external_api() {

    $posted_data = WC()->checkout->get_posted_data();

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

    foreach( WC()->cart->get_cart() as $cart_item_key => $item ) {

        // Get parent product object
        $product_id = $item['product_id'];
        $parent_product = wc_get_product( $product_id );
        $product_name = $parent_product->get_title();

        if( $item['variation_id'] > 0 ){
            $variation_id = !empty($posted_data['prescription_picture']) ? $item['variation_id'] : ($product_name == 'Semaglutide' ? 1630 : 1649); // Variation product            
        } else {            
            $variation_id = 0;
        }
        
        
        if( !has_term( 'glp-1', 'product_cat', $product_id ) ) continue;

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
                $weekly_dose_preference =  $variation->get_attribute('pa_weekly-dose') ;
                $current_dose_name = 'current_dose_'.strtolower($product_name);
            }
        }

    }

    
    if( !has_term( 'glp-1', 'product_cat', $product_id ) ) return;

    $current_past_med_conds_array = [];
    foreach ($posted_data as $key => $value) {
        if( strpos( $key, 'condition_' ) === 0 && !empty($value) ) {
            if($key == 'condition_noneoftheabove'){
                $current_past_med_conds_array = [];
            }
            else {
                $condition = str_replace(['condition_','_'], ['',' '], $key);
                $current_past_med_conds_array[]= $condition;
            }
        }
    }
    $current_past_med_conds = !empty($current_past_med_conds_array) ? implode(', ', $current_past_med_conds_array) : 'None';

    switch ($posted_data[$current_dose_name]) {
        case 'Semaglutide 0.25mg':
        case 'Tirzepatide 2.5mg':
            $current_dose = 'Weightloss1';
            break;
        case 'Semaglutide 0.5mg':
        case 'Tirzepatide 5mg':
            $current_dose = 'Weightloss2';
            break;
        case 'Semaglutide 1mg':
        case 'Tirzepatide 7.5mg':
            $current_dose = 'Weightloss3';
            break;
        case 'Semaglutide 1.5mg':
        case 'Semaglutide 1.7mg':
        case 'Tirzepatide 10mg':
            $current_dose = 'Weightloss4';
            break;
        case 'Semaglutide 2.5mg':
        case 'Tirzepatide 12.5mg':
        case 'Tirzepatide 15mg':
            $current_dose = 'Weightloss5';
            break;
        
        default:
            // code...
            break;
    }


    switch ($posted_data['new_dose']) {
        case 'same':
            $new_dose_text = 'Stay at the same dose or equivalent dose';
            break;
        case 'increase':
            $new_dose_text = 'Increase the dose if a higher one is available, or continue with my current dose if it is already at the maximum';
            break;
        case 'decrease':
            $new_dose_text = 'Decrease dose';
            break;
        
        default:
            // code...
            break;
    }
    
    $masterId = uniqid(substr($posted_data['billing_first_name'], 0, 1).substr($posted_data['billing_last_name'], 0, 1).'_');

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
                "weightlossPreference" => isset($posted_data['new_dose']) ? sanitize_text_field($posted_data['new_dose']) : 'N/A',
                "currentDose" => isset($current_dose) ? sanitize_text_field($current_dose) : 'N/A',
                "patientPreference" => [
                    [
                        "name" => $product_name.' '.$weekly_dose_preference,
                        "strength" => $product_med_str,
                        "quantity" => $product_med_qty,
                        "refills" => $product_med_ref,
                        "medId" => $product_sku
                    ]
                ],                         
            ],
            "masterId" => "".$masterId."",
            "company" => 'soSoThin',
            "pharmacyId" => $pharmacy_id,
            "visitType" => $visit_type
    ];

    $i = 1;
    $args1["formObj"]["Q".$i] = "What was your sex assigned at birth? POSSIBLE ANSWERS: Male; Female; Other";
    $args1["formObj"]["A".$i++] = isset($posted_data['sex']) ? $posted_data['sex'] : '';

    if($posted_data['sex'] == 'Female') {
        $args1["formObj"]["Q".$i] = "Are you currently pregnant, breastfeeding or planning to become pregnant? POSSIBLE ANSWERS: Yes; No";
        $args1["formObj"]["A".$i++] = isset($posted_data['pregnant']) ? $posted_data['pregnant'] : '';
        $args1["formObj"]["Q".$i] = "Consent (pregnancy): Read the following for more information about this product and its potential side effects: It is not safe to take these medications while pregnant or breastfeeding. The FDA advises that these medications may pose a risk to a developing fetus. Oral contraceptives alone may not be effective, as the medication can reduce their effectiveness. The FDA specifically recommends continuing oral contraception alongside a barrier method (like condoms) for the first month after starting a weight loss medication and for the first month after any dose increase. Alternatively, you can switch to a non-oral contraceptive method (such as an IUD or implant) before beginning the medication. After stopping the medication, you should continue using a backup method, such as condoms, for two months to ensure the medication has fully cleared your system before trying to conceive. Additionally, its safety during breastfeeding is unknown, so if you are nursing, consult your doctor to explore safer weight loss options.";
        $args1["formObj"]["A".$i++] = "I acknowledge that I have read and understood the above information.";
    }

    $args1["formObj"]["Q".$i] = "What is your height in feet and inches?";
    $args1["formObj"]["A".$i++] = isset($posted_data['feet']) && isset($posted_data['inches']) ? sanitize_text_field($posted_data['feet'])." ' ".sanitize_text_field($posted_data['inches']) : '';
    $args1["formObj"]["Q".$i] = "What is your weight in pounds?";
    $args1["formObj"]["A".$i++] = isset($posted_data['pounds']) ? sanitize_text_field($posted_data['pounds']) : '';
    $args1["formObj"]["Q".$i] = "Your BMI is";
    $args1["formObj"]["A".$i++] = isset($posted_data['bmi']) ? sanitize_text_field($posted_data['bmi']) : '';
    $args1["formObj"]["Q".$i] = "Consent (BMI): The traditional use of weight loss medications is for individuals with a BMI of 30 and above or to those who are overweight who have associated health conditions. Using it for someone with a BMI range (27-29) without an accompanying health condition is termed \"off-label.\" Using a medication \"off-label\" refers to the practice of prescribing a drug for a purpose, age group, dosage, or form of administration that is not included in the approved labeling by regulatory agencies like the U.S. Food and Drug Administration (FDA). While a medication undergoes rigorous testing for specific uses before receiving approval, healthcare providers may discover through clinical experience or research that it can be effective for treating other conditions. There may be benefits such as weight reduction for individuals within your range. If you agree to this off-label use, it's crucial to follow the prescribed regimen and report any concerns. Please discuss any questions with us.";
    $args1["formObj"]["A".$i++] = "I acknowledge that I have read and understood the above information.";
    $args1["formObj"]["Q".$i] = "Your current or past medical conditions?";
    $args1["formObj"]["A".$i++] = $current_past_med_conds;

    if($posted_data['condition_gallbladder']) {
        $args1["formObj"]["Q".$i] = "Consent (Gallbladde): Read the following for more information about this product and its potential side effects.Gallbladder disease information:You noted that you have gallbladder disease or previous removal of our gallbladder. This medication may still be a good option. However, this medication can affect how the body handles fats and bile. If you have had your gallbladder removed, the body's ability to store and release bile is altered. Bile is crucial for digestion and fat absorption. This medication may increase the likelihood of gastrointestinal side effects in these individuals because it can alter fat metabolism and bile flow. This can lead to symptoms such as diarrhea and stomach pain. Additionally, medications that affect digestion and appetite, like this medication, might alter the absorption and metabolism of other nutrients (like fat soluble vitamins such as vitamin A, D, E, and K) and medications. This is particularly important for those without a gallbladder, as their digestive system already operates differently from those with a functioning gallbladder. If you wish to move forward, it is important to eat smaller and more frequent meals. In addition, to ensure that you're receiving enough vitamins, you should avoid processed foods while eating plenty of fruits and vegetables, as well as considering the use of a multi-vitamin unless told by your provider to avoid these for other reasons. If you have asymptomatic gallstones, please note that these medications and weight loss itself may result in gallstone formation which could result in the obstruction of the normal flow of bile which can result in infection, pancreatitis, and/or emergent need for gallbladder removal. It is important to receive prompt medical evaluation if symptoms appear as delayed action may result in serious harm or death if untreated.";
        $args1["formObj"]["A".$i++] = "I acknowledge that I have read and understood the above information.";
    }

    if(!empty($current_past_med_conds_array)) {
        $args1["formObj"]["Q".$i] = "Please tell us more about your medical condition(s) that you selected:";
        $args1["formObj"]["A".$i++] = isset($posted_data['selected_medical_conditions']) ? sanitize_text_field($posted_data['selected_medical_conditions']) : '';
    }

    $args1["formObj"]["Q".$i] = "Have you had a gastric bypass in the past 6 months? POSSIBLE ANSWERS: Yes; No";
    $args1["formObj"]["A".$i++] = isset($posted_data['gastric_bypass']) ? $posted_data['gastric_bypass'] : '';
    $args1["formObj"]["Q".$i] = "Are you allergic to any of the following: Ozempic (Semaglutide), Mounjaro (Tirzepatide), Wegovy (Semaglutide), Zepbound (Tirzepatide), Saxenda (Liraglutide), Trulicity (dulaglutide) ? POSSIBLE ANSWERS: Yes; No";
    $args1["formObj"]["A".$i++] = isset($posted_data['allergic_any_glp1_med']) ? $posted_data['allergic_any_glp1_med'] : '';
    $args1["formObj"]["Q".$i] = "Do you take any of the following medications: Insulin Glimepiride (Amaryl), Meglitinides such as repaglinide or nateglinide, Glipizide (Glucotrol and Glucotrol XL), Glyburide (Micronase, Glynase, and Diabeta), Sitagliptin, Saxagliptin, Linagliptin, Alogliptin? POSSIBLE ANSWERS: Yes; No";
    $args1["formObj"]["A".$i++] = isset($posted_data['prohibited_medications']) ? $posted_data['prohibited_medications'] : '';
    $args1["formObj"]["Q".$i] = "Are you currently, or have you in the past two months, taken any GLP-1 medications? POSSIBLE ANSWERS: Semaglutide (Ozempic, Wegovy, Rybelsus); Tirzepatide (Zepbound, Mounjaro);None of these";
    $args1["formObj"]["A".$i++] = isset($posted_data['current_meds_sem_tirz']) ? ( $posted_data['current_meds_sem_tirz']=='semaglutide' ? 'Semaglutide (Ozempic, Wegovy, Rybelsus) ' : ( $posted_data['current_meds_sem_tirz']=='tirzepatide' ? 'Tirzepatide (Zepbound, Mounjaro) ' : 'None of these' ) ) : '';

    if($posted_data['current_meds_sem_tirz']=='semaglutide' || $posted_data['current_meds_sem_tirz']=='tirzepatide' ) {
        $args1["formObj"]["Q".$i] = "Have you experienced side effects from your current medication? POSSIBLE ANSWERS: Yes; No";
        $args1["formObj"]["A".$i++] = isset($posted_data['current_meds_side_efects']) ? $posted_data['current_meds_side_efects'] : '';

        if($posted_data['current_meds_side_efects'] == 'Yes') {
            $args1["formObj"]["Q".$i] = "Please describe the side effects that you've experienced";
            $args1["formObj"]["A".$i++] = isset($posted_data['current_meds_side_efects_textarea']) ? sanitize_text_field($posted_data['current_meds_side_efects_textarea']) : '';
        }

        if($posted_data['current_meds_sem_tirz']=='semaglutide') {
            $args1["formObj"]["Q".$i] = "Which Semaglutide (Ozempic, Wegovy, Rybelsus) dose most closely matches your most recent dose? POSSIBLE ANSWERS: Semaglutide 0.25mg; Semaglutide 0.5mg; Semaglutide 1mg; Semaglutide 1.5mg; Semaglutide 1.7mg; Semaglutide 2.5mg";
            $args1["formObj"]["A".$i++] = isset($posted_data['current_dose_semaglutide']) ? $posted_data['current_dose_semaglutide'] : '';
        }

        if($posted_data['current_meds_sem_tirz']=='tirzepatide') {
            $args1["formObj"]["Q".$i] = "Which Tirzepatide (Zepbound, Mounjaro) dose most closely matches your most recent dose? POSSIBLE ANSWERS: Tirzepatide 2.5mg; Tirzepatide 5mg; Tirzepatide 7.5mg; Tirzepatide 10mg; Tirzepatide 12.5mg; Tirzepatide 15mg";
            $args1["formObj"]["A".$i++] = isset($posted_data['current_dose_tirzepatide']) ? $posted_data['current_dose_tirzepatide'] : '';
        }

        $args1["formObj"]["Q".$i] = "How would you like to continue your treatment? POSSIBLE ANSWERS: Stay at the same dose or equivalent dose; Increase the dose if a higher one is available, or continue with my current dose if it's already at the maximum; Decrease dose";
        $args1["formObj"]["A".$i++] = isset($new_dose_text) ? $new_dose_text : '';


        $args1["formObj"]["Q".$i] = "Do you have a picture of your current prescription? We need this photograph in order to validate your current dosage. POSSIBLE ANSWERS: Yes; No";
        $args1["formObj"]["A".$i++] = isset($posted_data['current_prescription_picture_yes_no']) ? $posted_data['current_prescription_picture_yes_no'] : '';

        
    }

    $args1["formObj"]["Q".$i] = "What other information or questions do you have for the doctor?";
    $args1["formObj"]["A".$i++] = isset($posted_data['other_info_questions']) ? sanitize_text_field($posted_data['other_info_questions']) : '';

    $args1["formObj"]["Q".$i] = "Consent (Truthfulness): Please attest to the following confirming that all information you have provided to us is true and complete. Consent: I verify that I am the patient and that I have answered the questions asked in this intake form. I confirm that I have reviewed and understood all the questions asked of me. I attest that the answers and information I have provided in this questionnaire is true and complete to the best of my knowledge. I understand that it is critical to my health to share complete health information with my doctor. I will not hold the doctor or affiliated medical practice responsible for any oversights or omissions, whether intentional or not, in the information that I provided.";
    $args1["formObj"]["A".$i++] = "I acknowledge that I have read and understood the above information.";

    $args1["formObj"]["Q".$i] = "Consent (GLP-1 and GLP-1/GIP): Indication for Use: You are requesting treatment with a GLP-1 (Ozempic, Wegovy, or compounded semaglutide) or GIP/GLP-1 receptor agonist (Mounjaro, Zepbound, or compounded tirzepatide) medication as part of your treatment plan for the management of weight or obesity. These medications work by mimicking the action of incretin hormones, which help regulate blood sugar levels, promote feeling full, and reduce food intake. Potential Benefits:Weight loss or weight management,Improved blood glucose control,Reduced cardiovascular risk,Potential improvement in overall metabolic health. Potential Side Effects: While these medications can be beneficial, they may also cause side effects.  Although not common, these medications can result in emergency room visits, hospitalizations, or even death. Common and serious side effects include, but are not limited to Common Side Effects: Nausea,Vomiting,Diarrhea,Constipation, Decreased appetite, Indigestion. Serious Side Effects: Pancreatitis (inflammation of the pancreas), Hypoglycemia (low blood sugar) especially when used with other diabetes medications,Gallbladder disease (e.g., gallstones),Kidney problems, Allergic reactions (e.g., rash, itching, swelling), Gastroparesis (paralysis of the bowels). Risks and Considerations:  Pancreatitis: There is a risk of developing pancreatitis. If you experience severe abdominal pain, nausea, or vomiting, you should contact your healthcare provider immediately. Thyroid Tumors: Animal studies have shown an increased risk of thyroid tumors with certain GLP-1 medications. Although this has not been confirmed in humans, please inform your healthcare provider if you have a history of thyroid cancer. Hypoglycemia: When taken with other diabetes medications, particularly insulin or sulfonylureas, there is a risk of low blood sugar. It is important that your provider knows if any of these medications are added to your regimen. Kidney Function: This medication may affect kidney function, particularly in patients with existing kidney disease. Regular monitoring of kidney function may be required. Monitoring and Follow-up: You will require regular follow-up visits to monitor your response to the medication and to assess for any side effects. We may intermittently ask for full-body selfie images to ensure that your reported weight is consistent. I acknowledge the potential benefits, risks, and side effects of GLP-1 or GIP/GLP-1 receptor agonist medications. I understand the importance of regular monitoring and follow-up appointments. I consent to the use of GLP-1 or GIP/GLP-1 receptor agonist medications as part of my treatment plan for overweight or obesity.";
    $args1["formObj"]["A".$i++] = "I acknowledge that I have read and understood the above information.";

       write_log($args1); 
      wc_add_notice('Your order could not be processed. Please check your details and try again.', 'error');

    /*$response = wp_remote_post( 'https://api-staging.belugahealth.com/visit/createNoPayPhotos', 
        array(
          'method' => 'POST',
          'httpversion' => '1.0',
          'headers' => array(
           'Authorization' => 'Bearer z17DZCRW9jjUwuG3uRNr',
            'Content-Type' => 'application/json'),
          'body' => json_encode($args1, JSON_UNESCAPED_SLASHES)
           )
         );

    //write_log($response);

     // Handle API response
    if (is_wp_error($response)) {
        $error_message = 'There was an error connecting to the validation service. Please try again. ('.$response_data['error'].')';
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_code !== 200 || !isset($response_data['data']['masterId'], $response_data['data']['visitId'])) {
            $error_message = 'Your order could not be processed. Please check your details and try again. ('.$response_data['error'].')';
        }
    }

    // If API request failed, cancel order and throw error
    if (!empty($error_message)) {         

        write_log($error_message);       

        // Ensure no further processing happens
        wc_add_notice($error_message, 'error');

    }
    else {

        // If API is successful, store masterId,visitId,responseInfo,payloadData in wc session        
        if (WC()->session) {
            WC()->session->set('masterId', $response_data['data']['masterId']); 
            WC()->session->set('visitId', $response_data['data']['visitId']); 
            WC()->session->set('responseVisitInfo', $response_data['info']);           
            WC()->session->set('payloadData', $args1);
        }
            


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

         
        $images_base64_codes = $image_fields ? compress_resize_encode_images($image_fields,$posted_data) : array();
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
           'Authorization' => 'Bearer z17DZCRW9jjUwuG3uRNr',
            'Content-Type' => 'application/json'),
          'body' => json_encode($args_photos, JSON_UNESCAPED_SLASHES)
           )
         );

        //write_log($response2);
        
        if (is_wp_error($response2)) {
             if (WC()->session)         
                WC()->session->set('responseImagesInfo', "Error sending images.");            
        } else {        
            $response_body2 = wp_remote_retrieve_body($response2);
            $response_data2 = json_decode($response_body2, true);
            if (WC()->session) 
                WC()->session->set('responseImagesInfo', $response_data2['info']);      
        }

    
    }*/

    
    
    //wc_add_notice('Your order could not be processed. Please check your details and try again.', 'error');
}




add_action('woocommerce_checkout_order_processed', 'validate_with_external_api_b',10,3);

function validate_with_external_api_b($order_id, $posted_data, $order) {

    if( !empty(WC()->session->get( 'masterId')) && !empty(WC()->session->get( 'visitId')) && !empty(WC()->session->get('responseVisitInfo')) && !empty(WC()->session->get('responseImagesInfo')) && !empty(WC()->session->get( 'payloadData')) ) {

        $order->update_meta_data('api_masterId', sanitize_text_field(WC()->session->get('masterId')));
        $order->update_meta_data('api_visitId', sanitize_text_field(WC()->session->get('visitId')));        
        $order->update_meta_data('api_response_visit_info', WC()->session->get('responseVisitInfo'));
        $order->update_meta_data('api_response_images_info', WC()->session->get('responseImagesInfo'));
        $order->update_meta_data('api_payload_data', serialize(WC()->session->get('payloadData')));      
        $order->save(); // Ensure data is saved
        add_custom_order_note( $order_id, '(Beluga Health message) : '.WC()->session->get('responseVisitInfo'), true );
        add_custom_order_note( $order_id, '(Beluga Health message) : '.WC()->session->get('responseImagesInfo'), true );
        
    }

   
}






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

function compress_resize_encode_images($image_fields,$posted_data) {
    
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

         // *** Remove the original uploaded file from wp-content/uploads ***
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Save or process $processed_images as needed (e.g., store in database or session)
   /* if (!empty($processed_images)) {
        WC()->session->set('uploaded_images_base64', $processed_images);
    }*/
    return $processed_images;
}



function add_custom_order_note( $order_id, $note, $public = false ) {
    global $wpdb;

    $customer_note = $public ? 1 : 0; // 1 = Public (Visible in My Account), 0 = Private

    // Insert the order note into the comments table
    $wpdb->insert(
        $wpdb->prefix . 'comments',
        array(
            'comment_post_ID'      => $order_id,
            'comment_author'       => 'WooCommerce',
            'comment_author_email' => '',
            'comment_author_url'   => '',
            'comment_content'      => $note,
            'comment_type'         => 'order_note',
            'comment_parent'       => 0,
            'user_id'              => 0,
            'comment_approved'     => 1,
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
    );

    $comment_id = $wpdb->insert_id;

    if ( $comment_id ) {
        // Insert metadata to mark the note as public or private
        $wpdb->insert(
            $wpdb->prefix . 'commentmeta',
            array(
                'comment_id' => $comment_id,
                'meta_key'   => 'is_customer_note',
                'meta_value' => $customer_note
            ),
            array('%d', '%s', '%d')
        );
    }

    return $comment_id; // Return the comment ID if needed
}


