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
            if( !empty($posted_data['prescription_picture']) ) {
                $variation_id = $item['variation_id'];
            }
            else{
                switch ($product_name) {
                    case 'Semaglutide':
                        $variation_id = 1630;
                        break;
                    case 'Tirzepatide':
                        $variation_id = 1649;
                        break;
                    case 'Mounjaro':
                        $variation_id = 1918;
                        break;
                    case 'Ozempic':
                        $variation_id = 1944;
                        break;
                    case 'Wegovy':
                        $variation_id = 1954;
                        break;
                    case 'Zepbound':
                        $variation_id = 1964;
                        break;
                    default:
                        // code...
                        break;
                }
                //$variation_id = !empty($posted_data['prescription_picture']) ? $item['variation_id'] : ($product_name == 'Semaglutide' ? 1630 : 1649); // Variation product  
            }
                      
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
                $weekly_dose_preference =  $variation->get_attribute('pa_dose') ;
                //$current_dose_name = 'current_dose_'.strtolower($product_name);
                $current_dose_name = isset($posted_data['current_meds_sem_tirz']) && $posted_data['current_meds_sem_tirz']=='semaglutide' ? 'current_dose_semaglutide' : (isset($posted_data['current_meds_sem_tirz']) && $posted_data['current_meds_sem_tirz']=='tirzepatide' ? 'current_dose_tirzepatide' : '');
                
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
        case 'Tirzepatide 1.5mg':
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
        case 'Semaglutide 2mg':
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

    if($posted_data['current_meds_sem_tirz']=='neither') {
        $args1["formObj"]["Q".$i] = "People have different sensitivities to medications and commonly experience side effects to standard dosing regimens. Do you commonly experience side effects to medications or feel like you're sensitive to the impacts of most medications? POSSIBLE ANSWERS: Yes; No";
        $args1["formObj"]["A".$i++] = isset($posted_data['commonly_side_effects']) ? $posted_data['commonly_side_effects'] : '';
    }

    if($posted_data['current_meds_sem_tirz']=='semaglutide' || $posted_data['current_meds_sem_tirz']=='tirzepatide' ) {
        $args1["formObj"]["Q".$i] = "Have you experienced side effects from your current medication? POSSIBLE ANSWERS: Yes; No";
        $args1["formObj"]["A".$i++] = isset($posted_data['current_meds_side_efects']) ? $posted_data['current_meds_side_efects'] : '';

        if($posted_data['current_meds_side_efects'] == 'Yes') {
            $args1["formObj"]["Q".$i] = "Please describe the side effects that you've experienced";
            $args1["formObj"]["A".$i++] = isset($posted_data['current_meds_side_efects_textarea']) ? sanitize_text_field($posted_data['current_meds_side_efects_textarea']) : '';
        }

        if($posted_data['current_meds_sem_tirz']=='semaglutide') {
            $args1["formObj"]["Q".$i] = "Which Semaglutide (Ozempic, Wegovy, Rybelsus) dose most closely matches your most recent dose? POSSIBLE ANSWERS: Semaglutide 0.25mg; Semaglutide 0.5mg; Semaglutide 1mg; Semaglutide 1.5mg; Semaglutide 1.7mg; Semaglutide 2mg; Semaglutide 2.5mg";
            $args1["formObj"]["A".$i++] = isset($posted_data['current_dose_semaglutide']) ? $posted_data['current_dose_semaglutide'] : '';
        }

        if($posted_data['current_meds_sem_tirz']=='tirzepatide') {
            $args1["formObj"]["Q".$i] = "Which Tirzepatide (Zepbound, Mounjaro) dose most closely matches your most recent dose? POSSIBLE ANSWERS: Tirzepatide 1.5mg; Tirzepatide 2.5mg; Tirzepatide 5mg; Tirzepatide 7.5mg; Tirzepatide 10mg; Tirzepatide 12.5mg; Tirzepatide 15mg";
            $args1["formObj"]["A".$i++] = isset($posted_data['current_dose_tirzepatide']) ? $posted_data['current_dose_tirzepatide'] : '';
        }

        $args1["formObj"]["Q".$i] = "How would you like to continue your treatment? POSSIBLE ANSWERS: Stay at the same dose or equivalent dose; Increase the dose if a higher one is available, or continue with my current dose if it's already at the maximum; Decrease dose";
        $args1["formObj"]["A".$i++] = isset($new_dose_text) ? $new_dose_text : '';


        $args1["formObj"]["Q".$i] = "Do you have a picture of your current prescription? We need this photograph in order to validate your current dosage. POSSIBLE ANSWERS: Yes; No";
        $args1["formObj"]["A".$i++] = isset($posted_data['current_prescription_picture_yes_no']) ? $posted_data['current_prescription_picture_yes_no'] : '';

        
    }

    $args1["formObj"]["Q".$i] = "What other information or questions do you have for the doctor?";
    $args1["formObj"]["A".$i++] = isset($posted_data['other_info_questions']) ? sanitize_text_field($posted_data['other_info_questions']) : 'N/A';

    $args1["formObj"]["Q".$i] = "Consent (Truthfulness): Please attest to the following confirming that all information you have provided to us is true and complete. Consent: I verify that I am the patient and that I have answered the questions asked in this intake form. I confirm that I have reviewed and understood all the questions asked of me. I attest that the answers and information I have provided in this questionnaire is true and complete to the best of my knowledge. I understand that it is critical to my health to share complete health information with my doctor. I will not hold the doctor or affiliated medical practice responsible for any oversights or omissions, whether intentional or not, in the information that I provided.";
    $args1["formObj"]["A".$i++] = "I acknowledge that I have read and understood the above information.";

    $args1["formObj"]["Q".$i] = "Consent (GLP-1 and GLP-1/GIP): Indication for Use: You are requesting treatment with a GLP-1 (Ozempic, Wegovy, or compounded semaglutide) or GIP/GLP-1 receptor agonist (Mounjaro, Zepbound, or compounded tirzepatide) medication as part of your treatment plan for the management of weight or obesity. These medications work by mimicking the action of incretin hormones, which help regulate blood sugar levels, promote feeling full, and reduce food intake. Potential Benefits:Weight loss or weight management,Improved blood glucose control,Reduced cardiovascular risk,Potential improvement in overall metabolic health. Potential Side Effects: While these medications can be beneficial, they may also cause side effects.  Although not common, these medications can result in emergency room visits, hospitalizations, or even death. Common and serious side effects include, but are not limited to Common Side Effects: Nausea,Vomiting,Diarrhea,Constipation, Decreased appetite, Indigestion. Serious Side Effects: Pancreatitis (inflammation of the pancreas), Hypoglycemia (low blood sugar) especially when used with other diabetes medications,Gallbladder disease (e.g., gallstones),Kidney problems, Allergic reactions (e.g., rash, itching, swelling), Gastroparesis (paralysis of the bowels). Risks and Considerations:  Pancreatitis: There is a risk of developing pancreatitis. If you experience severe abdominal pain, nausea, or vomiting, you should contact your healthcare provider immediately. Thyroid Tumors: Animal studies have shown an increased risk of thyroid tumors with certain GLP-1 medications. Although this has not been confirmed in humans, please inform your healthcare provider if you have a history of thyroid cancer. Hypoglycemia: When taken with other diabetes medications, particularly insulin or sulfonylureas, there is a risk of low blood sugar. It is important that your provider knows if any of these medications are added to your regimen. Kidney Function: This medication may affect kidney function, particularly in patients with existing kidney disease. Regular monitoring of kidney function may be required. Monitoring and Follow-up: You will require regular follow-up visits to monitor your response to the medication and to assess for any side effects. We may intermittently ask for full-body selfie images to ensure that your reported weight is consistent. I acknowledge the potential benefits, risks, and side effects of GLP-1 or GIP/GLP-1 receptor agonist medications. I understand the importance of regular monitoring and follow-up appointments. I consent to the use of GLP-1 or GIP/GLP-1 receptor agonist medications as part of my treatment plan for overweight or obesity.";
    $args1["formObj"]["A".$i++] = "I acknowledge that I have read and understood the above information.";

       write_log($args1); 
      //wc_add_notice('Your order could not be processed. Please check your details and try again.', 'error');

    $response = wp_remote_post( 'https://api-staging.belugahealth.com/visit/createNoPayPhotos', 
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
        $error_message = 'There was an error connecting to the health service. Please try again.';
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ( ($response_code !== 200 || $response_data['status'] !== 200) || !isset($response_data['data']['masterId'], $response_data['data']['visitId'])) {
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
            WC()->session->set('responseVisitInfo', strtoupper($response_data['info']));           
            WC()->session->set('payloadData', $args1);
        }
         write_log($args1);   


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
                WC()->session->set('responseImagesInfo', "ERROR SENDING IMAGES.");            
        } else {        
            $response_body2 = wp_remote_retrieve_body($response2);
            $response_data2 = json_decode($response_body2, true);
            if (WC()->session) 
                WC()->session->set('responseImagesInfo', strtoupper($response_data2['info']));      
        }

    
    }
     
   
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






