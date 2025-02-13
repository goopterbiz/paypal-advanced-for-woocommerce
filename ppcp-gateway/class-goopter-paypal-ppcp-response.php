<?php

defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Response {

    public $api_log;
    public $setting_obj;
    public $generate_signup_link_default_request_param;
    protected static $_instance = null;
    public $is_sandbox;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->goopter_ppcp_load_class();
        $image_url = PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/images/company-logo.png';
        $this->generate_signup_link_default_request_param = array(
            'tracking_id' => '',
            'partner_config_override' => array(
                'partner_logo_url' => $image_url,
                'return_url' => '',
                'return_url_description' => '',
                'show_add_credit_card' => true,
            ),
            'products' => '',
            'legal_consents' => array(
                array(
                    'type' => 'SHARE_DATA_CONSENT',
                    'granted' => true,
                ),
            ),
            'operations' => array(
                array(
                    'operation' => 'API_INTEGRATION',
                    'api_integration_preference' => array(
                        'rest_api_integration' => array(
                            'integration_method' => 'PAYPAL',
                            'integration_type' => 'THIRD_PARTY',
                            'third_party_details' => array(
                                'features' => array(
                                    'PAYMENT',
                                    'FUTURE_PAYMENT',
                                    'REFUND',
                                    'ADVANCED_TRANSACTIONS_SEARCH',
                                    'ACCESS_MERCHANT_INFORMATION',
                                    'PARTNER_FEE'
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
        $this->is_sandbox = 'yes' === $this->setting_obj->get('testmode', 'no');
    }

    public function parse_response($paypal_api_response, $url, $request, $action_name) {

        try {
            if (is_wp_error($paypal_api_response)) {
                $response = array(
                    'status' => 'failed',
                    'body' => array('error_message' => $paypal_api_response->get_error_message(), 'error_code' => $paypal_api_response->get_error_code())
                );
            } else {
                $body = wp_remote_retrieve_body($paypal_api_response);
                $response = !empty($body) ? json_decode($body, true) : '';
                $response = isset($response['body']) ? $response['body'] : $response;
                $this->goopter_ppcp_write_log($url, $request, $paypal_api_response, $action_name);
                if (strpos($url, 'paypal.com') !== false) {
                    do_action('goopter_ppcp_request_respose_data', $request, $response, $action_name);
                }
                return $response;
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_write_log($url, $request, $response, $action_name = 'Exception') {
        global $wp_version;
        if(in_array($action_name, array('list_all_payment_tokens', 'get_order', 'get_capture'))) {
            return;
        }
        $environment = ($this->is_sandbox === true) ? 'SANDBOX' : 'LIVE';
        $this->api_log->log('PayPal Environment: ' . $environment);
        $this->api_log->log('WordPress Version: ' . $wp_version);
        $this->api_log->log('WooCommerce Version: ' . WC()->version);
        $this->api_log->log('PFW Version: ' . VERSION_PFW);
        $this->api_log->log('Action: ' . ucwords(str_replace('_', ' ', $action_name)));
        $this->api_log->log('Request URL: ' . $url);
        $response_body = isset($response['body']) ? json_decode($response['body'], true) : $response;
        if ($action_name === 'generate_signup_link') {
            $this->goopter_ppcp_signup_link_write_log($request);
        } elseif (!empty($request['body']) && is_array($request['body'])) {
            $this->api_log->log('Request Body: ' . wc_print_r($request['body'], true));
        } elseif (isset($request['body']) && !empty($request['body']) && is_string($request['body'])) {
            $this->api_log->log('Request Body: ' . wc_print_r(json_decode($request['body'], true), true));
        }
        if (!empty($response_body['requestId'])) {
            $this->api_log->log('Request ID: ' . wc_print_r($response_body['requestId'], true));
        }
        if (!empty($response_body['headers'])) {
            $this->api_log->log('Response Headers: ' . wc_print_r($response_body['headers'], true));
        }
        if (!empty($response_body['body']) && is_array($response_body['body'])) {
            $this->api_log->log('Response Body: ' . wc_print_r($response_body['body'], true));
        } elseif (is_array($response_body)) {
            $this->api_log->log('Response Body: ' . wc_print_r($response_body, true));
        } else {
            $this->api_log->log('Response Body: ' . wc_print_r(json_decode(wp_remote_retrieve_body($response_body), true), true));
        }
    }

    public function goopter_ppcp_load_class() {
        try {
            if (!class_exists('Goopter_PayPal_PPCP_Log')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-log.php';
            }
            if (!class_exists('Goopter_WC_Gateway_PPCP_Settings')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
            }
            $this->setting_obj = Goopter_WC_Gateway_PPCP_Settings::instance();
            $this->api_log = Goopter_PayPal_PPCP_Log::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_signup_link_write_log($request) {
        if (isset($request['body'])) {
            $data = json_decode($request['body'], true);
            if (isset($data['tracking_id'])) {
                $this->generate_signup_link_default_request_param['tracking_id'] = $data['tracking_id'];
            }
            if (isset($data['return_url'])) {
                $this->generate_signup_link_default_request_param['partner_config_override']['return_url'] = $data['return_url'];
            }
            if (isset($data['return_url'])) {
                $this->generate_signup_link_default_request_param['partner_config_override']['return_url_description'] = $data['return_url_description'];
            }
            if (isset($data['products'])) {
                $this->generate_signup_link_default_request_param['products'] = $data['products'];
            }
            if (isset($data['capabilities'])) {
                $this->generate_signup_link_default_request_param['capabilities'] = $data['capabilities'];
            }
            if (isset($data['third_party_features']) && !empty($data['third_party_features'])) {
                $this->generate_signup_link_default_request_param['operations'][0]['api_integration_preference']['rest_api_integration']['third_party_details']['features'] = array_merge($this->generate_signup_link_default_request_param['operations'][0]['api_integration_preference']['rest_api_integration']['third_party_details']['features'], $data['third_party_features']);
            }
            $this->api_log->log('Request Body: ' . wc_print_r($this->generate_signup_link_default_request_param, true));
        }
    }

}
