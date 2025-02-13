<?php

defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Seller_Onboarding {

    public $dcc_applies;
    public $ppcp_host;
    public $testmode;
    public $setting_obj;
    public $host;
    public $partner_merchant_id;
    public $sandbox_partner_merchant_id;
    public $api_request;
    public $result;
    protected static $_instance = null;
    public $api_log;
    public $is_sandbox;
    public $ppcp_migration;
    public $ppcp_paypal_country;
    public $subscription_support_enabled;
    public $is_vaulting_enable;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        try {
            $this->ppcp_host = PAYPAL_FOR_WOOCOMMERCE_PPCP_GOOPTER_WEB_SERVICE;
            $this->goopter_ppcp_load_class();
            $this->sandbox_partner_merchant_id = PAYPAL_PPCP_SANDBOX_PARTNER_MERCHANT_ID;
            $this->partner_merchant_id = PAYPAL_PPCP_PARTNER_MERCHANT_ID;
            //add_action('wc_ajax_ppcp_login_seller', array($this, 'goopter_ppcp_login_seller'));
            add_action('admin_init', array($this, 'goopter_ppcp_listen_for_merchant_id'));
            $this->ppcp_paypal_country = $this->dcc_applies->country();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function setTestMode($testMode = 'no') {
        $this->is_sandbox = $testMode === 'yes';
    }

    public function goopter_ppcp_load_class() {
        try {
            if (!class_exists('Goopter_PayPal_PPCP_DCC_Validate')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-dcc-validate.php');
            }
            if (!class_exists('Goopter_WC_Gateway_PPCP_Settings')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Request')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-request.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Log')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-log.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Apple_Pay_Configurations')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/admin/class-goopter-paypal-ppcp-apple-pay-configurations.php';
            }
            $this->api_log = Goopter_PayPal_PPCP_Log::instance();
            $this->setting_obj = Goopter_WC_Gateway_PPCP_Settings::instance();
            $this->dcc_applies = Goopter_PayPal_PPCP_DCC_Validate::instance();
            $this->api_request = Goopter_PayPal_PPCP_Request::instance();
            Goopter_PayPal_PPCP_Apple_Pay_Configurations::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function nonce() {
        return 'a1233wtergfsdt4365tzrshgfbaewa36AGa1233wtergfsdt4365tzrshgfbaewa36AG';
    }

    public function goopter_generate_signup_link($testmode, $page) {
        $this->is_sandbox = ( $testmode === 'yes' ) ? true : false;
        $body = $this->default_data();
        if ($page === 'gateway_settings') {
            $body['return_url'] = add_query_arg(array('place' => 'gateway_settings', 'utm_nooverride' => '1'), untrailingslashit($body['return_url']));
        } else {
            $body['return_url'] = add_query_arg(array('place' => 'admin_settings_onboarding', 'utm_nooverride' => '1'), untrailingslashit($body['return_url']));
        }
        if ($this->is_sandbox) {
            $tracking_id = goopter_key_generator();
            $body['tracking_id'] = $tracking_id;
            update_option('goopter_ppcp_sandbox_tracking_id', $tracking_id);
        } else {
            $tracking_id = goopter_key_generator();
            $body['tracking_id'] = $tracking_id;
            update_option('goopter_ppcp_live_tracking_id', $tracking_id);
        }
        // $host_url = $this->ppcp_host . 'generate-signup-link';
        // Goopter
        $body['action_name'] = 'generate_signup_link';
        $host_url = $this->ppcp_host;
        $args = array(
            'method' => 'POST',
            'body' => wp_json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
        );
        return $this->api_request->request($host_url, $args, 'generate_signup_link');
    }

    public function goopter_generate_signup_link_with_feature($testmode, $page, $body) {
        $this->is_sandbox = ( $testmode === 'yes' ) ? true : false;
        if ($page === 'gateway_settings') {
            $body['return_url'] = add_query_arg(array('place' => 'gateway_settings', 'utm_nooverride' => '1'), untrailingslashit($body['return_url']));
        } else {
            $body['return_url'] = add_query_arg(array('place' => 'admin_settings_onboarding', 'utm_nooverride' => '1'), untrailingslashit($body['return_url']));
        }
        if ($this->is_sandbox) {
            $tracking_id = goopter_key_generator();
            $body['tracking_id'] = $tracking_id;
            update_option('goopter_ppcp_sandbox_tracking_id', $tracking_id);
        } else {
            $tracking_id = goopter_key_generator();
            $body['tracking_id'] = $tracking_id;
            update_option('goopter_ppcp_live_tracking_id', $tracking_id);
        }
        // $host_url = $this->ppcp_host . 'generate-signup-link';
        // Goopter
        $body['action_name'] = 'generate_signup_link';
        $host_url = $this->ppcp_host;
        $args = array(
            'method' => 'POST',
            'body' => wp_json_encode($body),
            'headers' => array('Content-Type' => 'application/json'),
        );
        return $this->api_request->request($host_url, $args, 'generate_signup_link');
    }

    private function default_data() {
        $testmode = ($this->is_sandbox) ? 'yes' : 'no';
        $default_data = array(
            'testmode' => $testmode,
            'return_url' => admin_url(
                    'admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp&testmode=' . $testmode
            ),
            'return_url_description' => __(
                    'Return to your shop.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
            ),
            'products' => array(
                $this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT'
        ));
        $country = $this->dcc_applies->country();
        if (!empty($country)) {
            if (in_array($this->dcc_applies->country(), $this->dcc_applies->apple_google_vault_supported_country)) {
                $default_data['capabilities'] = array(
                    'PAYPAL_WALLET_VAULTING_ADVANCED',
                    'GOOGLE_PAY',
                    'APPLE_PAY'
                );
                $default_data['third_party_features'] = array('VAULT', 'BILLING_AGREEMENT');
                $default_data['products'][] = 'ADVANCED_VAULTING';
                $default_data['products'][] = 'PAYMENT_METHODS';
            }
        }
        return $default_data;
    }

    public function ppcp_apple_pay_data() {
        $testmode = ($this->is_sandbox) ? 'yes' : 'no';
        return array(
            'testmode' => $testmode,
            'return_url' => admin_url(
                    'admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp&feature_activated=applepay&testmode=' . $testmode
            ),
            'return_url_description' => __(
                    'Return to your shop.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
            ),
            'capabilities' => array(
                'APPLE_PAY'
            ),
            'third_party_features' => array('VAULT', 'BILLING_AGREEMENT'),
            'products' => array(
                $this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT',
                'PAYMENT_METHODS'
        ));
    }

    public function ppcp_google_pay_data() {
        $testmode = ($this->is_sandbox) ? 'yes' : 'no';
        return array(
            'testmode' => $testmode,
            'return_url' => admin_url(
                    'admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp&feature_activated=googlepay&testmode=' . $testmode
            ),
            'return_url_description' => __(
                    'Return to your shop.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
            ),
            'capabilities' => array(
                'GOOGLE_PAY'
            ),
            'third_party_features' => array('VAULT', 'BILLING_AGREEMENT'),
            'products' => array(
                $this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT',
                'PAYMENT_METHODS'
        ));
    }

    public function ppcp_vault_data() {
        $testmode = ($this->is_sandbox) ? 'yes' : 'no';
        return array(
            'testmode' => $testmode,
            'return_url' => admin_url(
                    'admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp&testmode=' . $testmode
            ),
            'return_url_description' => __(
                    'Return to your shop.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
            ),
            'capabilities' => array(
                'PAYPAL_WALLET_VAULTING_ADVANCED'
            ),
            'third_party_features' => array('VAULT', 'BILLING_AGREEMENT'),
            'products' => array(
                $this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT',
                'ADVANCED_VAULTING'
        ));
    }

    public function goopter_ppcp_login_seller() {
        try {
            $posted_raw = goopter_ppcp_get_raw_data();
            $this->api_log->log('goopter_ppcp_login_seller', 'error');
            $this->api_log->log(wp_json_encode($posted_raw, true), 'error');
            if (empty($posted_raw)) {
                return false;
            }
            $data = json_decode($posted_raw, true);
            $this->goopter_ppcp_get_credentials($data);
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_get_credentials($data) {
        try {
            $this->is_sandbox = isset($data['env']) && 'sandbox' === $data['env'];
            $this->host = ($this->is_sandbox) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            $this->setting_obj->set('testmode', ($this->is_sandbox) ? 'yes' : 'no');
            $this->setting_obj->persist();
            if ($this->is_sandbox) {
                $this->setting_obj->set('enabled', 'yes');
            } else {
                $this->setting_obj->set('enabled', 'yes');
            }
            $this->setting_obj->persist();
            if ($this->is_sandbox) {
                set_transient('goopter_ppcp_sandbox_seller_onboarding_process_done', 'yes', 29000);
            } else {
                set_transient('goopter_ppcp_live_seller_onboarding_process_done', 'yes', 29000);
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_listen_for_merchant_id() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- External callback by paypal, nonce not applicable
        try {
            $this->is_sandbox = false;
            if (!$this->is_valid_site_request()) {
                return;
            }
            if (!isset($_GET['merchantIdInPayPal'])) {
                return;
            }
            if (!isset($_GET['testmode'])) {
                return;
            }
            if (isset($_GET['post_id'])) {
                return;
            }
            if (isset($_GET['testmode']) && 'yes' === $_GET['testmode']) {
                $this->is_sandbox = true;
            }
            $this->setting_obj->set('enabled', 'yes');
            $this->setting_obj->set('testmode', ($this->is_sandbox) ? 'yes' : 'no');
            $this->host = ($this->is_sandbox) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            $merchant_id = sanitize_text_field(wp_unslash($_GET['merchantIdInPayPal']));
            if (isset($_GET['merchantId'])) {
                $merchant_email = sanitize_text_field(wp_unslash($_GET['merchantId']));
            } else {
                $merchant_email = '';
            }

            // Delete the transient so that system fetches the latest status after connecting the account
            delete_transient('goopter_seller_onboarding_status');
            delete_option('goopter_ppcp_account_reconnect_notice');

            $move_to_location = 'tokenization_subscriptions';
            if (isset($_GET['feature_activated'])) {
                switch ($_GET['feature_activated']) {
                    case 'applepay':
                        set_transient('goopter_ppcp_applepay_onboarding_done', 'yes', 29000);
                        delete_transient('goopter_apple_pay_domain_list_cache');
                        $move_to_location = 'apple_pay_authorizations';
                        break;
                    case 'googlepay':
                        set_transient('goopter_ppcp_googlepay_onboarding_done', 'yes', 29000);
                        $move_to_location = 'google_pay_authorizations';
                        break;
                }
            }

            if ($this->is_sandbox) {
                $this->setting_obj->set('sandbox_merchant_id', $merchant_id);
                set_transient('goopter_ppcp_sandbox_seller_onboarding_process_done', 'yes', 29000);
                $this->api_log->log("sandbox_merchant_id: " . $merchant_id, 'error');
            } else {
                $this->setting_obj->set('live_merchant_id', $merchant_id);
                set_transient('goopter_ppcp_live_seller_onboarding_process_done', 'yes', 29000);
            }
            $this->setting_obj->set('enabled', 'yes');
            $this->setting_obj->persist();
            if (isset($_GET['place']) && $_GET['place'] === 'gateway_settings') {
                $redirect_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp&move=' . $move_to_location);
            } else {
                $redirect_url = admin_url('options-general.php?page=goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
            }
            unset($_GET);
            wp_safe_redirect($redirect_url, 302);
            exit();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    public function goopter_track_seller_onboarding_status_from_cache($merchant_id, $force_refresh = false) {
        $seller_onboarding_status_transient = false;
        if (!$force_refresh) {
            $seller_onboarding_status_transient = get_transient('goopter_seller_onboarding_status');
        }
        if (!$seller_onboarding_status_transient) {
            $this->is_sandbox = 'yes' === $this->setting_obj->get('testmode', 'no');
            $this->host = ($this->is_sandbox) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            if ($this->is_sandbox) {
                $partner_merchant_id = $this->sandbox_partner_merchant_id;
            } else {
                $partner_merchant_id = $this->partner_merchant_id;
            }
            try {
                $this->api_request = new Goopter_PayPal_PPCP_Request();
                $url = trailingslashit($this->host) .
                        'v1/customer/partners/' . $partner_merchant_id .
                        '/merchant-integrations/' . $merchant_id;
                $args = array(
                    'method' => 'GET',
                    'headers' => array(
                        'Authorization' => '',
                        'Content-Type' => 'application/json',
                    ),
                );
                
                if (!empty($merchant_id)) {
                    $this->result = $this->api_request->request($url, $args, 'seller_onboarding_status');
                    $seller_onboarding_status_transient = $this->result;
                } else {
                    $seller_onboarding_status_transient = [];
                }
            } catch (Exception $ex) {
                $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
                $this->api_log->log($ex->getMessage(), 'error');
                $seller_onboarding_status_transient = [];
            }
        }
        set_transient('goopter_seller_onboarding_status', $seller_onboarding_status_transient, DAY_IN_SECONDS);
        return $seller_onboarding_status_transient;
    }

    public function goopter_track_seller_onboarding_status($merchant_id) {
        return $this->goopter_track_seller_onboarding_status_from_cache($merchant_id, true);
    }

    public function is_valid_site_request() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- not related with security
        if (!isset($_REQUEST['section']) || !in_array(sanitize_text_field(wp_unslash($_REQUEST['section'])), array('goopter_ppcp'), true)) {
            return false;
        }
        if (!current_user_can('manage_options')) {
            return false;
        }
        return true;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended -- not related with security
    }

    public function goopter_is_apple_pay_approved($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products'])) {
            foreach ($result['products'] as $product) {
                if (isset($product['vetting_status']) && ('SUBSCRIBED' === $product['vetting_status'] || 'APPROVED' === $product['vetting_status']) && isset($product['capabilities']) && is_array($product['capabilities']) && in_array('APPLE_PAY', $product['capabilities'])) {
                    foreach ($result['capabilities'] as $key => $capabilities) {
                        if (isset($capabilities['name']) && 'APPLE_PAY' === $capabilities['name'] && 'ACTIVE' === $capabilities['status']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function goopter_is_google_pay_approved($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products'])) {
            foreach ($result['products'] as $key => $product) {
                if (isset($product['vetting_status']) && ('SUBSCRIBED' === $product['vetting_status'] || 'APPROVED' === $product['vetting_status']) && isset($product['capabilities']) && is_array($product['capabilities']) && in_array('GOOGLE_PAY', $product['capabilities'])) {
                    foreach ($result['capabilities'] as $capabilities) {
                        if (isset($capabilities['name']) && 'GOOGLE_PAY' === $capabilities['name'] && 'ACTIVE' === $capabilities['status']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    public function goopter_ppcp_is_fee_enable($response) {
        try {
            if (!empty($response)) {
                if (isset($response['oauth_integrations']['0']['integration_type']) && 'OAUTH_THIRD_PARTY' === $response['oauth_integrations']['0']['integration_type']) {
                    if (isset($response['oauth_integrations']['0']['oauth_third_party']['0']['scopes']) && is_array($response['oauth_integrations']['0']['oauth_third_party']['0']['scopes'])) {
                        foreach ($response['oauth_integrations']['0']['oauth_third_party']['0']['scopes'] as $key => $scope) {
                            if (strpos($scope, 'payments/partnerfee') !== false) {
                                return true;
                            }
                        }
                    }
                }
            }
            return false;
        } catch (Exception $ex) {

        }
    }
}
