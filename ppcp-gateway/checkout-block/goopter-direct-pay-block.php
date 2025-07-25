<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Goopter_Direct_Pay_Block extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'goopter_direct_pay';
    public $version;

    public function initialize() {
        $this->version = VERSION_PFW;
        $this->settings = get_option('woocommerce_goopter_ppcp_settings', []);
        $this->gateway = new Goopter_WC_Gateway_Direct_Pay();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        wp_register_style('goopter_ppcp', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/css/wc-gateway-ppcp-goopter-public.css', array(), $this->version, 'all');
        wp_register_script('goopter_direct_pay-blocks-integration', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/checkout-block/goopter-direct-pay.js', array('wp-element', 'wp-plugins', 'wc-blocks-checkout', 'wc-blocks-registry', 'wp-hooks', 'wp-i18n', 'wc-settings', 'wp-html-entities', 'jquery'), VERSION_PFW, true);
        if (goopter_ppcp_has_active_session()) {
            $order_button_text = apply_filters('goopter_ppcp_order_review_page_place_order_button_text', __('Confirm Your Order', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
        } else {
            $order_button_text = 'Pay with Clover';
        }
        $page = '';
        $is_pay_page = '';
        if (is_product()) {
            $page = 'product';
        } else if (is_cart()) {
            $page = 'cart';
        } elseif (is_checkout_pay_page()) {
            $page = 'checkout';
            $is_pay_page = 'yes';
        } elseif (is_checkout()) {
            $page = 'checkout';
        }
        wp_localize_script('goopter_direct_pay-blocks-integration', 'goopter_direct_pay_manager_block', array(
            'placeOrderButtonLabel' => $order_button_text,
            'is_order_confirm_page' => (goopter_ppcp_has_active_session() === false) ? 'no' : 'yes',
            'is_paylater_enable_incart_page' => 'no',
            'settins' => $this->settings,
            'page' => $page
        ));
        
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('goopter_direct_pay-blocks-integration', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        }
        return ['goopter_direct_pay-blocks-integration'];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->get_setting('goopter_direct_pay_title'),
            'description' => $this->get_setting('goopter_direct_pay_description'),
            'supports' => $this->get_supported_features(),
            'icon' => $this->gateway->icon,
        ];
    }
}
