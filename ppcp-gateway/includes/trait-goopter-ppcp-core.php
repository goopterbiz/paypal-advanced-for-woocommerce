<?php

trait Goopter_PPCP_Core
{
    public ?Goopter_WC_Gateway_PPCP_Settings $setting_obj;
    public ?Goopter_PayPal_PPCP_Log $api_log;
    public ?Goopter_PayPal_PPCP_Request $api_request;
    public ?Goopter_PayPal_PPCP_DCC_Validate $dcc_applies;
    public ?Goopter_PayPal_PPCP_Payment $payment_request;
    public ?Goopter_WC_PayPal_PPCP_Payment_Token $ppcp_payment_token;
    public $setting_obj_fields;
    public static $_instance;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function goopter_ppcp_load_class($loadSettingsFields = false) {
        try {
            if (!class_exists('Goopter_WC_Gateway_PPCP_Settings')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Log')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-log.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Request')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-request.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_DCC_Validate')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-dcc-validate.php');
            }
            if (!class_exists('Goopter_PayPal_PPCP_Payment')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-payment.php');
            }
            if (!class_exists('Goopter_WC_PayPal_PPCP_Payment_Token')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/ppcp-payment-token/class-goopter-paypal-ppcp-payment-token.php';
            }
            $this->setting_obj = Goopter_WC_Gateway_PPCP_Settings::instance();
            $this->api_log = Goopter_PayPal_PPCP_Log::instance();
            $this->api_request = Goopter_PayPal_PPCP_Request::instance();
            $this->dcc_applies = Goopter_PayPal_PPCP_DCC_Validate::instance();
            $this->payment_request = Goopter_PayPal_PPCP_Payment::instance();
            $this->ppcp_payment_token = Goopter_WC_PayPal_PPCP_Payment_Token::instance();
            if ($loadSettingsFields) {
                $this->setting_obj_fields = $this->setting_obj->goopter_ppcp_setting_fields();
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function isSandbox(): bool
    {
        return 'yes' === $this->setting_obj->get('testmode', 'no');
    }
}
