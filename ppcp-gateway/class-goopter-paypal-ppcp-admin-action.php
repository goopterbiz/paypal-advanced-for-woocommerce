<?php
defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Admin_Action {

    use Goopter_WC_PPCP_Pre_Orders_Trait;
    use Goopter_WC_Gateway_Base;
    private $goopter_ppcp_plugin_name;
    public ?Goopter_PayPal_PPCP_Payment $payment_request;
    public $payment_response;
    public $goopter_capture_amount = 0;
    public $goopter_refund_amount = 0;
    public $goopter_auth_amount = 0;
    public $order;
    public $currency_code;
    public $goopter_void_amount = 0;
    public $goopter_ppcp_order_status_data = array();
    public $goopter_ppcp_order_actions = array();
    protected static $_instance_self = null;
    public $is_auto_capture_auth;
    public ?Goopter_PayPal_PPCP_Seller_Onboarding $seller_onboarding;
    public $is_sandbox;
    public $merchant_id;
    public $paymentaction;
    public $view_transaction_url;
    private $goopter_direct_pay_webhook_secret;

    public static function instance() {
        if (is_null(self::$_instance_self)) {
            self::$_instance_self = new self();
        }
        return self::$_instance_self;
    }

    public function __construct() {
        $this->goopter_ppcp_plugin_name = 'goopter_ppcp';
        $this->goopter_ppcp_load_class();
        $this->goopter_ppcp_add_hooks();
    }

    public function goopter_ppcp_load_class() {
        try {
            if (!class_exists('Goopter_PayPal_PPCP_Log')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-log.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Payment')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-payment.php');
            }
            if (!class_exists('Goopter_WC_Gateway_PPCP_Settings')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Seller_Onboarding')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-seller-onboarding.php';
            }
            $this->seller_onboarding = Goopter_PayPal_PPCP_Seller_Onboarding::instance();
            $this->api_log = Goopter_PayPal_PPCP_Log::instance();
            $this->payment_request = Goopter_PayPal_PPCP_Payment::instance();
            $this->setting_obj = Goopter_WC_Gateway_PPCP_Settings::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function removeAutoCaptureHooks() {
        remove_action('woocommerce_order_status_processing', array($this, 'goopter_ppcp_capture_payment'));
        remove_action('woocommerce_order_status_completed', array($this, 'goopter_ppcp_capture_payment'));
    }

    public function goopter_ppcp_add_hooks() {
        $this->paymentaction = $this->setting_obj->get('paymentaction', 'capture');
        $this->is_auto_capture_auth = false;
        if ($this->paymentaction === 'authorize') {
            $this->is_auto_capture_auth = 'yes' === $this->setting_obj->get('auto_capture_auth', 'yes');
        }
        $this->is_sandbox = 'yes' === $this->setting_obj->get('testmode', 'no');
        if ($this->is_sandbox) {
            $this->merchant_id = $this->setting_obj->get('sandbox_merchant_id', '');
        } else {
            $this->merchant_id = $this->setting_obj->get('live_merchant_id', '');
        }
        $this->goopter_direct_pay_webhook_secret = $this->setting_obj->get('goopter_direct_pay_webhook_secret_key', '');
        add_action('admin_notices', array($this, 'admin_notices'));
        // On checkout page these hooks conflicts when we change order status to processing or completed from our payment gateway
        // We need to apply these hooks in admin panel
        if ($this->is_auto_capture_auth) {
            add_action('woocommerce_order_status_processing', array($this, 'goopter_ppcp_capture_payment'));
            add_action('woocommerce_order_status_completed', array($this, 'goopter_ppcp_capture_payment'));
            add_action('woocommerce_order_status_cancelled', array($this, 'goopter_ppcp_cancel_authorization'));
            add_action('woocommerce_order_status_refunded', array($this, 'goopter_ppcp_cancel_authorization'));
        }
        add_action('wc_pre_order_status_completed', [$this, 'goopter_ppcp_pre_order_order_status_completed'], 10, 1);
        add_action('woocommerce_process_shop_order_meta', array($this, 'goopter_ppcp_save'), 10, 2);
        add_action('woocommerce_order_item_add_line_buttons', array($this, 'goopter_ppcp_capture_void_refund_submit'), 10, 1);
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'goopter_ppcp_add_order_action_buttons'), 10, 1);
        add_action('admin_enqueue_scripts', array($this, 'goopter_ppcp_add_order_action_js'), 10);
        add_action('woocommerce_admin_order_totals_after_total', array($this, 'goopter_ppcp_add_order_action_item_edit'), 10, 1);
        add_action('woocommerce_after_order_itemmeta', array($this, 'goopter_ppcp_display_capture_details'), 10, 3);
        add_action('woocommerce_after_order_itemmeta', array($this, 'goopter_ppcp_display_refund_details'), 11, 3);
        add_filter('woocommerce_hidden_order_itemmeta', array($this, 'woocommerce_hidden_order_itemmeta'), 10, 1);
        if (!has_action('woocommerce_admin_order_totals_after_tax', array($this, 'goopter_ppcp_display_total_capture'))) {
            add_action('woocommerce_admin_order_totals_after_tax', array($this, 'goopter_ppcp_display_total_capture'), 1, 1);
        }
        add_action('admin_notices', array($this, 'goopter_ppcp_display_payment_authorization_notice'));
        add_action('woocommerce_api_goopter_direct_pay_webhook', array($this, 'goopter_direct_pay_webhook_handler'));
    }
    
    public function goopter_ppcp_pre_order_order_status_completed($order_id) {
        if($this->has_pre_order($order_id) && $this->has_pre_order_charged_upon_release($order_id)){
            if($this->is_paypal_vault_used_for_pre_order()) {
                $this->goopter_ppcp_vault_payment($order_id);
            } else {
                $this->goopter_ppcp_capture_payment ($order_id);
            }
        }
    }

    public function goopter_ppcp_admin_void_action_handler($order, $order_data) {
        try {
            remove_action('woocommerce_order_action_goopter_ppcp_void', array($this, 'goopter_ppcp_admin_void_action_handler'));
            remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
            remove_action('woocommerce_order_status_cancelled', array($this, 'goopter_ppcp_cancel_authorization'));
            remove_action('woocommerce_order_status_refunded', array($this, 'goopter_ppcp_cancel_authorization'));
            $this->payment_request->goopter_ppcp_void_authorized_payment_admin($order, $order_data);
        } catch (Exception $ex) {

        }
    }

    public function goopter_ppcp_admin_capture_action_handler($order, $order_data) {
        try {
            remove_action('woocommerce_order_action_goopter_ppcp_capture', array($this, 'goopter_ppcp_admin_capture_action_handler'));
            remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
            remove_action('woocommerce_order_status_processing', array($this, 'goopter_ppcp_capture_payment'));
            remove_action('woocommerce_order_status_completed', array($this, 'goopter_ppcp_capture_payment'));
            $this->payment_request->goopter_ppcp_capture_authorized_payment_admin($order, $order_data);
        } catch (Exception $ex) {

        }
    }

    public function goopter_ppcp_capture_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $payment_method = $order->get_payment_method();
        $paymentaction = goopter_ppcp_get_post_meta($order, '_paymentaction');
        $auth_transaction_id = goopter_ppcp_get_post_meta($order, '_auth_transaction_id');
        $auto_capture_payment_support_gateways = ['goopter_ppcp', 'goopter_ppcp_cc', 'goopter_ppcp_google_pay', 'goopter_ppcp_apple_pay'];

        if (in_array($payment_method, $auto_capture_payment_support_gateways) && $paymentaction === 'authorize' && !empty($auth_transaction_id)) {
            $trans_details = $this->payment_request->goopter_ppcp_show_details_authorized_payment($auth_transaction_id);
            if ($this->goopter_ppcp_is_authorized_only($trans_details)) {
                $this->payment_request->goopter_ppcp_capture_authorized_payment($order_id);
            }
        }
    }

    public function goopter_ppcp_cancel_authorization($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $payment_method = $order->get_payment_method();
        $transaction_id = $order->get_transaction_id();
        $paymentaction = goopter_ppcp_get_post_meta($order, '_paymentaction');

        if (in_array($payment_method, ['goopter_ppcp_cc', 'goopter_ppcp', 'goopter_ppcp_apple_pay', 'goopter_ppcp_google_pay']) && $transaction_id && $paymentaction === 'authorize') {
            $trans_details = $this->payment_request->goopter_ppcp_show_details_authorized_payment($transaction_id);
            if ($this->goopter_ppcp_is_authorized_only($trans_details)) {
                $response = $this->payment_request->goopter_ppcp_void_authorized_payment($transaction_id);
                if (!is_wp_error($response)) {
                    $note = __("Authorization Voided", 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                } else {
                    $note = __("Void Authorization Failed:", 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce') . ': ' . $response->get_error_message();
                }
                $order->add_order_note($note);
            }
        }
    }

    public function goopter_ppcp_is_authorized_only($trans_details = array()) {
        if (!is_wp_error($trans_details) && !empty($trans_details)) {
            $payment_status = '';
            if (isset($trans_details->status) && !empty($trans_details->status)) {
                $payment_status = $trans_details->status;
            }
            if ('CREATED' === $payment_status || 'PARTIALLY_CAPTURED' === $payment_status) {
                return true;
            }
        }
        return false;
    }

    public function goopter_ppcp_is_display_paypal_transaction_details($post_id, $payment_actions = ["authorize"]) {
        try {
            $order = wc_get_order($post_id);
            if (!empty($order)) {
                $payment_method = $order->get_payment_method();
                $payment_action = goopter_ppcp_get_post_meta($order, '_payment_action');
                if (!empty($payment_method) && !empty($payment_action) && in_array($payment_method, ['goopter_ppcp_cc', 'goopter_ppcp', 'goopter_ppcp_apple_pay', 'goopter_ppcp_google_pay']) && $order->get_total() > 0 && in_array($payment_action, $payment_actions)) {
                    return true;
                }
            }
        } catch (Exception $ex) {

        }
        return false;
    }

    public function goopter_ppcp_save($post_id, $post_or_order_object) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by woocommerce hook: woocommerce_process_shop_order_meta
        if (!empty($_POST['is_ppcp_submited']) && 'yes' === $_POST['is_ppcp_submited']) {
            $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
            if (!is_a($order, 'WC_Order')) {
                return;
            }
            if (!empty($_POST['order_metabox_goopter_ppcp_payment_action'])) {
                $order_data = wc_clean($_POST);
                $action = wc_clean(sanitize_text_field(wp_unslash($_POST['order_metabox_goopter_ppcp_payment_action'])));
                switch ($action) {
                    case 'void':
                        $this->goopter_ppcp_admin_void_action_handler($order, $order_data);
                        break;
                    case 'capture':
                        $this->goopter_ppcp_admin_capture_action_handler($order, $order_data);
                        break;
                    default:
                        break;
                }
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing -- checked by woocommerce hook: woocommerce_process_shop_order_meta
    }

    public function admin_notices() {
        try {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
            if (isset($_GET['page']) && 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce' === $_GET['page']) {
                return;
            }

            $notice_data['vault_upgrade'] = array(
                'id' => 'ppcp_notice_vault_upgrade',
                'ans_company_logo' => PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/goopter-icon.png',
                'ans_message_title' => GOOPTER_PPCP_NAME . ' Now Supports Token Payments / Subscriptions!',
                'ans_message_description' => 'Maximize the power of '. GOOPTER_PPCP_NAME . ' in your WordPress store by enabling the Vault functionality. Unlock advanced features such as Subscriptions, One-Click Upsells, and more, for a seamless and streamlined payment experience. Upgrade your store today and take full advantage of the benefits offered by ' . GOOPTER_PPCP_NAME . '!',
                'ans_button_url' => admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp&move=tokenization_subscriptions'),
                'ans_button_label' => 'Enable PayPal Vault',
                'is_dismiss' => true
            );
            $notice_data['enable_apple_pay'] = array(
                'id' => 'ppcp_notice_apple_pay',
                'ans_company_logo' => PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/goopter-icon.png',
                'ans_message_title' => GOOPTER_PPCP_NAME . ' Now Supports Apple Pay!',
                'ans_message_description' => 'Unlock advanced features such as Apple Pay. Upgrade your store today and take full advantage of the benefits offered by' . GOOPTER_PPCP_NAME . '!',
                'ans_button_url' => admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp&move=additional_authorizations'),
                'ans_button_label' => 'Enable Apple Pay',
                'is_dismiss' => true
            );
            $notice_data['vault_upgrade_enable_apple_pay'] = array(
                'id' => 'ppcp_notice_vault_upgrade_apple_pay',
                'ans_company_logo' => PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/goopter-icon.png',
                'ans_message_title' => GOOPTER_PPCP_NAME . ' Now Supports Apple Pay and Token Payments / Subscriptions!',
                'ans_message_description' => 'Unlock advanced features such as Apple Pay, Subscriptions, One-Click Upsells, and more, for a seamless and streamlined payment experience. Upgrade your store today and take full advantage of the benefits offered by ' . GOOPTER_PPCP_NAME . '!',
                'ans_button_url' => admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp&move=tokenization_subscriptions'),
                'ans_button_label' => 'Activate These Features',
                'is_dismiss' => true
            );
            $notice_data['outside_us'] = array(
                'id' => 'ppcp_notice_outside_us',
                'ans_company_logo' => PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/goopter-icon.png',
                'ans_message_title' => '',
                'ans_message_description' => 'We notice that are running WooCommerce Subscriptions and your store country is outside the United States.<br>
                    Unfortunately, the '. GOOPTER_PPCP_NAME . ' Platform Vault functionality, which is required for Subscriptions, is only available for United States PayPal accounts.<br>
                    If your PayPal account is in fact based in the United States, you can continue with this update.<br>
                    However, if your PayPal account is not based in the U.S. you will need to wait until this feature is available in your country.<br>
                    Please submit a <a href="https://goopter.atlassian.net/servicedesk/customer/portal/1/group/1/create/1">help desk</a> ticket with any questions or concerns about this.',
                'is_dismiss' => true,
            );
            $result = $this->seller_onboarding->goopter_track_seller_onboarding_status_from_cache($this->merchant_id);
            $notice_data = json_decode(wp_json_encode($notice_data));
            $notice_type = goopter_ppcp_display_upgrade_notice_type($result);

            $goopter_ppcp_account_reconnect_notice = get_option('goopter_ppcp_account_reconnect_notice');
            // This is to ensure to display the notice only when goopter_ppcp (main gateway) is enabled.
            if (!empty($goopter_ppcp_account_reconnect_notice) && !empty($notice_type['active_ppcp_gateways']) && isset($notice_type['active_ppcp_gateways']['goopter_ppcp'])) {
                // This can be converted as a switch statement as the flag will tell use error reason
                $notice_data_account_reconnect = array(
                    'id' => 'ppcp_notice_account_reconnect',
                    'ans_company_logo' => PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/goopter-icon.png',
                    'ans_message_title' => 'Action Required: Reconnect Your PayPal Account',
                    'ans_message_description' => "We're experiencing permission issues preventing us from making certain PayPal API calls on your behalf. To fix this, please reconnect your PayPal account from the settings page. Click the button below to go to settings and select 'Reconnect PayPal Account'.",
                    'ans_button_url' => admin_url('options-general.php?page=goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                    'ans_button_label' => 'Settings',
                    'is_dismiss' => false
                );
                goopter_ppcp_display_notice(json_decode(wp_json_encode($notice_data_account_reconnect)));
            }

            if (!empty($notice_type)) {
                foreach ($notice_type as $key => $type) {
                    if ('outside_us' === $key && $type === true && isset($notice_data->$key)) {
                        goopter_ppcp_display_notice($notice_data->$key);
                    }
                }
            }
            if (isset($notice_type['vault_upgrade']) && $notice_type['vault_upgrade'] === true && isset($notice_type['enable_apple_pay']) && $notice_type['enable_apple_pay'] === true) {
                goopter_ppcp_display_notice($notice_data->vault_upgrade_enable_apple_pay);
            } elseif (isset($notice_type['vault_upgrade']) && $notice_type['vault_upgrade'] === true) {
                goopter_ppcp_display_notice($notice_data->vault_upgrade);
            } elseif (isset($notice_type['enable_apple_pay']) && $notice_type['enable_apple_pay'] === true) {
                goopter_ppcp_display_notice($notice_data->enable_apple_pay);
            }
        } catch (Exception $ex) {

        }
    }

    public function goopter_ppcp_order_action_list($order_id) {
        try {
            $order = wc_get_order($order_id);
            if (!is_a($order, 'WC_Order')) {
                echo esc_html(__('Error: Unable to detect the order, please refresh again to retry or Contact PayPal For WooCommerce support.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
                return;
            }
            $this->order = $order;
            $order_total_amount = floatval($order->get_total(''));
            $this->goopter_ppcp_order_status_data = array();
            $this->goopter_ppcp_order_actions = array();
            $paypal_order_id = goopter_ppcp_get_post_meta($order, '_paypal_order_id');
            if (empty($paypal_order_id)) {
                //echo __('PayPal order id does not exist for this order.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                return;
            }
            $this->payment_response = $this->payment_request->goopter_ppcp_get_paypal_order_details($paypal_order_id);

            if (isset($this->payment_response['name']) && $this->payment_response['name'] == 'RESOURCE_NOT_FOUND') {
                $auth_transaction_id = goopter_ppcp_get_post_meta($order, '_auth_transaction_id');
                // This condition is to fix the old orders where paypal_order_id has been replaced with authorization_id when capture was processed during order complete status change
                if (!empty($auth_transaction_id)) {
                    $auth_response = $this->payment_request->goopter_ppcp_get_authorized_payment($auth_transaction_id);
                    $paypal_txn_id = $auth_response['supplementary_data']['related_ids']['order_id'] ?? '';
                    if (!empty($paypal_txn_id)) {
                        $paypal_order_id = $paypal_txn_id;
                        $order->update_meta_data('_paypal_order_id', $paypal_txn_id);
                        $order->save();
                        $this->payment_response = $this->payment_request->goopter_ppcp_get_paypal_order_details($paypal_order_id);
                    }
                }
            }
            if (isset($this->payment_response) && !empty($this->payment_response) && isset($this->payment_response['intent']) && $this->payment_response['intent'] === 'AUTHORIZE') {
                if (isset($this->payment_response['purchase_units']['0']['payments']['authorizations']) && !empty($this->payment_response['purchase_units']['0']['payments']['authorizations'])) {
                    if (isset($this->payment_response['purchase_units']['0']['payments']['refunds'])) {
                        foreach ($this->payment_response['purchase_units']['0']['payments']['refunds'] as $key => $refunds) {
                            $this->currency_code = $refunds['amount']['currency_code'];
                            $line_item = array();
                            $line_item['transaction_id'] = isset($refunds['id']) ? $refunds['id'] : 'N/A';
                            $line_item['amount'] = isset($refunds['amount']['value']) ? wc_price($refunds['amount']['value'], array('currency' => $refunds['amount']['currency_code'])) : 'N/A';
                            $line_item['payment_status'] = isset($refunds['status']) ? ucwords(str_replace('_', ' ', strtolower($refunds['status']))) : 'N/A';
                            $line_item['expired_date'] = isset($refunds['expiration_time']) ? $refunds['expiration_time'] : 'N/A';
                            $line_item['payment_action'] = __('Refund', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                            $this->goopter_refund_amount = $this->goopter_refund_amount + $refunds['amount']['value'];
                        }
                    }
                    if (isset($this->payment_response['purchase_units']['0']['payments']['captures'])) {
                        foreach ($this->payment_response['purchase_units']['0']['payments']['captures'] as $key => $captures) {
                            $this->currency_code = $captures['amount']['currency_code'];
                            $line_item = array();
                            $line_item['transaction_id'] = isset($captures['id']) ? $captures['id'] : 'N/A';
                            $line_item['amount'] = isset($captures['amount']['value']) ? wc_price($captures['amount']['value'], array('currency' => $captures['amount']['currency_code'])) : 'N/A';
                            $line_item['payment_status'] = isset($captures['status']) ? ucwords(str_replace('_', ' ', strtolower($captures['status']))) : 'N/A';
                            $line_item['expired_date'] = isset($captures['expiration_time']) ? $captures['expiration_time'] : 'N/A';
                            $line_item['payment_action'] = __('Capture', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                            if ('COMPLETED' === $captures['status'] || 'PARTIALLY_REFUNDED' === $captures['status']) {
                                $this->goopter_ppcp_order_status_data['refund'][$line_item['transaction_id']] = $line_item['amount'];
                            }
                            $this->goopter_capture_amount = $this->goopter_capture_amount + $captures['amount']['value'];
                        }
                    }
                    if (isset($this->payment_response['purchase_units']['0']['payments']['authorizations'])) {
                        foreach ($this->payment_response['purchase_units']['0']['payments']['authorizations'] as $key => $authorizations) {
                            $this->currency_code = $authorizations['amount']['currency_code'];
                            $line_item = array();
                            $line_item['transaction_id'] = isset($authorizations['id']) ? $authorizations['id'] : 'N/A';
                            $line_item['amount'] = isset($authorizations['amount']['value']) ? wc_price($authorizations['amount']['value'], array('currency' => $authorizations['amount']['currency_code'])) : 'N/A';
                            $line_item['payment_status'] = isset($authorizations['status']) ? ucwords(str_replace('_', ' ', strtolower($authorizations['status']))) : 'N/A';
                            $line_item['expired_date'] = isset($authorizations['expiration_time']) ? $authorizations['expiration_time'] : 'N/A';
                            $line_item['payment_action'] = isset($this->payment_response['intent']) ? ucwords(str_replace('_', ' ', strtolower($this->payment_response['intent']))) : 'N/A';

                            $this->goopter_auth_amount = $this->goopter_auth_amount + $authorizations['amount']['value'];
                            $this->goopter_ppcp_order_status_data['capture'][$line_item['transaction_id']] = $line_item['amount'];
                            $this->goopter_ppcp_order_status_data['void'][$line_item['transaction_id']] = $line_item['amount'];
                        }
                    }
                    if (isset($this->payment_response['purchase_units']['0']['payments']['authorizations']['0']['status']) && 'CREATED' === $this->payment_response['purchase_units']['0']['payments']['authorizations']['0']['status']) {
                        $this->goopter_ppcp_order_actions['void'] = __('Void Authorization', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                        $this->goopter_ppcp_order_actions['capture'] = __('Capture Funds', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                    }
                    if (isset($this->payment_response['purchase_units']['0']['payments']['authorizations']['0']['status']) && 'PARTIALLY_CAPTURED' === $this->payment_response['purchase_units']['0']['payments']['authorizations']['0']['status']) {
                        if ($this->goopter_refund_amount < $this->goopter_capture_amount) {
                            $this->goopter_ppcp_order_actions['refund'] = __('Refund', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                            $this->goopter_ppcp_order_actions['void'] = __('Void Authorization', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                            $this->goopter_ppcp_order_actions['capture'] = __('Capture Funds', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                        }
                        if ($order_total_amount > $this->goopter_capture_amount) {
                            $this->goopter_ppcp_order_actions['capture'] = __('Capture Funds', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                        }
                    }
                    if (isset($this->payment_response['purchase_units']['0']['payments']['authorizations']['0']['status']) && 'CAPTURED' === $this->payment_response['purchase_units']['0']['payments']['authorizations']['0']['status']) {
                        if ($this->goopter_refund_amount < $this->goopter_capture_amount) {
                            $this->goopter_ppcp_order_actions['refund'] = __('Refund', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                        }
                    }
                    if (isset($this->payment_response['purchase_units']['0']['payments']['authorizations']['0']['status']) && 'VOIDED' === $this->payment_response['purchase_units']['0']['payments']['authorizations']['0']['status']) {
                        unset($this->goopter_ppcp_order_actions);
                    }
                }
            }
        } catch (Exception $ex) {

        }
    }

    public function goopter_ppcp_add_order_action_buttons($order) {
        try {
            $should_display_transaction_details = $this->goopter_ppcp_is_display_paypal_transaction_details($order->get_id(), ['authorize', 'capture']);

            // goopter_ppcp_order_actions variable will be only set when order is and Authorization order
            if ($should_display_transaction_details && !empty($this->goopter_ppcp_order_actions) && $this->is_auto_capture_auth === false) {
                wp_enqueue_script('goopter-ppcp-order-action');
                if ($this->goopter_capture_amount === 0) {
                    wp_register_style( 'hide-refund-button', false );
                    wp_enqueue_style( 'hide-refund-button' );
            
                    $css = '
                        .button.refund-items {
                            display: none !important;
                        }
                    ';
            
                    wp_add_inline_style( 'hide-refund-button', $css );
                } ?>
                <button type="button"
                        class="button goopter-ppcp-order-capture" <?php echo (isset($this->goopter_ppcp_order_actions['capture']) && !empty($this->goopter_ppcp_order_actions)) ? '' : 'disabled'; ?>> <?php esc_html_e('Capture', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?><?php echo wp_kses_post(wc_help_tip(__('Capture payment for the authorized order.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'))); ?></button>
                <button type="button"
                        class="button goopter-ppcp-order-void" <?php echo (isset($this->goopter_ppcp_order_actions['void']) && !empty($this->goopter_ppcp_order_actions)) ? '' : 'disabled'; ?>><?php esc_html_e('Void Authorization', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?><?php echo wp_kses_post(wc_help_tip(__('Void the authorized order to release the hold on the buyer\'s payment source.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'))); ?></button>
            <?php }
            ?>
            <?php
        } catch (Exception $ex) {

        }
    }

    public function goopter_ppcp_add_order_action_js() {
        wp_register_script('goopter-ppcp-order-action', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/js/wc-gateway-ppcp-admin-action.js', array('jquery'), VERSION_PFW, true);
    }

    public function goopter_ppcp_add_order_action_item_edit($order_id) {
        if ($this->goopter_ppcp_is_display_paypal_transaction_details($order_id) === false) {
            return;
        }
        $this->goopter_ppcp_order_action_list($order_id);
        $this->payment_request->goopter_ppcp_sync_ppcp_capture_details($order_id);
        $this->goopter_ppcp_order_meta_auth_capture_html();
    }

    public function goopter_ppcp_order_meta_auth_capture_html() {
        if (isset($this->goopter_ppcp_order_actions) && !empty($this->goopter_ppcp_order_actions)) {
            ?>
            <tr class="ppcp_auth_void_border" style="display: none;">
                <td colspan="3" style="border-top: 1px solid #dfdfdf;">&nbsp</td>
            </tr>
        <?php } ?>
        <?php if (isset($this->goopter_ppcp_order_status_data['capture']) && isset($this->goopter_ppcp_order_actions['capture'])) { ?>
            <tr class="goopter_ppcp_capture_box" style="display: none;">
                <td class="label"><?php echo esc_html(__('Additional Capture Possible', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')); ?></td>
                <td width="1%"></td>
                <td class="total">
                    <fieldset>
                        <label for="additional_capture_yes">
                            <input checked type="radio" name="additionalCapture" value="yes" id="additional_capture_yes">
                            Yes
                            <?php echo wp_kses_post(wc_help_tip(__('Yes (option to capture additional funds on this authorization if need)', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'))); ?>
                        </label>
                        <label for="additional_capture_no">
                            <input type="radio" name="additionalCapture" value="no" id="additional_capture_no">
                            No
                            <?php echo wp_kses_post(wc_help_tip(__('No (no additional capture needed; close authorization after this capture)', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'))); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr class="goopter_ppcp_capture_box" style="display: none;">
                <td class="label">
                    <label for="refund_amount">
                        <?php echo wp_kses_post(wc_help_tip(__('This will show the total amount to be captured/voided', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'))); ?>
                        <?php esc_html_e('Amount', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?>:
                    </label>
                </td>
                <td width="1%"></td>
                <td class="total">
                    <input readonly="readonly" type="text" id="ppcp_refund_amount" name="ppcp_refund_amount" style="width: 250px;" class="wc_input_price"/>

                </td>
            </tr>
            <tr class="goopter_ppcp_capture_box" style="display: none;">
                <td class="label"><?php
                    echo esc_html(__('Note To Buyer (Optional)', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
                    echo wp_kses_post(wc_help_tip(__('PayPal strongly recommends that you explain any unique circumstances (e.g. multiple captures, changes in item availability) to your buyer in detail below. Your buyer will see this note in the Transaction Details.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')));
                    ?></td>
                <td width="1%"></td>
                <td class="total">
                    <textarea maxlength="150" rows="2" cols="20" class="wide-input" type="textarea" name="goopter_ppcp_note_to_buyer_capture" id="goopter_ppcp_note_to_buyer_capture" style="width: 250px;"></textarea>
                </td>
            </tr>
        <?php } ?>
        <?php if (isset($this->goopter_ppcp_order_status_data['refund']) && isset($this->goopter_ppcp_order_actions['refund'])) { ?>
            <tr class="goopter_ppcp_refund_box" style="display: none;">
                <td class="label"><?php echo esc_html(__('Transaction Id', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')); ?></td>
                <td width="1%"></td>
                <td class="total">
                    <select name="goopter_ppcp_refund_data" id="goopter_ppcp_refund_data" style="width: 250px;">
                        <?php
                        $i = 0;
                        foreach ($this->goopter_ppcp_order_status_data['refund'] as $k => $v) :
                            if ($i == 0) {
                                echo "<option value=''>" . esc_html(__('Select Transaction Id', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')) . "</option>";
                            }
                            ?>
                            <option value="<?php echo esc_attr($k); ?>" ><?php echo esc_html($k) . ' - ' . esc_html($v); ?></option>
                            <?php
                            $i = $i + 1;
                        endforeach;
                        ?>
                    </select>
                </td>
            </tr>
            <tr class="goopter_ppcp_refund_box" style="display: none;">
                <td class="label"><?php echo esc_html(__('Refund Amount', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')); ?></td>
                <td width="1%"></td>
                <td class="total">
                    <fieldset>
                        <input type="text" placeholder="Enter amount" id="_regular_price" name="_goopter_ppcp_refund_price" class="short wc_input_price text-box" style="width: 250px;">
                    </fieldset>
                </td>
            </tr>
            <tr class="goopter_ppcp_refund_box" style="display: none;">
                <td class="label"><?php
                    echo esc_html(__('Note To Buyer (Optional)', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
                    echo wp_kses_post(wc_help_tip(__('PayPal strongly recommends that you explain any unique circumstances (e.g. multiple captures, changes in item availability) to your buyer in detail below. Your buyer will see this note in the Transaction Details.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')));
                    ?> </td>
                <td width="1%"></td>
                <td class="total">
                    <textarea maxlength="150" rows="4" cols="50" class="wide-input" type="textarea" name="goopter_ppcp_note_to_buyer_capture" id="goopter_ppcp_note_to_buyer_capture" style="width: 250px;"></textarea>
                </td>
            </tr>
            <?php
        }
        ?></div>
        <?php if (isset($this->goopter_ppcp_order_status_data['void']) && isset($this->goopter_ppcp_order_actions['void'])) { ?>
            <tr class="goopter_ppcp_void_box" style="display: none;">
                <td class="label">Note To Buyer (Optional)<?php echo wp_kses_post(wc_help_tip(esc_html__('PayPal strongly recommends that you explain any unique circumstances (e.g. multiple captures, changes in item availability) to your buyer in detail below. Your buyer will see this note in the Transaction Details.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'))); ?></td>
                <td width="1%"></td>
                <td class="total">
                    <textarea maxlength="150" rows="4" cols="50" class="wide-input" type="textarea" name="goopter_ppcp_note_to_buyer_void" id="goopter_ppcp_note_to_buyer_void" style="width: 250px;"></textarea>
                </td>
            </tr>
            <?php
        }
        ?>

        <?php
    }

    public function goopter_ppcp_capture_void_refund_submit() {
        ?><input type="hidden" value="no" name="is_ppcp_submited" id="is_ppcp_submited"><input type="hidden" name="order_metabox_goopter_ppcp_payment_action" id="order_metabox_goopter_ppcp_payment_action"><button type="button" class="button goopter-ppcp-order-action-submit button-primary"><?php esc_html_e('Submit', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></button><?php
    }

    public function goopter_ppcp_display_capture_details($item_id, $item, $product) {
        $ppcp_capture_details = wc_get_order_item_meta($item_id, '_ppcp_capture_details', true);
        if (empty($ppcp_capture_details)) {
            return;
        }
        $order_id = wc_get_order_id_by_order_item_id($item_id);
        $order = wc_get_order($order_id);
        $enviorment = goopter_ppcp_get_post_meta($order, '_enviorment', true);
        if ($enviorment === 'sandbox') {
            $this->view_transaction_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        } else {
            $this->view_transaction_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        }
        ?>
        <table cellspacing="0" class="display_meta">
            <?php
            foreach ($ppcp_capture_details as $index => $meta_array) :
                ?>
                <tr>
                    <td>
                        <?php
                        $ppcp_Capture_key_replace = array('_ppcp_transaction_id' => 'Transaction ID', '_ppcp_transaction_date' => 'Date', '_ppcp_transaction_amount' => 'Amount', 'total_refund_amount' => 'Total Refund Amount');
                        echo '<b>' . esc_html(__('Capture Details', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')) . '</b>: ';
                        $capture_details_html = '';
                        if (is_array($meta_array) && !empty($meta_array)) {
                            if (isset($meta_array['refund'])) {
                                $total_element = 4;
                            } else {
                                $total_element = 3;
                            }
                            $i = 1;
                            foreach ($meta_array as $key => $value) {
                                if (!is_array($value)) {
                                    if ($key === '_ppcp_transaction_date') {
                                        // Translators: %1$s is the date, and %2$s is the time.
                                        $capture_details_html .= esc_html(sprintf(__('%1$s at %2$s', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), date_i18n(wc_date_format(), strtotime($value)), date_i18n(wc_time_format(), strtotime($value))));
                                    } elseif ($key === '_ppcp_transaction_amount' || 'total_refund_amount' === $key) {
                                        $capture_details_html .= $ppcp_Capture_key_replace[$key] . ': ' . wc_price($value, array('currency' => $order->get_currency()));
                                    } elseif ($key === '_ppcp_transaction_id') {
                                        $return_url = sprintf($this->view_transaction_url, $value);
                                        $capture_details_html .= $ppcp_Capture_key_replace[$key] . ': ' . ' <a href="' . esc_url($return_url) . '" target="_blank">' . esc_html($value) . '</a>';
                                    } else {
                                        $capture_details_html .= $ppcp_Capture_key_replace[$key] . ': ' . $value;
                                    }
                                    if ($total_element !== $i) {
                                        $capture_details_html .= ' | ';
                                    }
                                    $i = $i + 1;
                                }
                            }
                        }
                        echo wp_kses_post($capture_details_html);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    public function goopter_ppcp_display_refund_details($item_id, $item, $product) {
        $ppcp_refund_details = wc_get_order_item_meta($item_id, '_ppcp_refund_details', true);
        if (empty($ppcp_refund_details)) {
            return;
        }
        $order_id = wc_get_order_id_by_order_item_id($item_id);
        $order = wc_get_order($order_id);
        $enviorment = goopter_ppcp_get_post_meta($order, '_enviorment', true);
        if ($enviorment === 'sandbox') {
            $this->view_transaction_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        } else {
            $this->view_transaction_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        }
        ?>
        <table cellspacing="0" class="display_meta">
            <?php
            foreach ($ppcp_refund_details as $index => $meta_array) :
                ?>
                <tr>
                    <td>
                        <?php
                        $ppcp_refund_key_replace = array('_ppcp_refund_id' => 'Refund ID', '_ppcp_refund_date' => 'Date', '_ppcp_refund_amount' => 'Amount');
                        echo '<b>' . esc_html(__('Refund Details', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')) . '</b>: ';
                        $refund_details_html = '';
                        if (is_array($meta_array) && !empty($meta_array)) {
                            $total_element = count($meta_array);
                            $i = 1;
                            foreach ($meta_array as $key => $value) {
                                if ('_ppcp_refund_amount' === $key) {
                                    $refund_details_html .= $ppcp_refund_key_replace[$key] . ': ' . wc_price($value, array('currency' => $order->get_currency()));
                                } elseif ('_ppcp_refund_date' === $key) {
                                    // Translators: %1$s is the date, and %2$s is the time.
                                    $refund_details_html .= wp_kses_post(sprintf(__('%1$s at %2$s', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), date_i18n(wc_date_format(), strtotime($value)), date_i18n(wc_time_format(), strtotime($value))));
                                } elseif ($key === '_ppcp_refund_id') {
                                    $return_url = sprintf($this->view_transaction_url, $value);
                                    $refund_details_html .= $ppcp_refund_key_replace[$key] . ':  <a href="' . esc_url($return_url) . '" target="_blank">' . esc_html($value) . '</a>';
                                } else {
                                    $refund_details_html .= $ppcp_refund_key_replace[$key] . ': ' . $value;
                                }
                                if ($total_element !== $i) {
                                    $refund_details_html .= ' | ';
                                }
                                $i = $i + 1;
                            }
                        }
                        echo wp_kses_post($refund_details_html);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    public function woocommerce_hidden_order_itemmeta($order_itemmeta) {
        $order_itemmeta = array_merge($order_itemmeta, array('_ppcp_refund_details', '_ppcp_capture_details'));
        return $order_itemmeta;
    }

    public function goopter_ppcp_display_total_capture($order_id) {
        $this->goopter_ppcp_order_action_list($order_id);
        if (!$this->goopter_ppcp_is_display_paypal_transaction_details($order_id)) {
            return;
        }
        if ($this->goopter_capture_amount === 0) {
            return;
        }
        $order = wc_get_order($order_id);
        $payment_method = $order->get_payment_method();
        $paymentaction = goopter_ppcp_get_post_meta($order, '_paymentaction');
        $payment_method = version_compare(WC_VERSION, '3.0', '<') ? $order->payment_method : $order->get_payment_method();
        $auto_capture_payment_support_gateways = ['goopter_ppcp', 'goopter_ppcp_google_pay', 'goopter_ppcp_apple_pay'];
        $auth_transaction_id = goopter_ppcp_get_post_meta($order, '_auth_transaction_id');
        if (!in_array($payment_method, $auto_capture_payment_support_gateways) && $paymentaction === 'authorize' && !empty($auth_transaction_id)) {
            return;
        }
        if ('on-hold' === $order->get_status()) {
            return false;
        }
        if ($order->get_status() == 'refunded') {
            return true;
        }
        ?>
        <tr>
            <td class="label">
                <?php esc_html_e('Total Capture:', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
                &nbsp;<?php echo wp_kses_post(wc_price($this->goopter_capture_amount, array('currency' => $this->currency_code))); ?>
            </td>
        </tr>
        <?php
    }

    public function goopter_ppcp_display_payment_authorization_notice() {
        global $post;
        $order = ( $post instanceof WP_Post ) ? wc_get_order( $post->ID ) : $post;
        if (!is_a($order, 'WC_Order')) {
            return;
        }
        if ('on-hold' != $order->get_status()) {
            return;
        }
        $screen = goopter_is_active_screen(goopter_get_shop_order_screen_id());
        if ($screen && $this->goopter_ppcp_is_display_paypal_transaction_details($order->get_id())) {
            echo wp_kses_post('<div class="updated woocommerce-message"><p>' . esc_html__('Capture the authorized order to receive funds in your PayPal account.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce') . '</p></div>');
        }
    }
    
    public function goopter_ppcp_vault_payment($order_id) {
        $this->payment_request->goopter_ppcp_capture_order_using_payment_method_token($order_id);
    }

    public function goopter_direct_pay_webhook_handler() {
        if (!$this->goopter_direct_pay_webhook_secret) {
            wp_send_json_error('Missing Webhook Secret in the setting', 403);
        }

        $raw_post_data = file_get_contents('php://input');

        // Get signature from request header
        $received_signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        // Calculate expected HMAC-SHA256 signature
        $expected_signature = hash_hmac('sha256', $raw_post_data, $this->goopter_direct_pay_webhook_secret);

        if (!hash_equals($expected_signature, $received_signature)) {
            // Invalid signature
            wp_send_json_error('Invalid Webhook Secret', 401);
            exit;
        }

        $data = json_decode($raw_post_data, true);
        
        $STATUS_CAPTURE_COMPLETED = 1;
        if ($data["event_type"] === "PAYMENT.CAPTURE.COMPLETED" && $data["status"] === $STATUS_CAPTURE_COMPLETED) {
            $order_id = $data["order_id"];
            $order = wc_get_order($order_id);
            $order->set_transaction_id($data["trx_id"]);
            $order->update_status('processing', "Payment received via Clover");
        }
        wp_send_json_success();
    }
}