//Registers a custom REST API endpoint for handling Beluga visit events.
add_action( 'rest_api_init', 'custom_api' );

function custom_api() {
    register_rest_route( 'beluga/visit', 'response', array(
        'methods'  => 'POST',
        'callback' => 'handle_visit_response',
        'permission_callback' => '__return_true',
        //'args'     => prefix_get_visit_arguments(),
    ) );
}

function handle_visit_response( $data ) {
    // Validate the Authorisation header 
    $auth_header = $data->get_header("authorization");
    if (empty($auth_header) || $auth_header !== 'Bearer qgwQVLGyfTskZgWcakkk') {        
        return new WP_REST_Response( array('status' => 403, 'error'   => 'Access denied',), 403 );
    }

    // Retrieve JSON parameters from the request body.
    $params = $data->get_json_params();

    // Ensure masterId and event are provided.
    if ( empty( $params['masterId'] ) || empty( $params['event'] ) ) {       
        return new WP_REST_Response( array('status' => 400, 'error'   => 'masterId and event are required',), 200 );
    }
    
    // Check if the submitted masterId exists in any WooCommerce order. it will query either legacy posts or HPOS tables depending on WooCommerce settings.
   $order_ids = wc_get_orders( array(
    'limit'      => 1,
    'meta_key'   => 'api_masterId',
    'meta_value' => $params['masterId'],
    ) );

    if ( empty( $order_ids ) ) {
        return new WP_REST_Response( array('status' => 400, 'error'   => 'masterId does not exist',), 200 );

    }

    $order = $order_ids[0]; // This is a WC_Order object


    // Conditional validations based on the event type.
    switch ( $params['event'] ) {
        case 'CONSULT_CONCLUDED':
            if ( empty( $params['visitOutcome'] ) || ! in_array( $params['visitOutcome'], array( 'prescribed', 'referred' ) ) ) {               
                return new WP_REST_Response( array('status' => 400, 'error'   => 'Invalid or missing visitOutcome for CONSULT_CONCLUDED event',), 200 );
            }
            if($params['visitOutcome'] == 'prescribed') {
                $order->add_order_note("(Beluga Health message) : PATIENT VISIT HAS BEEN CONCLUDED. THE DOCTOR HAS WRITTEN A PRESCRIPTION.",true );
            }
            else {
                $order->add_order_note("(Beluga Health message) : PATIENT VISIT HAS BEEN CONCLUDED WITHOUT A PRESCRIPTION. THE DOCTOR ISSUED A REFERRAL INSTEAD.",true );
            }
            $order->update_meta_data('visitOutcome', $params['visitOutcome']);
            $order->save();
            break;

        case 'RX_WRITTEN':
            if ( empty( $params['docName'] ) ) {               
                return new WP_REST_Response( array('status' => 400, 'error'   => 'Missing docName for RX_WRITTEN event',), 200 );

            }
            if ( empty( $params['medsPrescribed'] ) || ! is_array( $params['medsPrescribed'] ) ) {               
                return new WP_REST_Response( array('status' => 400, 'error'   => 'medsPrescribed must be a non-empty array for RX_WRITTEN event',), 200 );

            }
            // Validate each item in medsPrescribed.
            foreach ( $params['medsPrescribed'] as $index => $med ) {
                $required_fields = array( 'name', 'strength', 'refills', 'quantity', 'medId', 'rxId' );
                $presc_data = [];
                foreach ( $required_fields as $field ) {
                    if ( ! isset( $med[ $field ] ) || (empty( $med[ $field ]) && $med[ $field ] != 0  ) ) {                        
                        return new WP_REST_Response( array('status' => 400, 'error'   => sprintf( 'Field %s is required for item %d in medsPrescribed', $field, $index ),), 200 );
                    }
                    $presc_data[] = $field.': '.$med[$field];
                }
                $order->add_order_note("(Beluga Health message) : <br>PRESCRIBED MEDICATION INFO: <br>".implode(', <br>',$presc_data)."",true );
                $medsPrescribed = $order->get_meta('medsPrescribed') ?: [];
                $medsPrescribed[] = $presc_data;
                $order->update_meta_data('medsPrescribed', $medsPrescribed);
                $order->save();
            }
            break;

        case 'CONSULT_CANCELED':
            $order->add_order_note("(Beluga Health message) : PATIENT VISIT HAS BEEN CANCELED.",true );
            $order->update_meta_data('visitOutcome', 'canceled');
            $order->save();

        case 'DOCTOR_CHAT':
             handle_doctor_chat_webhook($params);
            break;

        default:           
            return new WP_REST_Response( array('status' => 400, 'error'   => 'Invalid event type',), 200 );
    }

    // Process the data as needed (e.g., store it, trigger other processes, etc.).
    // For demonstration, we return a success response.  
    return new WP_REST_Response( array('status' => 200, 'info'   => 'Successfully received data',), 200 );

}

