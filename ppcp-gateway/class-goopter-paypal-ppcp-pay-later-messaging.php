<?php

defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Pay_Later {
    use WC_PPCP_Pre_Orders_Trait;
    public $setting_obj;
    public $api_log;
    public $settings;
    public $minified_version;
    public $enable_tokenized_payments;
    protected static $_instance = null;
    public $title;
    public $enabled;
    public $is_sandbox;
    public $sandbox_client_id;
    public $sandbox_secret_id;
    public $live_client_id;
    public $live_secret_id;
    public $merchant_id;
    public $client_id;
    public $secret_id;
    public $enabled_pay_later_messaging;
    public $pay_later_messaging_page_type;
    public $pay_later_messaging_home_shortcode;
    public $pay_later_messaging_category_shortcode;
    public $pay_later_messaging_product_shortcode;
    public $pay_later_messaging_cart_shortcode;
    public $pay_later_messaging_payment_shortcode;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->goopter_ppcp_load_class();
        $this->goopter_ppcp_get_properties();
        $this->goopter_ppcp_pay_later_messaging_properties();
        $this->goopter_ppcp_add_hooks();
    }

    public function goopter_ppcp_load_class() {
        try {
            if (!class_exists('WC_Gateway_PPCP_Goopter_Settings')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Log')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-log.php';
            }
            $this->setting_obj = WC_Gateway_PPCP_Goopter_Settings::instance();
            $this->settings = $this->setting_obj->get_load();
            $this->api_log = Goopter_PayPal_PPCP_Log::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_get_properties() {
        $this->title = $this->setting_obj->get('title', AE_PPCP_NAME . ' - Built by Goopter');
        $this->enabled = 'yes' === $this->setting_obj->get('enabled', 'no');
        $this->is_sandbox = 'yes' === $this->setting_obj->get('testmode', 'no');
        $this->sandbox_client_id = $this->setting_obj->get('sandbox_client_id', '');
        $this->sandbox_secret_id = $this->setting_obj->get('sandbox_api_secret', '');
        $this->live_client_id = $this->setting_obj->get('api_client_id', '');
        $this->live_secret_id = $this->setting_obj->get('api_secret', '');
        if ($this->is_sandbox) {
            $this->client_id = $this->sandbox_client_id;
            $this->secret_id = $this->sandbox_secret_id;
            $this->merchant_id = $this->setting_obj->get('sandbox_merchant_id', '');
        } else {
            $this->client_id = $this->live_client_id;
            $this->secret_id = $this->live_secret_id;
            $this->merchant_id = $this->setting_obj->get('live_merchant_id', '');
        }
        $this->enable_tokenized_payments = 'yes' === $this->setting_obj->get('enable_tokenized_payments', 'no');
        $this->enabled_pay_later_messaging = 'yes' === $this->setting_obj->get('enabled_pay_later_messaging', 'yes');
        $this->pay_later_messaging_page_type = $this->setting_obj->get('pay_later_messaging_page_type', array('product', 'cart', 'payment'));
        if (empty($this->pay_later_messaging_page_type)) {
            $this->enabled_pay_later_messaging = false;
        }
        $this->minified_version = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '' : '.min';
    }

    public function goopter_ppcp_pay_later_messaging_properties() {
        if ($this->enabled_pay_later_messaging) {
            $this->pay_later_messaging_home_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_home_shortcode', 'no');
            $this->pay_later_messaging_category_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_category_shortcode', 'no');
            $this->pay_later_messaging_product_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_product_shortcode', 'no');
            $this->pay_later_messaging_cart_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_cart_shortcode', 'no');
            $this->pay_later_messaging_payment_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_payment_shortcode', 'no');
        }
    }

    public function goopter_ppcp_add_hooks() {
        if ($this->enabled_pay_later_messaging && $this->is_valid_for_use()) {
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'home') && $this->pay_later_messaging_home_shortcode === false) {
                add_filter('the_content', array($this, 'goopter_ppcp_pay_later_messaging_home_page_content'), 10);
                add_action('woocommerce_before_shop_loop', array($this, 'goopter_ppcp_pay_later_messaging_home_page'), 10);
            }
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'category') && $this->pay_later_messaging_category_shortcode === false) {
                add_action('woocommerce_before_shop_loop', array($this, 'goopter_ppcp_pay_later_messaging_category_page'), 10);
            }
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'product') && $this->pay_later_messaging_product_shortcode === false) {
                add_action('woocommerce_single_product_summary', array($this, 'goopter_ppcp_pay_later_messaging_product_page'), 11);
            }
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'cart') && $this->pay_later_messaging_cart_shortcode === false) {
                add_action('woocommerce_before_cart_table', array($this, 'goopter_ppcp_pay_later_messaging_cart_table'), 9);
                add_action('woocommerce_proceed_to_checkout', array($this, 'goopter_ppcp_pay_later_messaging_cart_page'), 10);
            }
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'payment') && $this->pay_later_messaging_payment_shortcode === false) {
                add_action('goopter_ppcp_display_paypal_button_checkout_page', array($this, 'goopter_ppcp_pay_later_messaging_payment_page'), 9);
            }
            add_shortcode('aepfw_bnpl_message', array($this, 'aepfw_bnpl_message_shortcode'), 10);
            add_action('woocommerce_review_order_before_submit', array($this, 'ppcp_payment_fields'));
        }
    }

    public function ppcp_payment_fields($bool = true) {
        if (apply_filters('woocommerce_checkout_show_terms', true) && function_exists('wc_terms_and_conditions_checkbox_enabled') && wc_terms_and_conditions_checkbox_enabled()) {
            echo '<div id="ppcp_payment_field_bottom">';
            $gateway = WC_Gateway_PPCP_Goopter::$_instance;
            if ($gateway->checkout_disable_smart_button === false) {
                do_action('goopter_ppcp_display_paypal_button_checkout_page');
            }
            echo '</div>';
        }
    }

    public function is_valid_for_use() {
        if ($this->enabled === false) {
            return false;
        }
        if (!empty($this->merchant_id) || (!empty($this->client_id) && !empty($this->secret_id))) {
            return true;
        }
        return false;
    }

    public function add_pay_later_script_in_frontend() {
        $script_versions = empty($this->minified_version) ? time() : VERSION_PFW;
        wp_register_script('goopter-pay-later-messaging', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/js/pay-later-messaging' . $this->minified_version . '.js', array('jquery', 'goopter-paypal-checkout-sdk'), $script_versions, true);

        $finalArray = [];
        $placements = [
            'home' => '.goopter_ppcp_message_home',
            'category' => '.goopter_ppcp_message_category',
            'cart' => '.goopter_ppcp_message_cart',
            'payment' => '.goopter_ppcp_message_payment',
            'product' => '.goopter_ppcp_message_product',
        ];
        foreach ($placements as $placement => $cssId) {
            $required_keys = array(
                'pay_later_messaging_' . $placement . '_layout_type' => 'text',
                'pay_later_messaging_' . $placement . '_text_layout_logo_type' => 'primary',
                'pay_later_messaging_' . $placement . '_text_layout_logo_position' => 'left',
                'pay_later_messaging_' . $placement . '_text_layout_text_size' => '12',
                'pay_later_messaging_' . $placement . '_text_layout_text_color' => 'black',
                'pay_later_messaging_' . $placement . '_flex_layout_color' => 'blue',
                'pay_later_messaging_' . $placement . '_flex_layout_ratio' => '8x1',
                'css_selector' => $cssId
            );
            if ($placement == 'home') {
                $required_keys['pay_later_messaging_' . $placement . '_layout_type'] = 'flex';
            }
            foreach ($required_keys as $key => $value) {
                $onlyKey = str_replace('pay_later_messaging_' . $placement . '_', '', $key);
                $finalArray[$placement][$onlyKey] = $this->settings[$key] ?? $value;
            }
        }
        if (wp_script_is('goopter-pay-later-messaging', 'enqueued')) {
            wp_localize_script('goopter-pay-later-messaging', 'goopter_pay_later_messaging', ['placements' => $finalArray, 'amount' => goopter_ppcp_number_format(goopter_ppcp_get_order_total()),
                'currencyCode' => goopter_ppcp_get_currency(),
                'currencySymbol' => get_woocommerce_currency_symbol(),
            ]);
        }
        goopter_ppcp_add_css_js();
    }

    public function goopter_ppcp_pay_later_messaging_home_page_content($content) {
        if($this->is_pre_order_item_in_cart()) {
            return false;
        }
        if (goopter_ppcp_is_cart_contains_subscription() !== true && (is_home() || is_front_page())) {
            $this->add_pay_later_script_in_frontend();
            return '<div class="goopter_ppcp_message_home"></div>' . $content;
        }
        return $content;
    }

    public function goopter_ppcp_pay_later_messaging_home_page() {
        if($this->is_pre_order_item_in_cart()) {
            return false;
        }
        if (goopter_ppcp_is_cart_contains_subscription() !== true && is_shop()) {
            $this->add_pay_later_script_in_frontend();
            echo '<div class="goopter_ppcp_message_home"></div>';
        }
        return false;
    }

    public function goopter_ppcp_pay_later_messaging_category_page() {
        if($this->is_pre_order_item_in_cart()) {
            return false;
        }
        if (goopter_ppcp_is_cart_contains_subscription() !== true && is_shop() === false && $this->pay_later_messaging_category_shortcode === false) {
            $this->add_pay_later_script_in_frontend();
            echo '<div class="goopter_ppcp_message_category"></div>';
        }
        return false;
    }

    public function goopter_ppcp_pay_later_messaging_product_page() {
        try {
            global $product;
            if($this->is_pre_order_product_charged_upon_release($product)) {
                return false;
            }
            if ($product->is_type(array('subscription', 'subscription_variation', 'variable-subscription'))) {
                return false;
            }
            if (goopter_ppcp_is_cart_contains_subscription() !== true && goopter_ppcp_is_product_purchasable($product, $this->enable_tokenized_payments) === true) {
                $this->add_pay_later_script_in_frontend();
                echo '<div class="goopter_ppcp_message_product"></div>';
            }
        } catch (Exception $ex) {

        }
        return false;
    }

    public function goopter_ppcp_pay_later_messaging_cart_table() {
        if($this->is_pre_order_item_in_cart()) {
            return false;
        }
        if (!WC()->cart->is_empty() && goopter_ppcp_is_cart_contains_subscription() !== true && WC()->cart->needs_payment()) {
            echo '<div class="goopter_ppcp_message_cart"></div>';
        }
        return false;
    }

    public function goopter_ppcp_pay_later_messaging_cart_page() {
        if($this->is_pre_order_item_in_cart()) {
            return false;
        }
        if (!WC()->cart->is_empty() && goopter_ppcp_is_cart_contains_subscription() !== true && WC()->cart->needs_payment()) {
            $this->add_pay_later_script_in_frontend();
            echo '<div class="goopter_ppcp_message_cart"></div>';
        }
        return false;
    }

    public function goopter_ppcp_pay_later_messaging_payment_page() {
        if($this->is_pre_order_item_in_cart()) {
            return false;
        }
        if (WC()->cart->is_empty() || goopter_ppcp_has_active_session() || goopter_ppcp_is_cart_contains_subscription() === true) {
            return false;
        }
        if(is_checkout()) {
            $this->add_pay_later_script_in_frontend();
            echo '<div class="goopter_ppcp_message_payment"></div>';
        }
    }

    public function is_paypal_pay_later_messaging_enable_for_page($page = '') {
        if($this->enabled_pay_later_messaging === false && $this->is_valid_for_use() === false) {
            return false;
        }
        if (empty($page)) {
            return false;
        }
        if (in_array($page, $this->pay_later_messaging_page_type)) {
            return true;
        }
        return false;
    }

    public function goopter_get_default_attribute_pay_later_messaging($placement = '') {
        if (!empty($placement)) {
            $enqueue_script_param = array();
            $enqueue_script_param['amount'] = goopter_ppcp_get_order_total();
            switch ($placement) {
                case 'home':
                    $required_keys = array(
                        'pay_later_messaging_home_layout_type' => 'flex',
                        'pay_later_messaging_home_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_home_text_layout_logo_position' => 'left',
                        'pay_later_messaging_home_text_layout_text_size' => '12',
                        'pay_later_messaging_home_text_layout_text_color' => 'black',
                        'pay_later_messaging_home_flex_layout_color' => 'blue',
                        'pay_later_messaging_home_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                case 'category':
                    $required_keys = array(
                        'pay_later_messaging_category_layout_type' => 'flex',
                        'pay_later_messaging_category_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_category_text_layout_logo_position' => 'left',
                        'pay_later_messaging_category_text_layout_text_size' => '12',
                        'pay_later_messaging_category_text_layout_text_color' => 'black',
                        'pay_later_messaging_category_flex_layout_color' => 'blue',
                        'pay_later_messaging_category_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                case 'product':
                    $required_keys = array(
                        'pay_later_messaging_product_layout_type' => 'text',
                        'pay_later_messaging_product_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_product_text_layout_logo_position' => 'left',
                        'pay_later_messaging_product_text_layout_text_size' => '12',
                        'pay_later_messaging_product_text_layout_text_color' => 'black',
                        'pay_later_messaging_product_flex_layout_color' => 'blue',
                        'pay_later_messaging_product_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                case 'cart':
                    $required_keys = array(
                        'pay_later_messaging_cart_layout_type' => 'text',
                        'pay_later_messaging_cart_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_cart_text_layout_logo_position' => 'left',
                        'pay_later_messaging_cart_text_layout_text_size' => '12',
                        'pay_later_messaging_cart_text_layout_text_color' => 'black',
                        'pay_later_messaging_cart_flex_layout_color' => 'blue',
                        'pay_later_messaging_cart_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                case 'payment':
                    $required_keys = array(
                        'pay_later_messaging_payment_layout_type' => 'text',
                        'pay_later_messaging_payment_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_payment_text_layout_logo_position' => 'left',
                        'pay_later_messaging_payment_text_layout_text_size' => '12',
                        'pay_later_messaging_payment_text_layout_text_color' => 'black',
                        'pay_later_messaging_payment_flex_layout_color' => 'blue',
                        'pay_later_messaging_payment_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                default:
                    break;
            }
        }
    }

    public function aepfw_bnpl_message_shortcode($atts) {
        if (empty($atts['placement'])) {
            return '';
        }
        if (!in_array($atts['placement'], array('home', 'category', 'product', 'cart', 'payment'))) {
            return;
        }
        if ($this->is_paypal_pay_later_messaging_enable_for_page($page = $atts['placement']) === false) {
            return false;
        }
        if ($this->is_paypal_pay_later_messaging_enable_for_shoerpage($page = $atts['placement']) === false) {
            return false;
        }
        $placement = $atts['placement'];
        if (!isset($atts['style'])) {
            $atts['style'] = $this->goopter_pay_later_messaging_get_default_value('style', $placement);
        }
        if ($atts['style'] === 'text') {
            $default_array = array(
                'placement' => 'home',
                'style' => $atts['style'],
                'logotype' => $this->goopter_pay_later_messaging_get_default_value('logotype', $placement),
                'logoposition' => $this->goopter_pay_later_messaging_get_default_value('logoposition', $placement),
                'textsize' => $this->goopter_pay_later_messaging_get_default_value('textsize', $placement),
                'textcolor' => $this->goopter_pay_later_messaging_get_default_value('textcolor', $placement),
            );
        } else {
            $default_array = array(
                'placement' => 'home',
                'style' => $atts['style'],
                'color' => $this->goopter_pay_later_messaging_get_default_value('color', $placement),
                'ratio' => $this->goopter_pay_later_messaging_get_default_value('ratio', $placement)
            );
        }
        $atts = array_merge(
                $default_array, (array) $atts
        );

        $finalParams = [
            'placement' => $atts['placement'],
            'layout_type' => $atts['style'],
            'text_layout_logo_type' => $atts['logotype'] ?? '',
            'text_layout_logo_position' => $atts['logoposition'] ?? '',
            'text_layout_text_size' => $atts['textsize'] ?? '',
            'text_layout_text_color' => $atts['textcolor'] ?? '',
            'flex_layout_color' => $atts['color'] ?? '',
            'flex_layout_ratio' => $atts['ratio'] ?? '',
            'css_selector' => '.goopter_ppcp_message_shortcode'
        ];

        $uniqueShortcodeKey = wp_unique_id('goopter_pay_later_messaging_');
        $this->add_pay_later_script_in_frontend();
        if (wp_script_is('goopter-pay-later-messaging', 'enqueued')) {
            wp_localize_script('goopter-pay-later-messaging', $uniqueShortcodeKey, $finalParams);
        }
        return '<div class="goopter_ppcp_message_shortcode" data-key="' . $uniqueShortcodeKey . '"></div>';
    }

    public function goopter_pay_later_messaging_get_default_value($key, $placement) {
        if (!empty($key) && !empty($placement)) {
            $param = $this->goopter_get_default_attribute_pay_later_messaging($placement);
            $map_keys = array('placement' => '', 'style' => 'pay_later_messaging_default_layout_type', 'logotype' => 'pay_later_messaging_default_text_layout_logo_type', 'logoposition' => 'pay_later_messaging_default_text_layout_logo_position', 'textsize' => 'pay_later_messaging_default_text_layout_text_size', 'textcolor' => 'pay_later_messaging_default_text_layout_text_color', 'color' => 'pay_later_messaging_default_flex_layout_color', 'ratio' => 'pay_later_messaging_default_flex_layout_ratio');
            if (!empty($map_keys[$key])) {
                $default_key = str_replace('default', $placement, $map_keys[$key]);
                if (!empty($param[$default_key])) {
                    return $param[$default_key];
                }
            }
            return '';
        }
    }

    public function is_paypal_pay_later_messaging_enable_for_shoerpage($page = '') {
        switch ($page) {
            case 'home':
                if ($this->pay_later_messaging_home_shortcode) {
                    return true;
                }
                break;
            case 'category':
                if ($this->pay_later_messaging_category_shortcode) {
                    return true;
                }
                break;
            case 'product':
                if ($this->pay_later_messaging_product_shortcode) {
                    return true;
                }
                break;
            case 'cart':
                if ($this->pay_later_messaging_cart_shortcode) {
                    return true;
                }
                break;
            case 'payment':
                if ($this->pay_later_messaging_payment_shortcode) {
                    return true;
                }
                break;
            default:
                break;
        }
        return false;
    }
}