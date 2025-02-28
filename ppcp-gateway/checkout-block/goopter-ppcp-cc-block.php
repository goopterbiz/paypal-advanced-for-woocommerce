<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Goopter_PPCP_CC_Block extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'goopter_ppcp_cc';
    public $pay_later;
    public $version;

    public function initialize() {
        $this->version = VERSION_PFW;
        $this->settings = get_option('woocommerce_goopter_ppcp_settings', []);
        $this->gateway = new Goopter_WC_Gateway_CC();
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
        wp_register_script('goopter_ppcp_cc-blocks-integration', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/checkout-block/ppcp-cc.js', array(), VERSION_PFW, true);
        if (goopter_ppcp_has_active_session()) {
            $order_button_text = apply_filters('goopter_ppcp_cc_order_review_page_place_order_button_text', __('Confirm Your PayPal Order', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
        } else {
            $order_button_text = 'Pay with Card';
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
        wp_localize_script('goopter_ppcp_cc-blocks-integration', 'goopter_ppcp_cc_manager_block', array(
            'placeOrderButtonLabel' => $order_button_text,
            'is_order_confirm_page' => (goopter_ppcp_has_active_session() === false) ? 'no' : 'yes',
            'is_paylater_enable_incart_page' => $is_paylater_enable_incart_page,
            'settins' => $this->settings,
            'page' => $page
        ));
        
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('goopter_ppcp_cc-blocks-integration', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        }
        wp_enqueue_script('goopter_ppcp_cc');
        if (goopter_ppcp_has_active_session() === false && $page === 'cart') {
            do_action('goopter_ppcp_cc_woo_cart_block_pay_later_message');
        }
        return ['goopter_ppcp_cc-blocks-integration'];
    }

    public function get_payment_method_data() {
        return [
            'cc_title' => $this->get_setting('advanced_card_payments_title'),
            'description' => $this->get_setting('description'),
            'supports' => $this->get_supported_features(),
            'icons' => $this->gateway->get_block_icon(),
            'icon_path' => PAYPAL_FOR_WOOCOMMERCE_ASSET_URL 
        ];
    }
}