/*function prefix_get_visit_arguments() {
    $args = array();

    // masterId parameter.
    $args['masterId'] = array(
        'description'       => esc_html__( 'The masterId is used to match a Sosothin order.', 'sosothin' ),
        'type'              => 'string',
        'required'          => true,
        'validate_callback' => function( $value, $request, $param ) {
            if ( ! is_string( $value ) ) {
                //return new WP_Error( 'rest_invalid_param', esc_html__( 'The masterId argument must be a string.', 'sosothin' ), array( 'status' => 400 ) );
                return new WP_REST_Response( array('status' => 400, 'error'   => 'The masterId argument must be a string',), 200 );
            }
            return true;
        },
        'sanitize_callback' => 'sanitize_text_field',
    );

    // event parameter.
    $args['event'] = array(
        'description'       => esc_html__( 'The event parameter to define the type of operation.', 'sosothin' ),
        'type'              => 'string',
        'required'          => true,
        'enum'              => array( 'CONSULT_CANCELED', 'RX_WRITTEN', 'CONSULT_CONCLUDED', 'DOCTOR_CHAT' ),
        'validate_callback' => function( $value, $request, $param ) {
            if ( ! is_string( $value ) ) {
                //return new WP_Error( 'rest_invalid_param', esc_html__( 'The event argument must be a string.', 'sosothin' ), array( 'status' => 400 ) );
                return new WP_REST_Response( array('status' => 400, 'error'   => 'The event argument must be a string',), 200 );
            }
            $attributes = $request->get_attributes();
            $args = $attributes['args'][ $param ];
            if ( ! in_array( $value, $args['enum'], true ) ) {
                //return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%s is not one of %s', 'sosothin' ), $param, implode( ', ', $args['enum'] ) ), array( 'status' => 400 ) );
                return new WP_REST_Response( array('status' => 400, 'error'   => 'Invalid event type',), 200 );

            }
            return true;
        },
    );

    // visitOutcome parameter (for CONSULT_CONCLUDED event).
    $args['visitOutcome'] = array(
        'description'       => esc_html__( 'Visit outcome for CONSULT_CONCLUDED event', 'sosothin' ),
        'type'              => 'string',
        'required'          => false,
        'enum'              => array( 'prescribed', 'referred' ),
        'sanitize_callback' => 'sanitize_text_field',
    );

    // docName parameter (for RX_WRITTEN event).
    $args['docName'] = array(
        'description'       => esc_html__( 'Doctor name for RX_WRITTEN event', 'sosothin' ),
        'type'              => 'string',
        'required'          => false,
        'sanitize_callback' => 'sanitize_text_field',
    );

    // medsPrescribed parameter (for RX_WRITTEN event).
    $args['medsPrescribed'] = array(
        'description'       => esc_html__( 'List of prescribed meds', 'sosothin' ),
        'type'              => 'array',
        'required'          => false,
        'validate_callback' => function( $value, $request, $param ) {
            if ( ! is_array( $value ) ) {
                return new WP_Error( 'rest_invalid_param', esc_html__( 'medsPrescribed must be an array.', 'sosothin' ), array( 'status' => 400 ) );
            }
            foreach ( $value as $index => $med ) {
                $required_fields = array( 'name', 'strength', 'refills', 'quantity', 'medId', 'rxId' );
                foreach ( $required_fields as $field ) {
                    if ( ! isset( $med[ $field ] ) || (empty( $med[ $field ] ) && $med[ $field ] != 0  ) ) {
                        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( 'Field %s is required for item %d in medsPrescribed', 'sosothin' ), $field, $index ), array( 'status' => 400 ) );
                    }
                }
            }
            return true;
        },
        'items'             => array(
            'type'       => 'object',
            'properties' => array(
                'name'     => array( 'type' => 'string' ),
                'strength' => array( 'type' => 'string' ),
                'refills'  => array( 'type' => 'string' ),
                'quantity' => array( 'type' => 'string' ),
                'medId'    => array( 'type' => 'string' ),
                'rxId'     => array( 'type' => 'string' ),
            ),
        ),
    );

    return $args;
}*/







