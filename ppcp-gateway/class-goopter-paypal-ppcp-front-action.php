<?php

defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Front_Action {

    public static bool $is_user_logged_in_before_checkout;
    public static string $checkout_started_from;
    private $goopter_ppcp_plugin_name;
    public $api_log;
    public Goopter_PayPal_PPCP_Payment $payment_request;
    public $setting_obj;
    public Goopter_PayPal_PPCP_Smart_Button $smart_button;
    protected static $_instance = null;
    public $procceed = 1;
    public $reject = 2;
    public $retry = 3;
    public $is_sandbox;
    public $merchant_id;
    public $dcc_applies;
    public $paymentaction;
    public $title;
    public $advanced_card_payments;
    public $three_d_secure_contingency;
    public $product;
    public $skip_final_review;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        self::$is_user_logged_in_before_checkout = function_exists('is_user_logged_in') && is_user_logged_in();
        $this->goopter_ppcp_plugin_name = 'goopter_ppcp';
        $this->goopter_ppcp_load_class();
        $this->paymentaction = $this->setting_obj->get('paymentaction', 'capture');
        $this->title = $this->setting_obj->get('title', GOOPTER_PPCP_NAME . ' - Built by Goopter');
        $this->advanced_card_payments = 'yes' === $this->setting_obj->get('enable_advanced_card_payments', 'no');
        $this->is_sandbox = 'yes' === $this->setting_obj->get('testmode', 'no');
        if ($this->dcc_applies->for_country_currency() === false) {
            $this->advanced_card_payments = false;
        }
        $this->three_d_secure_contingency = $this->setting_obj->get('3d_secure_contingency', 'SCA_WHEN_REQUIRED');
        if (!has_action('woocommerce_api_' . strtolower('Goopter_PayPal_PPCP_Front_Action'))) {
            add_action('woocommerce_api_' . strtolower('Goopter_PayPal_PPCP_Front_Action'), array($this, 'handle_wc_api'));
        }

        add_filter('woocommerce_currency', array($this, 'goopter_get_scm_current_woocommerce_currency'), 99, 1);

        add_action("woocommerce_checkout_create_order", array($this, "goopter_convert_order_prices_to_active_currency"), 9999, 2);

        add_action('set_logged_in_cookie', [$this, 'handle_logged_in_cookie_nonce_on_checkout'], 1000, 6);
    }

    public function goopter_ppcp_load_class() {
        try {
            if (!class_exists('Goopter_WC_Gateway_PPCP_Settings')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Log')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-log.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Payment')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-payment.php');
            }
            if (!class_exists('Goopter_PayPal_PPCP_DCC_Validate')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-dcc-validate.php');
            }
            if (!class_exists('Goopter_PayPal_PPCP_Smart_Button')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-smart-button.php');
            }
            $this->setting_obj = Goopter_WC_Gateway_PPCP_Settings::instance();
            $this->api_log = Goopter_PayPal_PPCP_Log::instance();
            $this->payment_request = Goopter_PayPal_PPCP_Payment::instance();
            $this->dcc_applies = Goopter_PayPal_PPCP_DCC_Validate::instance();
            $this->smart_button = Goopter_PayPal_PPCP_Smart_Button::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    /**
     * This hook adds the logged in user data in cookie so that any function that creates the nonce will get latest
     * nonce data without needing to trigger another api call to get valid nonce.
     * @param $logged_in_cookie
     * @param $expire
     * @param $expiration
     * @param $user_id
     * @param $scheme
     * @param $token
     */
    function handle_logged_in_cookie_nonce_on_checkout($logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token) {
        $_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
    }

    public function handle_wc_api() {
        global $wp;
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- nonce is verified in the each action if needed
        if (!empty($_GET['goopter_ppcp_action'])) {
            switch ($_GET['goopter_ppcp_action']) {
                case "cancel_order":
                    Goopter_Session_Manager::clear();
                    wp_redirect(wc_get_cart_url());
                    exit();
                case "create_order":
                    // clear any notices in woocommerce session so that next request can fulfil the updated request
                    // basically this is an edge case when first request fails due to any issue with error in session
                    // and a user tries to click place order button again.
                    wc_clear_notices();

                    global $woocommerce;
                    // Remove the shipping address override flag from session so that we can use the
                    // PayPal returned shipping address for other payment methods except google_pay
                    Goopter_Session_Manager::unset('shipping_address_updated_from_callback');

                    if (!isset($_POST['woocommerce-process-checkout-nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
                        // Nonce is invalid
                        $logger = wc_get_logger();  // Get the logger instance
                        $logger->error('create order nonce verification failed. Nonce not valid.', array('source' => 'ppcp-gateway/class-goopter-paypal-ppcp-front-action.php'));
                    }

                    // check if billing and shipping details posted from frontend then update cart
                    if (isset($_REQUEST['billing_address_source'])) {
                        $billing_address = json_decode(stripslashes(sanitize_text_field(wp_unslash($_REQUEST['billing_address_source']))), true);
                        if (!empty($billing_address)) {
                            !empty($billing_address['addressLines'][0]) ? $woocommerce->customer->set_billing_address_1($billing_address['addressLines'][0]) : null;
                            !empty($billing_address['addressLines'][1]) ? $woocommerce->customer->set_billing_address_2($billing_address['addressLines'][1]) : null;
                            $woocommerce->customer->set_billing_first_name($billing_address['givenName']);
                            $woocommerce->customer->set_billing_last_name($billing_address['familyName']);
                            $woocommerce->customer->set_billing_postcode($billing_address['postalCode']);
                            $woocommerce->customer->set_billing_country($billing_address['countryCode']);
                            $woocommerce->customer->set_billing_city($billing_address['locality']);
                            $woocommerce->customer->set_billing_state($billing_address['administrativeArea']);
                        }
                    }
                    if (isset($_REQUEST['shipping_address_source'])) {
                        $shipping_address = json_decode(stripslashes(sanitize_text_field(wp_unslash($_REQUEST['shipping_address_source']))), true);
                        if (!empty($shipping_address)) {
                            !empty($shipping_address['addressLines'][0]) ? $woocommerce->customer->set_shipping_address_1($shipping_address['addressLines'][0]) : null;
                            !empty($shipping_address['addressLines'][1]) ? $woocommerce->customer->set_shipping_address_2($shipping_address['addressLines'][1]) : null;
                            $woocommerce->customer->set_billing_email($shipping_address['emailAddress']);
                            $woocommerce->customer->set_shipping_first_name($shipping_address['givenName']);
                            $woocommerce->customer->set_shipping_last_name($shipping_address['familyName']);
                            $woocommerce->customer->set_shipping_postcode($shipping_address['postalCode']);
                            $woocommerce->customer->set_shipping_country($shipping_address['countryCode']);
                            $woocommerce->customer->set_shipping_city($shipping_address['locality']);
                            $woocommerce->customer->set_shipping_state($shipping_address['administrativeArea']);
                        }
                    }

                    // $request_from_page = sanitize_text_field(wp_unslash($_GET['from'])) ?? '';
                    $request_from_page = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';

                    Goopter_Session_Manager::set('from', $request_from_page);

                    self::$checkout_started_from = $request_from_page;

                    if ('pay_page' === $request_from_page) {
                        // $woo_order_id = sanitize_text_field(wp_unslash($_POST['woo_order_id']));
                        $woo_order_id = isset($_POST['woo_order_id']) ? sanitize_text_field(wp_unslash($_POST['woo_order_id'])) : '';

                        if (isset(WC()->session) && !WC()->session->has_session()) {
                            WC()->session->set_customer_session_cookie(true);
                        }
                        WC()->session->set('order_awaiting_payment', $woo_order_id);
                        $order = wc_get_order($woo_order_id);
                        do_action('woocommerce_before_pay_action', $order);
                        $error_messages = wc_get_notices('error');
                        wc_clear_notices();
                        if (empty($error_messages)) {
                            $this->payment_request->goopter_ppcp_create_order_request($woo_order_id);
                        } else {
                            $errors = [];
                            foreach ($error_messages as $error) {
                                $errors[] = $error['notice'];
                            }
                            ob_start();
                            wp_send_json_error(array('messages' => $errors));
                        }
                        exit();
                    } elseif ('checkout' === $request_from_page) {
                        if (isset($_POST) && !empty($_POST)) {
                            self::$is_user_logged_in_before_checkout = is_user_logged_in();
                            $address = array();
                            if (isset($_POST['address']) && strlen(sanitize_text_field(wp_unslash($_POST['address']))) > 2) {
                                $address = array();
                                $address_data = json_decode(stripslashes(sanitize_text_field(wp_unslash($_POST['address']))), true);
                                foreach ($address_data as $key => $address_value) {
                                    foreach ($address_value as $sub_key => $value) {
                                        $address[$key . '_' . $sub_key] = $value;
                                    }
                                }
                                if (isset($_POST['goopter_ppcp_payment_method_title'])) {
                                    $address['goopter_ppcp_payment_method_title'] = wc_clean(sanitize_text_field(wp_unslash($_POST['goopter_ppcp_payment_method_title'])));
                                }
                                if (isset($_POST['radio-control-wc-payment-method-options'])) {
                                    $address['radio-control-wc-payment-method-options'] = wc_clean(sanitize_text_field(wp_unslash($_POST['radio-control-wc-payment-method-options'])));
                                    $address['payment_method'] = wc_clean(sanitize_text_field(wp_unslash($_POST['radio-control-wc-payment-method-options'])));
                                }
                                Goopter_Session_Manager::set('checkout_post', $address);
                                $_POST = $address;
                            } else {
                                self::$is_user_logged_in_before_checkout = is_user_logged_in();
                                Goopter_Session_Manager::set('checkout_post', $_POST);
                                add_action('woocommerce_after_checkout_validation', array($this, 'maybe_start_checkout'), PHP_INT_MAX, 2);
                                WC()->checkout->process_checkout();
                                if (wc_notice_count('error') > 0) {
                                    WC()->session->set('reload_checkout', true);
                                    $error_messages_data = wc_get_notices('error');
                                    $error_messages = array();
                                    foreach ($error_messages_data as $key => $value) {
                                        $error_messages[] = $value['notice'];
                                    }
                                    wc_clear_notices();
                                    ob_start();
                                    wp_send_json_error(array('messages' => $error_messages));
                                }
                            }
                            add_action('woocommerce_after_checkout_validation', array($this, 'maybe_start_checkout'), PHP_INT_MAX, 2);
                            WC()->checkout->process_checkout();
                            if (wc_notice_count('error') > 0) {
                                WC()->session->set('reload_checkout', true);
                                $error_messages_data = wc_get_notices('error');
                                $error_messages = array();
                                foreach ($error_messages_data as $key => $value) {
                                    $error_messages[] = $value['notice'];
                                }
                                wc_clear_notices();
                                ob_start();
                                wp_send_json_error(array('messages' => $error_messages));
                            }
                        } else {
                            $_GET['from'] = 'checkout_top';
                            Goopter_Session_Manager::set('from', 'checkout_top');
                            $this->payment_request->goopter_ppcp_create_order_request();
                        }
                        exit();
                    } elseif ('product' === $request_from_page) {
                        try {
                            if (!class_exists('Goopter_PayPal_PPCP_Product')) {
                                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-product.php');
                            }
                            // $paymentMethod = sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp_payment_method_title'])) ?? null;
                            $paymentMethod = isset($_REQUEST['goopter_ppcp_payment_method_title']) 
                                ? sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp_payment_method_title'])) 
                                : null;

                            // $addToCart = sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp-add-to-cart'])) ?? null;
                            $addToCart = isset($_REQUEST['goopter_ppcp-add-to-cart']) 
                                ? sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp-add-to-cart'])) 
                                : null;

                            if (!empty($addToCart) && goopter_ppcp_get_order_total() > 0) {
                                WC()->cart->empty_cart();
                            }

                            if (goopter_ppcp_get_order_total() === 0 && !empty($addToCart)) {
                                $this->product = Goopter_PayPal_PPCP_Product::instance();
                                $this->product::goopter_ppcp_add_to_cart_action();
                            }
                            if (goopter_ppcp_get_order_total() === 0) {
                                $wc_notice = __('Sorry, your session has expired.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                                $all_notices = WC()->session->get('wc_notices', []);
                                if (wc_notice_count('error')) {
                                    wc_clear_notices();
                                    foreach ($all_notices['error'] as $notice) {
                                        $wc_notice = $notice['notice'] ?? $notice;
                                        break;
                                    }
                                }
                                wc_add_notice($wc_notice);
                                wp_send_json_error($wc_notice);
                            } else {
                                $this->payment_request->goopter_ppcp_create_order_request();
                            }
                            exit();
                        } catch (Exception $ex) {
                            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
                            $this->api_log->log($ex->getMessage(), 'error');
                        }
                    } else {
                        $this->payment_request->goopter_ppcp_create_order_request();
                        exit();
                    }
                    break;
                case 'shipping_address_update':
                    global $woocommerce;
                    // $paymentMethod = sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp_payment_method_title'])) ?? null;
                    $paymentMethod = isset($_REQUEST['goopter_ppcp_payment_method_title']) && !empty($_REQUEST['goopter_ppcp_payment_method_title']) 
                        ? sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp_payment_method_title'])) 
                        : null;
                    // $woo_order_id = sanitize_text_field(wp_unslash($_POST['woo_order_id'])) ?? null;
                    $woo_order_id = isset($_POST['woo_order_id']) && !empty($_POST['woo_order_id']) 
                        ? sanitize_text_field(wp_unslash($_POST['woo_order_id'])) 
                        : null;

                    if (isset($_REQUEST['shipping_address_source'])) {
                        $shipping_address = json_decode(stripslashes(sanitize_text_field(wp_unslash($_REQUEST['shipping_address_source']))), true);
                        $shipping_address = $shipping_address['shippingDetails'] ?? null;
                        if (!empty($shipping_address)) {
                            Goopter_Session_Manager::set('shipping_address_updated_from_callback', time());
                            !empty($shipping_address['addressLines'][0]) ? $woocommerce->customer->set_shipping_address_1($shipping_address['addressLines'][0]) : null;
                            !empty($shipping_address['address1']) ? $woocommerce->customer->set_shipping_address_1($shipping_address['address1']) : null;
                            !empty($shipping_address['addressLines'][1]) ? $woocommerce->customer->set_shipping_address_2($shipping_address['addressLines'][1]) : null;
                            !empty($shipping_address['address2']) ? $woocommerce->customer->set_shipping_address_2($shipping_address['address2']) : null;
                            isset($shipping_address['emailAddress']) && $woocommerce->customer->set_billing_email($shipping_address['emailAddress']);
                            isset($shipping_address['givenName']) && $woocommerce->customer->set_shipping_first_name($shipping_address['givenName']);
                            if (isset($shipping_address['name'])) {
                                $splitName = goopter_split_name($shipping_address['name']);
                                $woocommerce->customer->set_shipping_first_name($splitName[0]);
                                $woocommerce->customer->set_shipping_last_name($splitName[1]);
                            }
                            isset($shipping_address['familyName']) && $woocommerce->customer->set_shipping_last_name($shipping_address['familyName']);
                            isset($shipping_address['postalCode']) && $woocommerce->customer->set_shipping_postcode($shipping_address['postalCode']);
                            isset($shipping_address['countryCode']) && $woocommerce->customer->set_shipping_country($shipping_address['countryCode']);
                            isset($shipping_address['locality']) && $woocommerce->customer->set_shipping_city($shipping_address['locality']);
                            isset($shipping_address['administrativeArea']) && $woocommerce->customer->set_shipping_state($shipping_address['administrativeArea']);
                        }
                    }
                    if (isset($_REQUEST['billing_address_source'])) {
                        $billing_address = json_decode(stripslashes(sanitize_text_field(wp_unslash($_REQUEST['billing_address_source']))), true);
                        $billing_address = $billing_address['billingDetails'] ?? null;
                        if (!empty($billing_address)) {
                            isset($billing_address['givenName']) && $woocommerce->customer->set_billing_first_name($billing_address['givenName']);

                            if (isset($billing_address['name'])) {
                                $splitName = goopter_split_name($billing_address['name']);
                                $woocommerce->customer->set_billing_first_name($splitName[0]);
                                $woocommerce->customer->set_billing_last_name($splitName[1]);
                            }
                            isset($billing_address['familyName']) && $woocommerce->customer->set_billing_last_name($billing_address['familyName']);
                            !empty($billing_address['addressLines'][0]) ? $woocommerce->customer->set_billing_address_1($billing_address['addressLines'][0]) : null;
                            !empty($billing_address['address1']) ? $woocommerce->customer->set_billing_address_1($billing_address['address1']) : null;
                            !empty($billing_address['addressLines'][1]) ? $woocommerce->customer->set_billing_address_2($billing_address['addressLines'][1]) : null;
                            !empty($billing_address['address2']) ? $woocommerce->customer->set_billing_address_2($billing_address['address2']) : null;
                            isset($billing_address['emailAddress']) && $woocommerce->customer->set_billing_email($billing_address['emailAddress']);
                            isset($billing_address['postalCode']) && $woocommerce->customer->set_billing_postcode($billing_address['postalCode']);
                            isset($billing_address['countryCode']) && $woocommerce->customer->set_billing_country($billing_address['countryCode']);
                            isset($billing_address['locality']) && $woocommerce->customer->set_billing_city($billing_address['locality']);
                            isset($billing_address['administrativeArea']) && $woocommerce->customer->set_billing_state($billing_address['administrativeArea']);
                        }
                    }
                    $orderTotal = WC()->cart->get_total('');
                    // $addToCart = sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp-add-to-cart'])) ?? null;
                    $addToCart = isset($_REQUEST['goopter_ppcp-add-to-cart']) 
                                ? sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp-add-to-cart'])) 
                                : null;

                    if (!empty($addToCart)) {
                        try {
                            if (!class_exists('Goopter_PayPal_PPCP_Product')) {
                                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-product.php');
                            }
                            $this->product = Goopter_PayPal_PPCP_Product::instance();
                            $this->product::goopter_ppcp_add_to_cart_action();
                        } catch (Exception $ex) {
                            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
                            $this->api_log->log($ex->getMessage(), 'error');
                        }
                    }
                    if (!empty($woo_order_id)) {
                        $order = wc_get_order($woo_order_id);
                        if (is_a($order, 'WC_Order')) {
                            $response = $this->payment_request->goopter_get_updated_checkout_payment_data($order);
                        } else {
                            $response = [
                                'status' => false,
                                'message' => __('Order ID is invalid', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')
                            ];
                        }
                    } else {
                        $response = $this->payment_request->goopter_get_updated_checkout_payment_data();
                    }
                    wp_send_json($response);
                    break;
                case "display_order_page":
                    $this->goopter_ppcp_display_order_page();
                    break;
                case "handle_js_errors":
                    $_POST = json_decode(file_get_contents('php://input'), true);
                    if (isset($_POST['error']['msg']) && isset($_POST['error']['source']) && isset($_POST['error']['line'])) {
                        $errorLine = html_entity_decode(sanitize_text_field(wp_unslash($_POST['error']['msg'])), ENT_QUOTES) . ', file: ' . sanitize_text_field(wp_unslash($_POST['error']['source'])) . ', line:' . sanitize_text_field(wp_unslash($_POST['error']['line']));
                    } else {
                        $errorLine = !empty($_POST['error']) ? wp_json_encode(sanitize_text_field(wp_unslash($_POST['error'])), true) : '';
                    }
                    if (isset($_POST['logTrace'])) {
                        $errorLine .= "\nLog Trace: " . wp_json_encode(sanitize_text_field(wp_unslash($_POST['logTrace'])), true);
                    }
                    wc_get_logger()->error($errorLine, array('source' => 'goopter_ppcp_js_errors'));
                    break;
                case "cc_capture":
                    wc_clear_notices();
                    // Required for order pay form, as there will be no data in session
                    // Goopter_Session_Manager::set('paypal_order_id', wc_clean(sanitize_text_field(wp_unslash($_GET['paypal_order_id']))));
                    $paypal_order_id = isset($_GET['paypal_order_id']) && !empty($_GET['paypal_order_id'])
                        ? wc_clean(sanitize_text_field(wp_unslash($_GET['paypal_order_id'])))
                        : null;
                    Goopter_Session_Manager::set('paypal_order_id', $paypal_order_id);

                    $from = Goopter_Session_Manager::get('from', '');

                    if (!class_exists('Goopter_WC_Gateway_PPCP_Settings')) {
                        include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
                    }
                    $this->setting_obj = Goopter_WC_Gateway_PPCP_Settings::instance();
                    $this->skip_final_review = 'yes' === $this->setting_obj->get('skip_final_review', 'no');

                    if ('checkout_top' === $from && !$this->skip_final_review) {
                        if (ob_get_length()) {
                            ob_end_clean();
                        }
                        WC()->session->set('reload_checkout', true);
                        wp_send_json_success(array(
                            'result' => 'success',
                            // 'redirect' => add_query_arg(array('paypal_order_id' => wc_clean(sanitize_text_field(wp_unslash($_GET['paypal_order_id']))), 'utm_nooverride' => '1', 'wfacp_is_checkout_override' => 'yes'), untrailingslashit(wc_get_checkout_url())),
                            'redirect' => add_query_arg(
                                array(
                                    'paypal_order_id' => isset($_GET['paypal_order_id']) && !empty($_GET['paypal_order_id'])
                                        ? wc_clean(sanitize_text_field(wp_unslash($_GET['paypal_order_id'])))
                                        : null,
                                    'utm_nooverride' => '1',
                                    'wfacp_is_checkout_override' => 'yes'
                                ),
                                untrailingslashit(wc_get_checkout_url())
                            ),
                        ));
                        exit();
                    } else {
                        $this->goopter_ppcp_cc_capture();
                    }
                    break;
                case "direct_capture":
                    // Goopter_Session_Manager::set('paypal_order_id', wc_clean(sanitize_text_field(wp_unslash($_GET['paypal_order_id']))));
                    Goopter_Session_Manager::set(
                        'paypal_order_id',
                        isset($_GET['paypal_order_id']) && !empty($_GET['paypal_order_id'])
                            ? wc_clean(sanitize_text_field(wp_unslash($_GET['paypal_order_id'])))
                            : null
                    );
                    // Goopter_Session_Manager::set('paypal_payer_id', wc_clean(sanitize_text_field(wp_unslash($_GET['paypal_payer_id']))));
                    Goopter_Session_Manager::set(
                        'paypal_payer_id',
                        isset($_GET['paypal_payer_id']) && !empty($_GET['paypal_payer_id'])
                            ? wc_clean(sanitize_text_field(wp_unslash($_GET['paypal_payer_id'])))
                            : null
                    );

                    $this->goopter_ppcp_direct_capture();
                    break;
                case "regular_capture":
                    $this->goopter_ppcp_regular_capture();
                    break;
                case "regular_cancel":
                    Goopter_Session_Manager::clear();
                    wp_redirect(wc_get_checkout_url());
                    exit();
                    break;
                case "paypal_create_payment_token":
                    $this->payment_request->goopter_ppcp_paypal_create_payment_token();
                    exit();
                case "paypal_create_payment_token_free_signup_with_free_trial":
                    $this->payment_request->goopter_ppcp_paypal_create_payment_token_free_signup_with_free_trial();
                    exit();
                case "advanced_credit_card_create_payment_token":
                    $this->payment_request->goopter_ppcp_advanced_credit_card_create_payment_token();
                    exit();
                case "advanced_credit_card_create_payment_token_free_signup_with_free_trial":
                    $this->payment_request->goopter_ppcp_advanced_credit_card_create_payment_token_free_signup_with_free_trial();
                    exit();
                case "advanced_credit_card_create_payment_token_sub_change_payment":
                    $this->payment_request->goopter_ppcp_advanced_credit_card_create_payment_token_sub_change_payment();
                    exit();
                case "paypal_create_payment_token_sub_change_payment":
                    $this->payment_request->goopter_ppcp_paypal_create_payment_token_sub_change_payment();
                    exit();
                case "update_cart_oncancel":
                    if (!empty($_REQUEST['goopter_ppcp-add-to-cart']) && is_numeric((int) $_REQUEST['goopter_ppcp-add-to-cart'])) {
                        if (class_exists('WooCommerce')) {
                            $error_messages = wc_get_notices('error');
                            wc_clear_notices();
                            $product_id = sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp-add-to-cart']));
                            $quantity = !empty($_REQUEST['quantity']) ? sanitize_text_field(wp_unslash($_REQUEST['quantity'])) : 0;
                            $cart = WC()->cart;
                            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                                if (!empty($cart_item['product_id']) && $cart_item['product_id'] == $product_id) {
                                    $updated_quantity = $cart_item['quantity'] - $quantity;
                                    if ($updated_quantity > 0) {
                                        $cart->set_quantity($cart_item_key, $updated_quantity);
                                    } else {
                                        $cart->remove_cart_item($cart_item_key);
                                    }
                                }
                            }
                            $cart->calculate_totals();
                            if (ob_get_length()) {
                                ob_end_clean();
                            }
                            wp_send_json(['status' => true]);
                        }
                    }
                    if (ob_get_length()) {
                        ob_end_clean();
                    }
                    wp_send_json(['status' => false]);
                    exit();
                case "goopter_ppcp_cc_setup_tokens":
                    $this->payment_request->goopter_ppcp_advanced_credit_card_setup_tokens();
                    exit();
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
    }

    public function goopter_ppcp_regular_capture() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- no security issue
        if (isset($_GET['token']) && !empty($_GET['token'])) {
            Goopter_Session_Manager::set('paypal_order_id', wc_clean(sanitize_text_field(wp_unslash($_GET['token']))));
            // phpcs:enable WordPress.Security.NonceVerification.Recommended -- no security issue
        } else {
            wp_redirect(wc_get_checkout_url());
            exit();
        }
        $order_id = goopter_ppcp_get_awaiting_payment_order_id();
        if (goopter_ppcp_is_valid_order($order_id) === false || empty($order_id)) {
            wp_redirect(wc_get_checkout_url());
            exit();
        }
        $this->paymentaction = apply_filters('goopter_ppcp_paymentaction', $this->paymentaction, $order_id);
        $order = wc_get_order($order_id);
        if ($this->paymentaction === 'capture') {
            $is_success = $this->payment_request->goopter_ppcp_order_capture_request($order_id, $need_to_update_order = false);
        } else {
            $is_success = $this->payment_request->goopter_ppcp_order_auth_request($order_id);
        }
        $order->update_meta_data('_paymentaction', $this->paymentaction);
        $order->update_meta_data('_enviorment', ($this->is_sandbox) ? 'sandbox' : 'live');
        $order->save_meta_data();
        Goopter_Session_Manager::clear();
        if ($is_success) {
            WC()->cart->empty_cart();
            wp_redirect($this->goopter_ppcp_get_return_url($order));
        } else {
            // set this to null so that frontend third party plugin doesn't trigger reload on update_order_review ajax call
            WC()->session->set('reload_checkout', null);

            wp_redirect(wc_get_checkout_url());
        }
        exit();
    }

    public function goopter_ppcp_get_return_url($order = null) {
        if ($order) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = wc_get_endpoint_url('order-received', '', wc_get_checkout_url());
        }

        return apply_filters('woocommerce_get_return_url', $return_url, $order);
    }

    public function goopter_ppcp_cc_capture() {
        try {
            $this->paymentaction = apply_filters('goopter_ppcp_paymentaction', $this->paymentaction, null);
            $goopter_ppcp_paypal_order_id = Goopter_Session_Manager::get('paypal_order_id', false);
            if (!empty($goopter_ppcp_paypal_order_id)) {
                $order_id = goopter_ppcp_get_awaiting_payment_order_id();
                $order = wc_get_order($order_id);
                if ($order === false) {
                    if (!class_exists('Goopter_PayPal_PPCP_Checkout')) {
                        include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-checkout.php';
                    }
                    $ppcp_checkout = Goopter_PayPal_PPCP_Checkout::instance();
                    try {
                        $order_id = $ppcp_checkout->goopter_ppcp_create_order();
                        $order = wc_get_order($order_id);
                    } catch (Exception $exception) {
                        Goopter_Session_Manager::unset('paypal_transaction_details');
                        Goopter_Session_Manager::unset('paypal_order_id');
                        remove_filter('woocommerce_get_checkout_url', [$this->smart_button, 'goopter_ppcp_woocommerce_get_checkout_url']);
                        wc_add_notice($exception->getMessage(), 'error');
                        // Clear any warnings from the error buffer before sending a final JSON response to the frontend.
                        if (ob_get_length()) {
                            ob_end_clean();
                        }
                        wp_send_json_success(array(
                            'result' => 'failure',
                            'message' => $exception->getMessage(),
                            'redirect' => goopter_get_checkout_url()
                        ));
                        exit();
                    }
                }
                $liability_shift_result = 1;
                if ($this->advanced_card_payments) {
                    $api_response = Goopter_Session_Manager::get('paypal_transaction_details');
                    if (empty($api_response)) {
                        $api_response = $this->payment_request->goopter_ppcp_get_checkout_details($goopter_ppcp_paypal_order_id);
                    }
                    $liability_shift_result = $this->goopter_ppcp_liability_shift($order, $api_response);
                }
                if ($liability_shift_result === 1) {
                    if ($this->paymentaction === 'capture') {
                        $is_success = $this->payment_request->goopter_ppcp_order_capture_request($order_id, true);
                        if (!$is_success) {
                            $error_message = __('We cannot process your order with the payment information that you provided. Please use an alternate payment method.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                        }
                    } else {
                        $is_success = $this->payment_request->goopter_ppcp_order_auth_request($order_id);
                        if (!$is_success) {
                            $error_message = __('We cannot process your order with the payment information that you provided. Please use an alternate payment method.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                        }
                    }
                    $order->update_meta_data('_paymentaction', $this->paymentaction);
                    $order->update_meta_data('_enviorment', ($this->is_sandbox) ? 'sandbox' : 'live');
                    $order->save_meta_data();
                } elseif ($liability_shift_result === 2) {
                    $is_success = false;
                    $error_message = __('We cannot process your order with the payment information that you provided. Please use an alternate payment method.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                    wc_add_notice($error_message, 'error');
                } elseif ($liability_shift_result === 3) {
                    $is_success = false;
                    $error_message = __('We cannot process your order with the payment information that you provided. Please use an alternate payment method.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                    wc_add_notice($error_message, 'error');
                }
                if ($is_success) {
                    WC()->cart->empty_cart();
                    Goopter_Session_Manager::clear();
                    if (ob_get_length())
                        ob_end_clean();
                    wp_send_json_success(array(
                        'result' => 'success',
                        'redirect' => apply_filters('woocommerce_get_return_url', $order->get_checkout_order_received_url(), $order),
                    ));
                } else {
                    Goopter_Session_Manager::clear();
                    if (ob_get_length()) {
                        ob_end_clean();
                    }
                    // set this to null so that frontend third party plugin doesn't trigger reload on update_order_review ajax call
                    WC()->session->set('reload_checkout', null);
                    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- no security issue
                    if (isset($_GET['is_pay_page']) && 'yes' === $_GET['is_pay_page']) {
                        wp_send_json_success(array(
                            'result' => 'failure',
                            'message' => $error_message,
                            'redirect' => $order->get_checkout_payment_url()
                        ));
                    } else {
                        remove_filter('woocommerce_get_checkout_url', [$this->smart_button, 'goopter_ppcp_woocommerce_get_checkout_url']);
                        wp_send_json_success(array(
                            'result' => 'failure',
                            'message' => $error_message,
                            'redirect' => goopter_get_checkout_url()
                        ));
                    }
                }
                exit();
                // phpcs:enable WordPress.Security.NonceVerification.Recommended -- no security issue
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_display_order_page() {
        try {
            $order_id = goopter_ppcp_get_awaiting_payment_order_id();
            if (goopter_ppcp_is_valid_order($order_id) === false || empty($order_id)) {
                wp_redirect(wc_get_cart_url());
                exit();
            }
            $order = wc_get_order($order_id);
            // $this->payment_request->goopter_ppcp_update_woo_order_data(sanitize_text_field(wp_unslash($_GET['paypal_order_id'])));
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- no security issue
            $this->payment_request->goopter_ppcp_update_woo_order_data(
                isset($_GET['paypal_order_id']) && !empty($_GET['paypal_order_id'])
                    ? sanitize_text_field(wp_unslash($_GET['paypal_order_id']))
                    : null
            );
            
            WC()->cart->empty_cart();
            Goopter_Session_Manager::clear();
            wp_safe_redirect(apply_filters('woocommerce_get_return_url', $order->get_checkout_order_received_url(), $order));
            exit();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended -- no security issue
    }

    public function maybe_start_checkout($data, $errors = null) {
        try {
            foreach ($errors->errors as $code => $messages) {
                $data = $errors->get_error_data($code);
                foreach ($messages as $message) {
                    wc_add_notice($message, 'error', $data);
                }
            }
            if (0 === wc_notice_count('error')) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
                $this->goopter_ppcp_set_customer_data($_POST);
            } else {
                $error_messages = array();
                $messages = wc_get_notices('error');
                if (!empty($messages)) {
                    foreach ($messages as $key => $message) {
                        $error_messages[] = $message['notice'];
                    }
                }
                if (empty($error_messages)) {
                    $error_messages = $errors->get_error_messages();
                }
                wc_clear_notices();
                if (ob_get_length()) {
                    ob_end_clean();
                }
                wp_send_json_error(array('messages' => $error_messages));
                exit;
            }
            if (goopter_has_saved_payment_token_been_used() === false) {
                // check if an existing failed order is being processed.
                if (!class_exists('Goopter_PayPal_PPCP_Checkout')) {
                    include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-checkout.php';
                }
                $ppcp_checkout = Goopter_PayPal_PPCP_Checkout::instance();
                $order_id = $ppcp_checkout->goopter_ppcp_create_order();
                $this->payment_request->goopter_ppcp_create_order_request($order_id > 0 ? $order_id : null);
                exit();
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- no security issue
    }

    public function goopter_ppcp_set_customer_data($data) {
        try {
            $customer = WC()->customer;
            $billing_first_name = empty($data['billing_first_name']) ? '' : wc_clean($data['billing_first_name']);
            $billing_last_name = empty($data['billing_last_name']) ? '' : wc_clean($data['billing_last_name']);
            $billing_country = empty($data['billing_country']) ? '' : wc_clean($data['billing_country']);
            $billing_address_1 = empty($data['billing_address_1']) ? '' : wc_clean($data['billing_address_1']);
            $billing_address_2 = empty($data['billing_address_2']) ? '' : wc_clean($data['billing_address_2']);
            $billing_city = empty($data['billing_city']) ? '' : wc_clean($data['billing_city']);
            $billing_state = empty($data['billing_state']) ? '' : wc_clean($data['billing_state']);
            $billing_postcode = empty($data['billing_postcode']) ? '' : wc_clean($data['billing_postcode']);
            $billing_phone = empty($data['billing_phone']) ? '' : wc_clean($data['billing_phone']);
            $billing_email = empty($data['billing_email']) ? '' : wc_clean($data['billing_email']);
            if (isset($data['ship_to_different_address'])) {
                $shipping_first_name = empty($data['shipping_first_name']) ? '' : wc_clean($data['shipping_first_name']);
                $shipping_last_name = empty($data['shipping_last_name']) ? '' : wc_clean($data['shipping_last_name']);
                $shipping_country = empty($data['shipping_country']) ? '' : wc_clean($data['shipping_country']);
                $shipping_address_1 = empty($data['shipping_address_1']) ? '' : wc_clean($data['shipping_address_1']);
                $shipping_address_2 = empty($data['shipping_address_2']) ? '' : wc_clean($data['shipping_address_2']);
                $shipping_city = empty($data['shipping_city']) ? '' : wc_clean($data['shipping_city']);
                $shipping_state = empty($data['shipping_state']) ? '' : wc_clean($data['shipping_state']);
                $shipping_postcode = empty($data['shipping_postcode']) ? '' : wc_clean($data['shipping_postcode']);
            } else {
                $shipping_first_name = $billing_first_name;
                $shipping_last_name = $billing_last_name;
                $shipping_country = $billing_country;
                $shipping_address_1 = $billing_address_1;
                $shipping_address_2 = $billing_address_2;
                $shipping_city = $billing_city;
                $shipping_state = $billing_state;
                $shipping_postcode = $billing_postcode;
            }
            $customer->set_shipping_country($shipping_country);
            $customer->set_shipping_address($shipping_address_1);
            $customer->set_shipping_address_2($shipping_address_2);
            $customer->set_shipping_city($shipping_city);
            $customer->set_shipping_state($shipping_state);
            $customer->set_shipping_postcode($shipping_postcode);
            $customer->set_shipping_first_name($shipping_first_name);
            $customer->set_shipping_last_name($shipping_last_name);
            $customer->set_billing_first_name($billing_first_name);
            $customer->set_billing_last_name($billing_last_name);
            $customer->set_billing_country($billing_country);
            $customer->set_billing_address_1($billing_address_1);
            $customer->set_billing_address_2($billing_address_2);
            $customer->set_billing_city($billing_city);
            $customer->set_billing_state($billing_state);
            $customer->set_billing_postcode($billing_postcode);
            $customer->set_billing_phone($billing_phone);
            $billing_email = $customer->get_billing_email();
            if(empty($billing_email)) {
                $customer->set_billing_email($billing_email);
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_liability_shift($order, $response_object) {
        if (!empty($response_object)) {
            $response = json_decode(wp_json_encode($response_object), true);
            if (!empty($response['payment_source']['card']['authentication_result']['liability_shift'])) {
                $LiabilityShift = isset($response['payment_source']['card']['authentication_result']['liability_shift']) ? strtoupper($response['payment_source']['card']['authentication_result']['liability_shift']) : '';
                $EnrollmentStatus = isset($response['payment_source']['card']['authentication_result']['three_d_secure']['enrollment_status']) ? strtoupper($response['payment_source']['card']['authentication_result']['three_d_secure']['enrollment_status']) : '';
                $AuthenticationResult = isset($response['payment_source']['card']['authentication_result']['three_d_secure']['authentication_status']) ? strtoupper($response['payment_source']['card']['authentication_result']['three_d_secure']['authentication_status']) : '';
                $liability_shift_order_note = __('3D Secure response', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                $liability_shift_order_note .= "\n";
                $liability_shift_order_note .= 'Liability Shift : ' . goopter_ppcp_readable($LiabilityShift);
                $liability_shift_order_note .= "\n";
                $liability_shift_order_note .= 'Enrollment Status : ' . $EnrollmentStatus;
                $liability_shift_order_note .= "\n";
                $liability_shift_order_note .= 'Authentication Status : ' . $AuthenticationResult;
                if ($order) {
                    $order->add_order_note($liability_shift_order_note);
                }
                if ($LiabilityShift === 'POSSIBLE') {
                    return $this->procceed;
                }
                if ($LiabilityShift === 'UNKNOWN') {
                    return $this->retry;
                }
                if ($LiabilityShift === 'NO') {
                    if ($EnrollmentStatus === 'B' && empty($AuthenticationResult)) {
                        return $this->procceed;
                    }
                    if ($EnrollmentStatus === 'U' && empty($AuthenticationResult)) {
                        return $this->procceed;
                    }
                    if ($EnrollmentStatus === 'N' && empty($AuthenticationResult)) {
                        return $this->procceed;
                    }
                    if ($AuthenticationResult === 'R') {
                        return $this->reject;
                    }
                    if ($AuthenticationResult === 'N') {
                        return $this->reject;
                    }
                    if ($AuthenticationResult === 'U') {
                        return $this->retry;
                    }
                    if (!$AuthenticationResult) {
                        return $this->retry;
                    }
                    return $this->procceed;
                }
                return $this->procceed;
            } else {
                return $this->procceed;
            }
        }
        return $this->retry;
    }

    public function goopter_ppcp_create_woo_order() {
        if (!class_exists('Goopter_PayPal_PPCP_Checkout')) {
            include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-checkout.php';
        }
        $ppcp_checkout = Goopter_PayPal_PPCP_Checkout::instance();
        $ppcp_checkout->process_checkout();
    }

    public function goopter_ppcp_direct_capture() {
        $this->goopter_ppcp_create_woo_order();
    }

    // public function goopter_ppcp_download_zip_file($github_zip_url, $plugin_zip_path) {
    //     $request_headers = array();
    //     $request_headers[] = 'Accept: */*';
    //     $request_headers[] = 'Accept-Encoding: gzip, deflate, br';
    //     $request_headers[] = 'Connection: keep-alive';
    //     $fp = fopen($plugin_zip_path, 'w+');
    //     $ch = curl_init($github_zip_url);
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
    //     curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_TIMEOUT, -1);
    //     curl_setopt($ch, CURLOPT_VERBOSE, false);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    //     $data = curl_exec($ch);
    //     fwrite($fp, $data);
    //     curl_close($ch);
    //     fclose($fp);
    // }

    public function goopter_ppcp_download_zip_file($github_zip_url, $plugin_zip_path) {
        // Set up request arguments
        $args = [
            'headers' => [
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
            ],
            'timeout' => 0, // No timeout (equivalent to CURLOPT_TIMEOUT -1)
            'sslverify' => false, // Disable SSL verification (equivalent to CURLOPT_SSL_VERIFYPEER and CURLOPT_SSL_VERIFYHOST)
        ];
    
        // Perform the GET request
        $response = wp_remote_get($github_zip_url, $args);
    
        // Use the WooCommerce logger
        $logger = wc_get_logger();
    
        // Check for request errors
        if (is_wp_error($response)) {
            $logger->error('Error downloading the zip file: ' . $response->get_error_message(), ['source' => 'goopter_ppcp']);
            return;
        }
    
        // Get the response body
        $body = wp_remote_retrieve_body($response);
    
        // // Write the file to the specified path
        // if (!empty($body)) {
        //     $fp = fopen($plugin_zip_path, 'w+');
        //     if ($fp) {
        //         fwrite($fp, $body);
        //         fclose($fp);
        //     } else {
        //         $logger->error('Unable to write to file: ' . $plugin_zip_path, ['source' => 'goopter_ppcp']);
        //     }
        // } else {
        //     $logger->error('Error: Response body is empty.', ['source' => 'goopter_ppcp']);
        // }
        global $wp_filesystem;

        // Ensure WP_Filesystem is loaded
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Initialize WP_Filesystem
        WP_Filesystem();

        // Write the file to the specified path
        if ( ! empty( $body ) ) {
            if ( $wp_filesystem->put_contents( $plugin_zip_path, $body, FS_CHMOD_FILE ) ) {
                // Success: File written successfully
            } else {
                $logger->error( 'Unable to write to file: ' . $plugin_zip_path, [ 'source' => 'goopter_ppcp' ] );
            }
        } else {
            $logger->error( 'Error: Response body is empty.', [ 'source' => 'goopter_ppcp' ] );
        }
    }
    
    

    public function goopter_ppcp_add_zipdata($source, $inside_folder, $destination) {
        $plugin_folder_name = $inside_folder;
        $rootPath = $source;
        $zip = new ZipArchive();
        $zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootPath), RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $zip->addFile($filePath, $plugin_folder_name . '/' . $relativePath);
            }
        }
        $zip->close();
    }

    // public function goopter_ppcp_delete_files($dir) {
    //     $files = array_diff(scandir($dir), array('.', '..'));
    //     foreach ($files as $file) {
    //         (is_dir("$dir/$file")) ? $this->goopter_ppcp_delete_files("$dir/$file") : wp_delete_file("$dir/$file");
    //     }
    //     return rmdir($dir);
    // }
    public function goopter_ppcp_delete_files( $dir ) {
        global $wp_filesystem;
    
        // Load WP_Filesystem if it’s not already loaded
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
    
        // Initialize WP_Filesystem
        WP_Filesystem();
    
        // Get list of files and directories excluding '.' and '..'
        $files = array_diff( $wp_filesystem->dirlist( $dir ), array( '.', '..' ) );
    
        foreach ( $files as $file => $file_info ) {
            $file_path = trailingslashit( $dir ) . $file;
    
            if ( $file_info['type'] === 'd' ) {
                // If it's a directory, recursively delete its contents
                $this->goopter_ppcp_delete_files( $file_path );
            } else {
                // Delete file using WP_Filesystem
                $wp_filesystem->delete( $file_path, false, 'f' ); // 'f' indicates file
            }
        }
    
        // Delete the directory itself
        return $wp_filesystem->delete( $dir, false, 'd' ); // 'd' indicates directory
    }
    

    /**
     * Get current active woocommerce currency by scm multicurrency plugin
     */
    public function goopter_get_scm_current_woocommerce_currency($currency) {
        if (function_exists("scd_get_bool_option")) {
            $multicurrency_payment = scd_get_bool_option('scd_general_options', 'multiCurrencyPayment');
        } else {
            $scd_option = get_option('scd_general_options');
            $multicurrency_payment = ( isset($scd_option['multiCurrencyPayment']) && $scd_option['multiCurrencyPayment'] == true ) ? true : false;
        }
        if (function_exists("scd_get_target_currency") && $multicurrency_payment) {
            $currency = scd_get_target_currency();
        }
        return $currency;
    }

    /**
     * Convert the order prices to active currency
     */
    public function goopter_convert_order_prices_to_active_currency($order, $data) {
        if (function_exists("scd_get_bool_option")) {
            $multicurrency_payment = scd_get_bool_option('scd_general_options', 'multiCurrencyPayment');
        } else {
            $scd_option = get_option('scd_general_options');
            $multicurrency_payment = ( isset($scd_option['multiCurrencyPayment']) && $scd_option['multiCurrencyPayment'] == true ) ? true : false;
        }
        if (function_exists("scd_get_target_currency") && $multicurrency_payment) {
            // Get the woocommerce base currency
            $base_currency = get_option('woocommerce_currency');

            // Get the target currency
            $target_currency = scd_get_target_currency();

            $rate = scd_get_conversion_rate_origine($target_currency, $base_currency);

            $rate_c = scd_get_conversion_rate($base_currency, $target_currency);
            foreach ($order->get_items(array('line_item', 'tax', 'shipping', 'fee', 'coupon')) as $item_id => $item) {

                // Line items types are products. Convert their price.
                if ($item['type'] === 'line_item') {
                    $product = $item->get_product();
                    $product_id = $product->get_id();

                    $new_price = $item->get_subtotal() * $rate_c;

                    $item->set_subtotal($new_price);

                    $new_price = $item->get_total() * $rate_c;

                    $item->set_total($new_price);
                } else if ($item['type'] === 'shipping') {

                    $new_price = $item->get_total() * $rate_c;
                    // Set the shipping total
                    $item->set_total($new_price);
                } elseif ($item['type'] === 'fee') {

                    $new_price = $item->get_amount() * $rate_c;
                    // Set the fee total
                    $item->set_total($new_price);
                } elseif ($item['type'] === 'coupon') {

                    $new_price = $item->get_discount() * $rate_c;
                    // Set the discount price
                    $item->set_discount($new_price);

                    $coupons_used = true;
                }
            }
            $order->calculate_totals();
        }
    }
}
