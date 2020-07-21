<?php

/*
Plugin Name: Suncash For Woocomerce
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: SUNCASH Payment Method Integration.
Version: 1.0
Author: Carlos Luis Martinez Leyva
Author URI: ?
License: A "Slug" license name e.g. GPL2
*/
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'suncash_add_gateway_class' );
function suncash_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_Suncash_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'suncash_init_gateway_class' );
function suncash_init_gateway_class()
{

    class WC_Suncash_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {

            $this->id = 'suncash'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Suncash Gateway';
            $this->method_description = 'Description of Suncash payment gateway'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');
            $this->webhook_response = $this->webhook_response ? $this->get_option('webhook_response') : $this->get_option('webhook_response');


            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // Registrar el webhook
            add_action('woocommerce_api_webhook', array($this, 'webhook'));

        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Suncash Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'SunCash Checkout',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with Suncash payment gateway.',
                ),
                'testmode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'test_publishable_key' => array(
                    'title' => 'Merchant Name',
                    'type' => 'text',
                    'description' => 'Refers to the "Merchant Name" field in Suncash Setting.'
                ),
                'test_private_key' => array(
                    'title' => 'Merchant Key',
                    'type' => 'text',
                    'description' => 'Refers to the "Merchant Key" field in Suncash Setting.'
                ),
                //Esta propiedad de onfigurar el webhook es solo para poder prbar sastifactoriamente y poder variarla
                'webhook_response' => array(
                    'title' => 'Payment Webhook',
                    'type' => 'text',
                    'description' => 'Refers to the "Webhook" field in Suncash Setting.'
                )
            );
        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields()
        {

        }

        /*
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts()
        {
// no reason to enqueue JavaScript if API keys are not set
            if (empty($this->private_key) || empty($this->publishable_key)) {
                return;
            }

        }

        /*
          * Fields validation, more in Step 5
         */
        public function validate_fields()
        {
            if (empty($_POST['billing_first_name'])) {
                wc_add_notice('First name is required!', 'error');
                return false;
            }
            return true;

        }

        /*
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment($order_id)
        {

            global $woocommerce;
            //obtener la orden
            $order = wc_get_order($order_id);
            //obtener el total
            $total = $order->get_total();
           $products_details=$this->get_products($order);
            $server_name=$_SERVER['SERVER_NAME'];



            //construir parametros para la comunicación con suncash el P06 va con los elementos de la orden, ahora mismo estan fijos
    $curl = curl_init();
            $params = [
                'method' => 'payment',
                'P01' => $this->private_key,//'f3c29901fd045341e79a95e1fc0be1b9532c9c6fb4717e34baab4aef4187287f',
                'P02' => $this->publishable_key,//'JHM%20COMMERCE',
                'P03' => $total,
                'P04' => $server_name.'_'.$order_id,
                'P05' => $this->webhook_response,
                'P06' => $products_details
            ];
            $params = http_build_query($params);

            curl_setopt_array($curl, array(
                CURLOPT_URL => "http://dev.mysuncash.com/api/checkout.php?$params",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
            ));

            $response = curl_exec($curl);

            curl_close($curl);


            if (json_decode($response, true)['Success'] == 'NO') ;
            {
                if (json_decode($response, true)['ResponseMessage'] == 'Order ID already existing.') {
                    wc_add_notice('Your order has already been processed', 'error');

                    return;
                } elseif (json_decode($response, true)['Success'] == 'YES') {
                    $gg = json_decode($response, true)['ResponseMessage'];
                    $json = json_encode($gg);
                    $url_decode = json_decode($json, true)['url'];
                    $reference_id=json_decode($json, true)['reference_id'];
                    $order->payment_complete($reference_id);
                    return array(
                        'result' => 'success',
                        'redirect' => $url_decode
                    );

                } elseif(json_decode($response, true)['ResponseMessage']===null) {
                    wc_add_notice('Conection Error.', 'error');

                    return;
                }
                 else{
                     wc_add_notice(json_decode($response, true)['ResponseMessage'], 'error');

                    return;
                }

            }

//            if (json_decode($response, true)['Success'] == 'YES') ;
//            {
//                $body = json_decode($response, true)['ResponseMessage'];
//                $json = json_encode(body);
//                $url_decode = json_decode($json, true)['url'];
//                $reference_id=json_decode($json, true)['reference_id'];
//                    //set the reference Id into transaction_Id field
////                $order->set_transaction_id($reference_id);
////                $order->reduce_order_stock();
//
//                // some notes to customer (replace true with false to make it private)
//                $order->add_order_note( 'Hey, your order is in process! Thank you!', true );
//
//                // Empty cart
//                $woocommerce->cart->empty_cart();
//
//                $order->set_transaction_id($reference_id);
//                wc_update_order($this->$order);
////return succes and redirect to suncash url
//                return array(
//                    'result' => 'success',
//                    'redirect' => $url_decode
//                );

//            }




        }

        /*
         * In case you need a webhook, like PayPal IPN etc
         */
        public function webhook()
        {
            global $woocommerce;
            //obtener los agumentos de la respuesta del webhook
            $args = $_SERVER["REQUEST_URI"];
            $arg_arr = explode("/",$args);
            $cnt=count($arg_arr);
            $decoded=base64_decode($arg_arr[$cnt-1]);
//            list($cenpost_reference_id,$suncash_reference_id)= explode('#',$reference_id);
            $status_arr=explode("||",$decoded);
            $count_status_arr=count($status_arr);
            $transaction=$status_arr[0];
            $status=$status_arr[$count_status_arr-2];

            $order=wc_get_orders(['transaction_id'=>$transaction]);
            foreach ($order as $item)
            {
             if($item->get_transaction_id()==$transaction);
                {
                    if($status==="success") {
                        $item->update_status('completed',"Payment Complete by webhook");
                        $item->add_order_note($decoded);
//                        $item->reduce_order_stock();
                        $woocommerce->cart->empty_cart();

                    }
                    elseif($status==="failed"){
                        $item->update_status($count_status_arr-1);
                        $item->add_order_note($decoded);
                    }
                }
            }

            update_option('webhook_debug', $_GET);
        }

        private function get_products($order)
        {
            // Iterating through each "line" items in the order
            $items_order=$order->get_item_count();
            $order_details = '';

            foreach ( $order->get_items() as $item_id => $item_data ) {

                // Get an instance of corresponding the WC_Product object
                $product      = $item_data->get_product();
                $product_name = $product->get_name(); // Get the product name

                $item_quantity = $item_data->get_quantity(); // Get the item quantity

                $item_total = $item_data->get_total(); // Get the item line total

                // Displaying this data (to check)
                $order_details .='~'.$product_name .'|'. $item_quantity .'|'. number_format( $item_total, 2 );
            }
            $order_details = substr($order_details, 1);
//            wc_add_notice($items_order);
            return $order_details;
        }
    }
}