//write log function created by using error_log and woocommerce function wc_get_logger()
if (! function_exists('write_log')) {
                
    function write_log($log)  {
        
        if (is_array($log) || is_object($log)) {
            if ( function_exists('error_log') && true === WP_DEBUG) {
                error_log(print_r($log, true));
            }
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->info(wc_print_r($log, true), array( 'source' => 'custom-log' ));
            }
        } else {
            if ( function_exists('error_log') && true === WP_DEBUG) {
                error_log($log);
            }
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->info($log, array( 'source' => 'custom-log' ));
            }
            
        }
        
    }
}


//Convert all images to jpeg format. Compress the images to width: 1000px before encoding (or ensure that all images are <3MB). Encode the compressed images to base64 encoding. Do not include the MIME at the beginning of this string.
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


//custom customer order notes
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
            'comment_agent'        => 'WooCommerce',
            'comment_type'         => 'order_note',
            'comment_parent'       => 0,
            'user_id'              => 0,
            'comment_approved'     => 1,
            'comment_date'         => current_time( 'mysql' ),
            'comment_date_gmt'     => current_time( 'mysql', 1 ),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
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









//chat doctor - patient webhook
function handle_doctor_chat_webhook($params) {    

    if (!isset($params['masterId'], $params['event'], $params['content'])) {
        return new WP_REST_Response( array('status' => 400, 'error'   => 'Missing parameters',), 200 );        
    }

    $orders = wc_get_orders([
        'limit' => 1,
        'meta_key' => 'api_masterId',
        'meta_value' => sanitize_text_field($params['masterId']),
    ]);

    if (empty($orders)) {       
        return new WP_REST_Response( array('status' => 400, 'error'   => 'masterId does not exist',), 200 );
    }

    $order = $orders[0];
    /*$chat = $order->get_meta('doctor_chat') ?: [];
    $chat[] = [
        'sender' => 'doctor', // or 'customer'
        'message' => sanitize_textarea_field($params['content']),
        'timestamp' => current_time('mysql'),
    ];

    $order->update_meta_data('doctor_chat', $chat);
    $order->save();*/
    $message = sprintf( __( '(Beluga Doctor message) :  %s <div class="hide-on-front"><hr><p style="font-size:13px;">Send a message to the doctor via the <a href="%s">order details page.</a></p></div>', 'sosothin' ), sanitize_textarea_field($params['content']),$order->get_view_order_url() );
    $order->add_order_note($message,true );

    return new WP_REST_Response( array('status' => 200, 'info'   => 'Successfully received message',), 200 );
}


