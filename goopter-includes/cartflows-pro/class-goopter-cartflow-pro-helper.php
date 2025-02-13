<?php

if (!defined('ABSPATH')) {
    exit;
}

class Goopter_Cartflows_Pro_Helper {

    protected static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_filter('cartflows_offer_supported_payment_gateways', array($this, 'own_cartflows_offer_supported_payment_gateways'), 10, 1);
    }

    /**
     *
     * @param type $supported_gateways
     */
    public function own_cartflows_offer_supported_payment_gateways($supported_gateways) {
        $supported_gateways['goopter_ppcp'] = array(
            'file' => 'paypal-ppcp-goopter.php',
            'class' => 'Goopter_Cartflows_Pro_Gateway_PayPal_PPCP',
            'path' => PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/cartflow/class-cartflows-pro-gateway-paypal-ppcp-goopter.php'
        );
        $supported_gateways['goopter_ppcp_cc'] = array(
            'file' => 'paypal-ppcp-cc-goopter.php',
            'class' => 'Goopter_Cartflows_Pro_Gateway_PayPal_PPCP_CC',
            'path' => PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/cartflow/class-cartflows-pro-gateway-paypal-ppcp-goopter-cc.php'
        );
        return $supported_gateways;
    }
}

Goopter_Cartflows_Pro_Helper::instance();
