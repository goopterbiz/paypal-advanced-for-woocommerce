<?php

defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Vault_Sync {

    protected static $_instance = null;
    public $payment_request;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->goopter_ppcp_load_class();
    }

    public function goopter_ppcp_wc_get_customer_saved_methods_list() {
        $saved_methods = wc_get_customer_saved_methods_list(get_current_user_id());
        return $saved_methods;
    }

    public function goopter_ppcp_load_class() {
        if (!class_exists('Goopter_PayPal_PPCP_Payment')) {
            include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-payment.php');
        }
        $this->payment_request = Goopter_PayPal_PPCP_Payment::instance();
    }
}