function render_customer_chat_form($order_id) {
    ?>
    <form method="post" enctype="multipart/form-data" class="doctor-chat-form">
        <h5>Send a message or image to the doctor</h5>
        <input type="hidden" name="doctor_chat_order_id" value="<?php echo esc_attr($order_id); ?>">
        <label class="label-img-on"><input type="checkbox" name="enable_img_sending" id="doctor-chat-img-on" value="0" class="input-checkbox fme-ccfw_class_main" ischanged="false"> Check if you would like to send an image.</label>
        <textarea name="doctor_chat_message" required placeholder="Your message to the doctor"></textarea>
        <label for="chat-image" class="label-chat-image" style="display:none;">Please choose image:</label>
        <input id="chat-image" type="file" name="chat_image" accept=".jpg,.jpeg,.png" style="display:none;">
        <input id="doctor-chat-image" type="hidden" name="doctor_chat_image" value="">
        <button class="bricks-button bricks-background-primary" type="submit">Send</button>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkbox = document.getElementById('doctor-chat-img-on');
            const textarea = document.querySelector('textarea[name="doctor_chat_message"]');
            const fileInput = document.getElementById('chat-image');
            const docChatImage = document.getElementById('doctor-chat-image');
            const fileLabel = document.querySelector('label.label-chat-image');

            checkbox.addEventListener('change', function () {
                const isChecked = checkbox.checked;

                // Toggle file input and label
                fileInput.style.display = isChecked ? 'block' : 'none';
                fileLabel.style.display = isChecked ? 'block' : 'none';

                // Toggle textarea
                textarea.style.display = isChecked ? 'none' : 'block';

                // Required attributes
                if (isChecked) {
                    fileInput.setAttribute('required', 'required');
                    textarea.removeAttribute('required');
                } else {
                    fileInput.removeAttribute('required');
                    textarea.setAttribute('required', 'required');
                }
            });

             fileInput.addEventListener('change', function () {
                if (fileInput.files.length > 0) {
                    docChatImage.value = fileInput.files[0].name;
                } else {
                    docChatImage.value = '';
                }
            });
        });
