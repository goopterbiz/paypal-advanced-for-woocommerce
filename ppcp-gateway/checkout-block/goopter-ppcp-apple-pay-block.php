<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Goopter_Apple_Pay_Checkout_Block extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'goopter_ppcp_apple_pay';
    public $pay_later;
    public $version;

    public function initialize() {
        $this->version = VERSION_PFW;
        $this->settings = get_option('woocommerce_goopter_ppcp_settings', []);
        $this->gateway = new Goopter_WC_Gateway_Apple_Pay();
        if (!class_exists('Goopter_PayPal_PPCP_Pay_Later')) {
            include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-pay-later-messaging.php');
        }
        $this->pay_later = Goopter_PayPal_PPCP_Pay_Later::instance();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        wp_register_style('goopter_ppcp', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/css/wc-gateway-ppcp-goopter-public.css', array(), $this->version, 'all');
        wp_register_script('goopter_apple_pay-blocks-integration', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/checkout-block/ppcp-apple-pay.js', array('jquery', 'react', 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n', 'wp-polyfill', 'wp-element', 'wp-plugins'), VERSION_PFW, true);
        if (goopter_ppcp_has_active_session()) {
            $order_button_text = apply_filters('goopter_ppcp_order_review_page_place_order_button_text', __('Confirm Your Order', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
        } else {
            $order_button_text = 'Proceed with Apple Pay';
        }
        $is_paylater_enable_incart_page = 'no';
        if ($this->pay_later->is_paypal_pay_later_messaging_enable_for_page($page = 'cart') && $this->pay_later->pay_later_messaging_cart_shortcode === false) {
            $is_paylater_enable_incart_page = 'yes';
        } else {
            $is_paylater_enable_incart_page = 'no';
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

        wp_localize_script('goopter_apple_pay-blocks-integration', 'goopter_ppcp_apple_pay_manager_block', array(
            'placeOrderButtonLabel' => $order_button_text,
            'is_order_confirm_page' => (goopter_ppcp_has_active_session() === false) ? 'no' : 'yes',
            'is_paylater_enable_incart_page' => $is_paylater_enable_incart_page,
            'settins' => $this->settings,
            'page' => $page,
        ));
        
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('goopter_apple_pay-blocks-integration', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        }
        if (goopter_ppcp_has_active_session() === false && $page === 'cart') {
            do_action('goopter_ppcp_woo_cart_block_pay_later_message');
        }
        return ['goopter_apple_pay-blocks-integration'];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->get_setting('apple_pay_payments_title'),
            'description' => $this->get_setting('apple_pay_payments_description'),
            'supports' => $this->get_supported_features(),
            'icon' => $this->gateway->icon,
        ];
    }
}
