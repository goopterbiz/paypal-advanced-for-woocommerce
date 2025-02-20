<?php

class Goopter_Utility {

    public $plugin_name;
    public $version;
    public $payment_method;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->load_dependencies();
    }

    public function load_dependencies() {
        add_action('init', array($this, 'paypal_for_woocommerce_paypal_transaction_history'), 5);
        if (is_admin() && !defined('DOING_AJAX')) {
            add_filter('woocommerce_payment_gateway_supports', array($this, 'goopter_woocommerce_payment_gateway_supports'), 10, 3);
        }
        add_action('woocommerce_process_shop_order_meta', array($this, 'save'), 50, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'goopter_paypal_for_woocommerce_billing_agreement_details'), 10, 1);
    }

    // TODO check the usage of this function as its not changing anything with return value
    public function goopter_woocommerce_payment_gateway_supports($boolean, $feature, $current) {
        return $boolean;
    }

    public function paypal_for_woocommerce_paypal_transaction_history() {

        if (post_type_exists('paypal_transaction')) {
            return;
        }

        register_post_type('paypal_transaction', apply_filters('goopter_paypal_for_woocommerce_register_post_type_paypal_transaction_history', array(
            'labels' => array(
                'name' => __('PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'singular_name' => __('PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'menu_name' => _x('PayPal Transaction', 'Admin menu name', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'add_new' => __('Add PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'add_new_item' => __('Add New PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'edit' => __('Edit', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'edit_item' => __('View PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'new_item' => __('New PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'view' => __('View PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'view_item' => __('View PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'search_items' => __('Search PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'not_found' => __('No PayPal Transaction found', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'not_found_in_trash' => __('No PayPal Transaction found in trash', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'parent' => __('Parent PayPal Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')
            ),
            'description' => __('This is where you can add new PayPal Transaction to your store.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'public' => false,
            'query_var' => false,
        )));
    }

    public function save($post_id, $post_or_order_object) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by woocommerce hook: woocommerce_process_shop_order_meta
        if (!empty($_POST['save']) && $_POST['save'] == 'Submit') {
            $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
            if (!is_a($order, 'WC_Order')) {
                return;
            }
            if (goopter_is_active_screen(goopter_get_shop_order_screen_id())) {
                if (empty($this->payment_method)) {
                    $this->payment_method = $order->get_payment_method();
                }
                if (!empty($_POST['goopter_payment_action'])) {
                    $action = wc_clean(sanitize_text_field(wp_unslash($_POST['goopter_payment_action'])));
                    $hook_name = 'wc_' . $this->payment_method . '_' . strtolower($action);
                    if (!did_action('woocommerce_order_action_' . sanitize_title($hook_name))) {
                        do_action('woocommerce_order_action_' . sanitize_title($hook_name), $order);
                    }
                }
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing -- checked by woocommerce hook: woocommerce_process_shop_order_meta
    }

        public function goopter_paypal_for_woocommerce_billing_agreement_details($order) {
            if (!is_object($order)) {
                $order = wc_get_order($order);
            }


            $billing_agreement_id = $order->get_meta( 'BILLINGAGREEMENTID', true);
            if (empty($billing_agreement_id)) {
                return false;
            }
            ?>
        <h3>
            <?php esc_html_e( 'Billing Agreement Details', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce' ); ?>
        </h3>
        <p><?php echo esc_html( $billing_agreement_id ); ?></p> <?php
    }

    public static function goopter_set_address($new_order, $address, $type = 'billing') {
        if (!is_a($new_order, 'WC_Order')) {
            $new_order = wc_get_order($new_order);
        }
        if (!is_a($new_order, 'WC_Order')) {
            return;
        }
        foreach ($address as $key => $value) {
            if (is_callable(array($new_order, "set_{$type}_" . $key))) {
                $new_order->{"set_{$type}_" . $key}($value);
                $new_order->save();
            } else {
                $new_order->update_meta_data('_' . $key, $value);
                $new_order->save_meta_data();
            }
        }
    }

    public static function get_button_locale_code() {
        $_supportedLocale = array(
            'en_US', 'fr_XC', 'es_XC', 'zh_XC', 'en_AU', 'de_DE', 'nl_NL',
            'fr_FR', 'pt_BR', 'fr_CA', 'zh_CN', 'ru_RU', 'en_GB', 'zh_HK',
            'he_IL', 'it_IT', 'ja_JP', 'pl_PL', 'pt_PT', 'es_ES', 'sv_SE', 'zh_TW', 'tr_TR'
        );
        $wpml_locale = self::goopter_ec_get_wpml_locale();
        if ($wpml_locale) {
            if (in_array($wpml_locale, $_supportedLocale)) {
                return $wpml_locale;
            }
        }
        $locale = get_locale();
        if (get_locale() != '') {
            $locale = substr(get_locale(), 0, 5);
        }
        if (!in_array($locale, $_supportedLocale)) {
            $locale = 'en_US';
        }
        return $locale;
    }

    public static function goopter_ec_get_wpml_locale() {
        $locale = false;
        if (defined('ICL_LANGUAGE_CODE') && function_exists('icl_object_id')) {
            global $sitepress;
            if (isset($sitepress)) { // avoids a fatal error with Polylang
                $locale = $sitepress->get_current_language();
            } else if (function_exists('pll_current_language')) { // adds Polylang support
                $locale = pll_current_language('locale'); //current selected language requested on the broswer
            } else if (function_exists('pll_default_language')) {
                $locale = pll_default_language('locale'); //default lanuage of the blog
            }
        }
        return $locale;
    }
}