</script>

    <?php
}


//send form data do doctor
add_action('init', function () {     
    if ( !empty($_POST['doctor_chat_order_id']) && (!empty($_POST['doctor_chat_message']) || !empty($_POST['doctor_chat_image'])) ) {
        $order_id = absint($_POST['doctor_chat_order_id']);
       if (!empty($_FILES['chat_image']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';

            $uploaded_file = $_FILES['chat_image'];

            // Get the upload root directory
            $upload_dir = wp_upload_dir();
            $uploads_path = trailingslashit($upload_dir['basedir']); // wp-content/uploads/
            $filename = sanitize_file_name($uploaded_file['name']);
            $target_file_path = $uploads_path . $filename;

            // Move uploaded file to uploads root
            if (move_uploaded_file($uploaded_file['tmp_name'], $target_file_path)) {
                // Set just the filename (not full path) to be used by compress_resize_encode_images
                $_POST['doctor_chat_image'] = $filename;
            } else {
                wc_add_notice(__('Failed to upload image.', 'woocommerce'), 'error');
            }
        }

        $encoded_chat_image = !empty($_POST['doctor_chat_image']) ? compress_resize_encode_images(['doctor_chat_image'],$_POST)['doctor_chat_image'] : '';
        $message =  !empty($_POST['doctor_chat_message']) ? sanitize_textarea_field($_POST['doctor_chat_message']) : '';
        
        $order = wc_get_order($order_id);
        
        $entry = [
            'sender' => 'customer',
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'image'  => $encoded_chat_image
        ];

        
        // Send to doctor via API        
        send_chat_to_doctor_api($order, $entry);

        //wp_safe_redirect(wc_get_account_endpoint_url('doctor-chat'));
        //exit;
    }
});

function send_chat_to_doctor_api($order, $entry) {
    
    /*$user = wp_get_current_user();    
    $first_name = $user->first_name ?: $user->user_firstname;
    $last_name = $user->last_name ?: $user->user_lastname;*/

    $first_name = $order->get_billing_first_name();
    $last_name = $order->get_billing_last_name();
    $content = empty($entry['image']) ? $entry['message'] : $entry['image'];
    $master_id = $order->get_meta('api_masterId') ?: '';

    $chat_args = [
        'firstName' => $first_name,
        'lastName' => $last_name,
        'content' => $content,
        'isMedia' => !empty($entry['image']),
        'masterId' => $master_id,
    ];    
  //print_r($chat_args);
    $response = wp_remote_post('https://api-staging.belugahealth.com/external/receiveChat', 
         array(
          'method' => 'POST',
          'httpversion' => '1.0',
          'headers' => array(
           'Authorization' => 'Bearer z17DZCRW9jjUwuG3uRNr',
            'Content-Type' => 'application/json'),
          'body' => json_encode($chat_args, JSON_UNESCAPED_SLASHES)
           )
    );
    //write_log($response); 

    // Handle API response
    if (is_wp_error($response)) {
        $error_message = 'There was an error connecting to the Beluga Health service. Please try again.';
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_code !== 200 || $response_data['status'] !== 200) {
            $error_message = sprintf( __( 'Your message has not been sent. (%s)', 'sosothin' ), $response_data['info'] );
        }
    }
    if (!empty($error_message)) {
        write_log($error_message); 
        wc_add_notice($error_message, 'error');
    }
    else {
        wc_add_notice( 'Your message has been sent susccessfully!', 'success' );
        if( empty($entry['image']) && !empty($entry['message']) ) {  
           /* $chat = $order->get_meta('doctor_chat') ?: [];      
            $chat[] = $entry;
            $order->update_meta_data('doctor_chat', $chat);
            $order->save();*/
            add_custom_order_note( $order->get_id(), sprintf( __( '(Patient message to Doctor) :  %s', 'sosothin' ), $entry['message'] ), true );  
        }
    }
}
