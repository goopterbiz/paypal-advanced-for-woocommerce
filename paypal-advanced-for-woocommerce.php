<?php
/**
 * @wordpress-plugin
 * Plugin Name:       PayPal Advanced for WooCommerce
 * Description:       Integrate the PayPal Complete Payments Platform into your WooCommerce site, offering PayPal Checkout, Pay Later, Venmo, direct credit card processing, and various alternative payment options such as Apple Pay, Google Pay, and others!
 * Version:           1.0.0
 * Author:            Goopter
 * Author URI:        https://www.goopter.com/
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       paypal-advanced-for-woocommerce
 * Domain Path:       /i18n/languages/
 * Requires at least: 5.8
 * Tested up to: 6.6.2
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0.0
 * WC tested up to: 9.3.2
 *
 * ************
 * Attribution
 * ************
 * PayPal for WooCommerce is a derivative work of the code from WooThemes / SkyVerge,
 * which is licensed with GPLv3. This code is also licensed under the terms
 * of the GNU Public License, version 3.
 */
if (!defined('ABSPATH')) {
    exit();
}

require_once('goopter-includes/goopter-functions.php');
require_once('goopter-includes/goopter-session-functions.php');

if (!class_exists('Goopter_Gateway_Paypal')) {

    class Goopter_Gateway_Paypal {

        protected $plugin_screen_hook_suffix = null;
        protected $plugin_slug = 'paypal-advanced-for-woocommerce';
        private $subscription_support_enabled = false;
        public $minified_version;
        public $customer_id = '';

        public function __construct() {
            $this->define_constants();
            $this->minified_version = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            $this->initialize_actions();
            $this->include_files_and_classes();

            add_filter('script_loader_tag', [$this, 'goopter_pfw_clean_script_tag'], 10000, 3);
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('goopter_ppcp' !== $handle) {
                    return $tag;
                }
                return str_replace(' src', ' async src', $tag);
            }, 10, 2);

            $this->initialize_other_actions_and_filters();

            $this->customer_id;
        }

        private function define_constants() {
            $constants = [
                'PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR' => dirname(__FILE__),
                'PAYPAL_FOR_WOOCOMMERCE_ASSET_URL' => plugin_dir_url(__FILE__),
                'VERSION_PFW' => '1.0.0',
                'PAYPAL_FOR_WOOCOMMERCE_PLUGIN_FILE' => __FILE__,
                'PAYPAL_FOR_WOOCOMMERCE_BASENAME' => plugin_basename(__FILE__),
                'PAYPAL_FOR_WOOCOMMERCE_DIR_PATH' => untrailingslashit(plugin_dir_path(__FILE__)),
                
                // Goopter
                'PAYPAL_PPCP_SANDBOX_PARTNER_MERCHANT_ID' => '58MRAGUA3QU7J',
                'PAYPAL_PPCP_PARTNER_MERCHANT_ID' => 'BNLF2FXLXTS6J',
                'PAYPAL_PPCP_SANDBOX_PARTNER_CLIENT_ID' => 'AUCjmZviwYLNMzMOXAxGgfxIB06HO4QaG4tGTiK7VjErSbGiJUcTqTNhvR3X0k58-ROEPj3PWGpBwNJ_',
                'PAYPAL_PPCP_PARTNER_CLIENT_ID' => 'ATYIBuWDPfFXuRoYNYx2spSQNyTOi0fm_zLo8G55Pe6oF5gLBKmOZJm7bpDTSlDXfsbiu8-qCd8nt1TY',

                // Goopter
                'PAYPAL_FOR_WOOCOMMERCE_PPCP_AWS_WEB_SERVICE' => 'https://api-dev.goopter.com/api/v8/ppcpRequest',
                'PAYPAL_FOR_WOOCOMMERCE_PPCP_GOOPTER_WEB_SERVICE' => 'https://api-dev.goopter.com/api/v8/ppcpRequest',
                
                'AE_FEE' => 'ae_p_f',
                'AE_PPCP_NAME' => 'PayPal Complete Payments',
                'AE_PPCP_CC' => 'Credit or Debit Card',
            ];

            foreach ($constants as $key => $value) {
                if (!defined($key))
                    define($key, $value);
            }
        }

        private function initialize_actions() {
            $basename = plugin_basename(__FILE__);
            $prefix = is_network_admin() ? 'network_admin_' : '';
            add_filter("{$prefix}plugin_action_links_$basename", array($this, 'plugin_action_links'), 10, 4);
            add_action('init', array($this, 'load_plugin_textdomain'));
            add_action('wp_loaded', array($this, 'load_cartflow_pro_plugin'), 20);
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 32);
            add_action('plugins_loaded', array($this, 'init'), 103);
            add_action('plugins_loaded', array($this, 'load_funnelkit_pro_plugin_compatible_gateways'), 5);
            add_action('admin_init', array($this, 'set_ignore_tag'));
            add_action('wp_enqueue_scripts', array($this, 'goopter_cc_ui_style'), 100);
            add_action('wp_ajax_goopter_dismiss_notice', array($this, 'goopter_dismiss_notice'), 10);
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('admin_print_styles', array($this, 'admin_styles'));
            add_action('admin_menu', array($this, 'goopter_admin_menu_own'));
            add_action('product_type_options', array($this, 'goopter_product_type_options_own'), 10, 1);
            add_action('woocommerce_process_product_meta', array($this, 'goopter_woocommerce_process_product_meta_own'), 10, 1);
            add_action('admin_enqueue_scripts', array($this, 'goopter_woocommerce_admin_enqueue_scripts'));
            add_action('wp_ajax_pfw_ed_shipping_bulk_tool', array($this, 'goopter_woocommerce_pfw_ed_shipping_bulk_tool'));
            add_action('http_api_curl', array($this, 'http_api_curl_ec_add_curl_parameter'), 10, 3);
            add_action('woocommerce_product_data_tabs', array($this, 'goopter_paypal_for_woo_woocommerce_product_data_tabs'), 99, 1);
            add_action('woocommerce_process_product_meta', array($this, 'goopter_paypal_for_woo_product_process_product_meta'));
            add_action('goopter_paypal_for_woocommerce_product_level_payment_action', array($this, 'goopter_paypal_for_woo_product_level_payment_action'), 10, 3);
            add_action('wp_head', array($this, 'paypal_for_woo_head_mark'), 1);
            add_action('init', array($this, 'goopter_register_post_status'), 99);
            add_action('current_screen', array($this, 'goopter_redirect_to_onboard'), 9);
        }

        private function include_files_and_classes() {
            if (!class_exists('Goopter_Utility')) {
                require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/goopter-includes/goopter-utility.php');
            }
            if (is_admin()) {
                include_once plugin_dir_path(__FILE__) . 'goopter-includes/goopter-admin-order-payment-process.php';
                new Goopter_Admin_Order_Payment_Process();
            }
            new Goopter_Utility($this->plugin_slug, VERSION_PFW);
        }

        private function initialize_other_actions_and_filters() {
            add_filter('woocommerce_get_checkout_order_received_url', array($this, 'goopter_woocommerce_get_checkout_order_received_url'), 10, 2);
            add_filter('wc_order_statuses', array($this, 'goopter_wc_order_statuses'), 10, 1);
            add_filter('woocommerce_email_classes', array($this, 'goopter_woocommerce_email_classes'), 10, 1);
            add_filter('woocommerce_email_actions', array($this, 'own_goopter_woocommerce_email_actions'), 10);
            add_filter('admin_body_class', array($this, 'goopter_include_admin_body_class'), 9999);
        }

        public function paypal_for_woo_head_mark() {
            $hide_watermark = get_option('pfw_hide_frontend_mark', 'no');
            if ($hide_watermark != 'yes') {
                echo sprintf(
                        '<!-- This site has installed %1$s %2$s - %3$s -->',
                        esc_html('PayPal for WooCommerce'),
                        ('v' . VERSION_PFW),
                        esc_url('https://www.goopter.com')
                );
                echo "\n\r";
            }
        }

        public function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
            global $woocommerce;
            $gateways = $woocommerce->payment_gateways->payment_gateways();
            $base_url = admin_url('options-general.php?page=paypal-advanced-for-woocommerce');
            $configure_url = $base_url;
            if (isset($gateways['goopter_ppcp']) && (
                    ($gateways['goopter_ppcp']->sandbox === true && $gateways['goopter_ppcp']->sandbox_merchant_id) ||
                    ($gateways['goopter_ppcp']->sandbox === false && $gateways['goopter_ppcp']->sandbox_merchant_id)
                    )) {
                $configure_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp');
            }
            $configure = sprintf('<a href="%s">%s</a>', $configure_url, __('Configure', 'paypal-advanced-for-woocommerce'));
            $custom_actions = array(
                'configure' => $configure,
                'contact' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://www.goopter.com/contact-us/', __('Contact', 'paypal-advanced-for-woocommerce')),
            );
            return array_merge($custom_actions, $actions);
        }

        function set_ignore_tag() {
            global $current_user;
            $plugin = plugin_basename(__FILE__);
            $plugin_data = get_plugin_data(__FILE__, false);
            if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !is_plugin_active_for_network('woocommerce/woocommerce.php')) {
                if (!empty($_GET['action']) && !in_array($_GET['action'], array('activate-plugin', 'upgrade-plugin', 'activate', 'do-plugin-upgrade')) && is_plugin_active($plugin)) {
                    deactivate_plugins($plugin);
                    wp_die("<strong>" . $plugin_data['Name'] . "</strong> requires <strong>WooCommerce</strong> plugin to work normally. Please activate it or install it from <a href=\"http://wordpress.org/plugins/woocommerce/\" target=\"_blank\">here</a>.<br /><br />Back to the WordPress <a href='" . get_admin_url(null, 'plugins.php') . "'>Plugins page</a>.");
                }
            }

            $user_id = $current_user->ID;

            /* If user clicks to ignore the notice, add that to their user meta */

            $notices = array('ignore_pp_ssl', 'ignore_pp_sandbox', 'ignore_pp_woo', 'ignore_pp_check', 'ignore_pp_donate', 'ignore_paypal_plus_move_notice', 'ignore_billing_agreement_notice', 'payflow_sb_autopopulate_new_credentials', 'agree_disgree_opt_in_logging');

            foreach ($notices as $notice) {
                if (isset($_GET[$notice]) && '0' == $_GET[$notice]) {
                    add_user_meta($user_id, $notice, 'true', true);
                    $set_ignore_tag_url = remove_query_arg($notice);
                    wp_redirect($set_ignore_tag_url);
                }
            }
        }

        public function init() {
            if (!class_exists("WC_Payment_Gateway"))
                return;

            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/ae-ppcp-constants.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/trait-goopter-ppcp-core.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/pre-order/trait-wc-ppcp-pre-orders.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/class-goopter-session-manager.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-base-goopter.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-seller-onboarding.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/goopter-paypal-ppcp-common-functions.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-smart-button.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-pay-later-messaging.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-admin-action.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-front-action.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-apple-pay-goopter.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-google-pay-goopter.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/lib/class-goopter-wordpress-custom-routes-handler.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/class-goopter-paypal-ppcp-apple-domain-validation.php');
            Goopter_PayPal_PPCP_Smart_Button::instance();
            Goopter_PayPal_PPCP_Seller_Onboarding::instance();
            Goopter_PayPal_PPCP_Pay_Later::instance();
            Goopter_PayPal_PPCP_Admin_Action::instance();
            Goopter_PayPal_PPCP_Front_Action::instance();
            add_filter('woocommerce_payment_gateways', array($this, 'goopter_add_paypal_pro_gateway'), 1000);
            Goopter_PayPal_PPCP_Apple_Domain_Validation::instance();
            Goopter_Session_Manager::instance();
            add_filter('woocommerce_payment_gateways', array($this, 'goopter_add_paypal_pro_gateway'), 3);
        }

        public function admin_scripts() {
            global $pos;
            if (!empty($post->ID)) {
                $payment_method = get_post_meta($post->ID, '_payment_method', true);
                $payment_action = get_post_meta($post->ID, '_payment_action', true);
            } else {
                $payment_method = '';
                $payment_action = '';
            }

            $dir = plugin_dir_path(__FILE__);
            wp_enqueue_media();
            wp_enqueue_script('jquery');
            $script_versions = empty($this->minified_version) ? time() : VERSION_PFW;
            wp_register_script('goopter_admin', plugins_url('/assets/js/goopter-admin-v2.js', __FILE__), array('jquery'), $script_versions);
            $translation_array = array(
                'is_ssl' => is_ssl() ? "yes" : "no",
                'choose_image' => __('Choose Image', 'paypal-advanced-for-woocommerce'),
                'payment_method' => $payment_method,
                'payment_action' => $payment_action,
                'is_paypal_credit_enable' => "yes",
                'locale' => (Goopter_Utility::get_button_locale_code() != '') ? Goopter_Utility::get_button_locale_code() : ''
            );
            wp_enqueue_script('goopter_admin');
            wp_localize_script('goopter_admin', 'goopter_admin', $translation_array);
        }

        public function admin_styles() {
            wp_enqueue_style('thickbox');
        }

        public function goopter_add_paypal_pro_gateway($methods) {
            if (class_exists('WC_Subscriptions') && function_exists('wcs_create_renewal_order')) {
                $this->subscription_support_enabled = true;
            }
            if (is_admin()) {
                if ($this->subscription_support_enabled) {
                    if ((isset($_GET['tab']) && 'checkout' === $_GET['tab']) && !isset($_GET['section'])) {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
                        $methods[] = 'WC_Gateway_PPCP_Goopter_Subscriptions';
                        $methods[] = 'WC_Gateway_PPCP_Goopter';
                    } else {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/wc-gateway-ppcp-goopter-subscriptions-base.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-apple-pay-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-google-pay-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-cc-goopter-subscriptions.php');
                        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
                            $methods[] = 'WC_Gateway_PPCP_Goopter_Apple_Pay_Subscriptions';
                            $methods[] = 'WC_Gateway_PPCP_Goopter_Google_Pay_Subscriptions';
                            $methods[] = 'WC_Gateway_CC_Goopter_Subscriptions';
                        }
                        $methods[] = 'WC_Gateway_PPCP_Goopter_Subscriptions';
                    }
                } else {
                    if ((isset($_GET['tab']) && 'checkout' === $_GET['tab']) && !isset($_GET['section'])) {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
                        $methods[] = 'WC_Gateway_PPCP_Goopter';
                    } else {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-apple-pay-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-google-pay-goopter.php');
                        $methods[] = 'WC_Gateway_PPCP_Goopter';
                        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
                            $methods[] = 'WC_Gateway_Apple_Pay_Goopter';
                            $methods[] = 'WC_Gateway_Google_Pay_Goopter';
                            $methods[] = 'WC_Gateway_CC_Goopter';
                        }
                    }
                }
            } else {
                if ($this->subscription_support_enabled) {
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/wc-gateway-ppcp-goopter-subscriptions-base.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-apple-pay-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-google-pay-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-cc-goopter-subscriptions.php');
                    $methods[] = 'WC_Gateway_PPCP_Goopter_Apple_Pay_Subscriptions';
                    $methods[] = 'WC_Gateway_PPCP_Goopter_Google_Pay_Subscriptions';
                    $methods[] = 'WC_Gateway_PPCP_Goopter_Subscriptions';
                    $methods[] = 'WC_Gateway_CC_Goopter_Subscriptions';
                } else {
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-apple-pay-goopter.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-google-pay-goopter.php');
                    $methods[] = 'WC_Gateway_Apple_Pay_Goopter';
                    $methods[] = 'WC_Gateway_Google_Pay_Goopter';
                    $methods[] = 'WC_Gateway_PPCP_Goopter';
                    $methods[] = 'WC_Gateway_CC_Goopter';
                }
            }
            return $methods;
        }
    
        public function goopter_admin_menu_own() {
            $this->plugin_screen_hook_suffix = add_submenu_page(
                    'options-general.php',
                    __('PayPal for WooCommerce - Settings', 'paypal-advanced-for-woocommerce'),
                    AE_PPCP_NAME,
                    'manage_options',
                    'paypal-advanced-for-woocommerce',
                    array($this, 'display_plugin_admin_page')
            );
        }

        public function display_plugin_admin_page() {
            $taxonomy = 'product_cat';
            $orderby = 'name';
            $show_count = 0;
            $pad_counts = 0;
            $hierarchical = 1;
            $title = '';
            $empty = 0;

            $args = array(
                'taxonomy' => $taxonomy,
                'orderby' => $orderby,
                'show_count' => $show_count,
                'pad_counts' => $pad_counts,
                'hierarchical' => $hierarchical,
                'title_li' => $title,
                'hide_empty' => $empty
            );

            $product_cats = get_categories($args);
            include_once('template/admin.php');
        }

        function goopter_product_type_options_own($product_type) {
            return $product_type;
        }

        function goopter_woocommerce_process_product_meta_own($post_id) {
            $no_shipping_required = isset($_POST['_no_shipping_required']) ? 'yes' : 'no';
            update_post_meta($post_id, '_no_shipping_required', $no_shipping_required);
            $_paypal_billing_agreement = isset($_POST['_paypal_billing_agreement']) ? 'yes' : 'no';
            update_post_meta($post_id, '_paypal_billing_agreement', $_paypal_billing_agreement);
            $_enable_sandbox_mode = isset($_POST['_enable_sandbox_mode']) ? 'yes' : 'no';
            update_post_meta($post_id, '_enable_sandbox_mode', $_enable_sandbox_mode);
            $_enable_ec_button = isset($_POST['_enable_ec_button']) ? 'yes' : 'no';
            update_post_meta($post_id, '_enable_ec_button', $_enable_ec_button);
        }

        public function goopter_woocommerce_admin_enqueue_scripts($hook) {
            wp_enqueue_style('ppe_cart', plugins_url('assets/css/admin.css', __FILE__), array(), VERSION_PFW);
        }

        public function goopter_woocommerce_pfw_ed_shipping_bulk_tool() {
            if (is_admin() && (defined('DOING_AJAX') || DOING_AJAX)) {
                global $wpdb;
                $processed_product_id = array();
                $errors = FALSE;
                $products = FALSE;
                $product_ids = FALSE;
                $update_count = 0;
                $where_args = array(
                    'post_type' => array('product', 'product_variation'),
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'fields' => 'id=>parent',
                );
                $where_args['meta_query'] = array();
                $pfw_bulk_action_type = (isset($_POST["actionType"])) ? sanitize_text_field(wp_unslash($_POST['actionType'])) : FALSE;
                $pfw_bulk_action_target_type = (isset($_POST["actionTargetType"])) ? sanitize_text_field(wp_unslash($_POST['actionTargetType'])) : FALSE;
                $pfw_bulk_action_target_where_type = (isset($_POST["actionTargetWhereType"])) ? sanitize_text_field(wp_unslash($_POST['actionTargetWhereType'])) : FALSE;
                $pfw_bulk_action_target_where_category = (isset($_POST["actionTargetWhereCategory"])) ? sanitize_text_field(wp_unslash($_POST['actionTargetWhereCategory'])) : FALSE;
                $pfw_bulk_action_target_where_product_type = (isset($_POST["actionTargetWhereProductType"])) ? sanitize_text_field(wp_unslash($_POST['actionTargetWhereProductType'])) : FALSE;
                $pfw_bulk_action_target_where_price_value = (isset($_POST["actionTargetWherePriceValue"])) ? sanitize_text_field(wp_unslash($_POST['actionTargetWherePriceValue'])) : FALSE;
                $pfw_bulk_action_target_where_stock_value = (isset($_POST["actionTargetWhereStockValue"])) ? sanitize_text_field(wp_unslash($_POST['actionTargetWhereStockValue'])) : FALSE;

                if (!$pfw_bulk_action_type || !$pfw_bulk_action_target_type) {
                    $errors = TRUE;
                }

                $is_enable_value = explode("_", $pfw_bulk_action_type);
                $is_enable = (isset($is_enable_value[0]) && $is_enable_value[0] == 'enable') ? 'yes' : 'no';

                if ($pfw_bulk_action_type == 'enable_no_shipping' || $pfw_bulk_action_type == 'disable_no_shipping') {
                    $action_key = "_no_shipping_required";
                } elseif ($pfw_bulk_action_type == 'enable_paypal_billing_agreement' || $pfw_bulk_action_type == 'disable_paypal_billing_agreement') {
                    $action_key = "_paypal_billing_agreement";
                } elseif ($pfw_bulk_action_type == 'enable_sandbox_mode' || $pfw_bulk_action_type == 'disable_sandbox_mode') {
                    $action_key = "_enable_sandbox_mode";
                } elseif ($pfw_bulk_action_type == 'enable_payment_action' || $pfw_bulk_action_type == 'disable_payment_action') {
                    $action_key = "enable_payment_action";
                }

                if ($pfw_bulk_action_target_type == 'all') {
                    $products = new WP_Query($where_args);
                } elseif ($pfw_bulk_action_target_type == 'featured') {
                    array_push($where_args['meta_query'], array(
                        'key' => '_featured',
                        'value' => 'yes'
                    ));
                    $products = new WP_Query($where_args);
                } elseif ($pfw_bulk_action_target_type == 'all_downloadable') {
                    array_push($where_args['meta_query'], array(
                        'key' => '_downloadable',
                        'value' => 'yes'
                    ));
                    $products = new WP_Query($where_args);
                } elseif ($pfw_bulk_action_target_type == 'all_virtual') {
                    array_push($where_args['meta_query'], array(
                        'key' => '_virtual',
                        'value' => 'yes'
                    ));
                    $products = new WP_Query($where_args);
                } elseif ($pfw_bulk_action_target_type == 'where' && $pfw_bulk_action_target_where_type) {
                    if ($pfw_bulk_action_target_where_type == 'category' && $pfw_bulk_action_target_where_category) {
                        $where_args['product_cat'] = $pfw_bulk_action_target_where_category;
                        $products = new WP_Query($where_args);
                    } elseif ($pfw_bulk_action_target_where_type == 'product_type' && $pfw_bulk_action_target_where_product_type) {
                        $where_args['product_type'] = $pfw_bulk_action_target_where_product_type;
                        $products = new WP_Query($where_args);
                    } elseif ($pfw_bulk_action_target_where_type == 'price_greater') {
                        array_push($where_args['meta_query'], array(
                            'key' => '_price',
                            'value' => str_replace(",", "", number_format($pfw_bulk_action_target_where_price_value, 2)),
                            'compare' => '>',
                            'type' => 'DECIMAL(10,2)'
                        ));
                        $products = new WP_Query($where_args);
                    } elseif ($pfw_bulk_action_target_where_type == 'price_less') {
                        array_push($where_args['meta_query'], array(
                            'key' => '_price',
                            'value' => str_replace(",", "", number_format($pfw_bulk_action_target_where_price_value, 2)),
                            'compare' => '<',
                            'type' => 'DECIMAL(10,2)'
                        ));
                        $products = new WP_Query($where_args);
                    } elseif ($pfw_bulk_action_target_where_type == 'stock_greater') {
                        array_push($where_args['meta_query'], array(
                            'key' => '_manage_stock',
                            'value' => 'yes'
                        ));
                        array_push($where_args['meta_query'], array(
                            'key' => '_stock',
                            'value' => str_replace(",", "", number_format($pfw_bulk_action_target_where_stock_value, 0)),
                            'compare' => '>',
                            'type' => 'NUMERIC'
                        ));
                        $products = new WP_Query($where_args);
                    } elseif ($pfw_bulk_action_target_where_type == 'stock_less') {
                        array_push($where_args['meta_query'], array(
                            'key' => '_manage_stock',
                            'value' => 'yes'
                        ));
                        array_push($where_args['meta_query'], array(
                            'key' => '_stock',
                            'value' => str_replace(",", "", number_format($pfw_bulk_action_target_where_stock_value, 0)),
                            'compare' => '<',
                            'type' => 'NUMERIC'
                        ));
                        $products = new WP_Query($where_args);
                    } elseif ($pfw_bulk_action_target_where_type == 'instock') {
                        array_push($where_args['meta_query'], array(
                            'key' => '_stock_status',
                            'value' => 'instock'
                        ));
                        $products = new WP_Query($where_args);
                    } elseif ($pfw_bulk_action_target_where_type == 'outofstock') {
                        array_push($where_args['meta_query'], array(
                            'key' => '_stock_status',
                            'value' => 'outofstock'
                        ));
                        $products = new WP_Query($where_args);
                    } elseif ($pfw_bulk_action_target_where_type == 'sold_individually') {
                        array_push($where_args['meta_query'], array(
                            'key' => '_sold_individually',
                            'value' => 'yes'
                        ));
                        $products = new WP_Query($where_args);
                    }
                } else {
                    $errors = TRUE;
                }

                if (!$errors && $products) {
                    if (count($products->posts) < 1) {
                        $errors = TRUE;
                        $update_count = 'zero';
                        $redirect_url = admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=general_settings&gateway=tool&processed=' . $update_count);
                        echo $redirect_url;
                    } else {
                        foreach ($products->posts as $target) {
                            $target_product_id = ($target->post_parent != '0') ? $target->post_parent : $target->ID;
                            if (get_post_type($target_product_id) == 'product' && !in_array($target_product_id, $processed_product_id)) {
                                if (!update_post_meta($target_product_id, $action_key, $is_enable)) {
                                    if (!empty($_POST['payment_action'])) {
                                        if (update_post_meta($target_product_id, 'woo_product_payment_action', wc_clean(sanitize_text_field(wp_unslash($_POST['payment_action']))))) {
                                            $processed_product_id[$target_product_id] = $target_product_id;
                                        }
                                        if ($_POST['payment_action'] == 'Authorization') {
                                            // if (update_post_meta($target_product_id, 'woo_product_payment_action_authorization', wc_clean(sanitize_text_field(wp_unslash($_POST['authorization_type']))))) {
                                            //     $processed_product_id[$target_product_id] = $target_product_id;
                                            // }
                                            $authorization_type = isset($_POST['authorization_type']) && !empty($_POST['authorization_type'])
                                                ? sanitize_text_field(wp_unslash($_POST['authorization_type']))
                                                : '';
                                            if (update_post_meta($target_product_id, 'woo_product_payment_action_authorization', wc_clean($authorization_type))) {
                                                $processed_product_id[$target_product_id] = $target_product_id;
                                            }
                                        } else {
                                            if (update_post_meta($target_product_id, 'woo_product_payment_action_authorization', '')) {
                                                $processed_product_id[$target_product_id] = $target_product_id;
                                            }
                                        }
                                    }
                                } else {
                                    if (!empty($_POST['payment_action'])) {
                                        if (update_post_meta($target_product_id, 'woo_product_payment_action', wc_clean(sanitize_text_field(wp_unslash($_POST['payment_action']))))) {
                                            $processed_product_id[$target_product_id] = $target_product_id;
                                        }
                                        if ($_POST['payment_action'] == 'Authorization') {
                                            // if (update_post_meta($target_product_id, 'woo_product_payment_action_authorization', wc_clean(sanitize_text_field(wp_unslash($_POST['authorization_type']))))) {
                                            //     $processed_product_id[$target_product_id] = $target_product_id;
                                            // }
                                            $authorization_type = isset($_POST['authorization_type']) && !empty($_POST['authorization_type']) 
                                                ? wc_clean(sanitize_text_field(wp_unslash($_POST['authorization_type']))) 
                                                : '';
                                            if (update_post_meta($target_product_id, 'woo_product_payment_action_authorization', $authorization_type)) {
                                                $processed_product_id[$target_product_id] = $target_product_id;
                                            }
                                        } else {
                                            if (update_post_meta($target_product_id, 'woo_product_payment_action_authorization', '')) {
                                                $processed_product_id[$target_product_id] = $target_product_id;
                                            }
                                        }
                                    }
                                    $processed_product_id[$target_product_id] = $target_product_id;
                                }
                            }
                        }
                        $update_count = count($processed_product_id);
                    }
                }

                if (!$errors) {
                    if ($update_count == 0) {
                        $update_count = 'zero';
                    }
                    $redirect_url = admin_url('options-general.php?page=paypal-advanced-for-woocommerce&tab=general_settings&gateway=tool&processed=' . $update_count);
                    echo $redirect_url;
                }
                die();
            }
        }

        public static function currency_has_decimals($currency) {
            if (in_array($currency, array('HUF', 'JPY', 'TWD'))) {
                return false;
            }
            return true;
        }

        public static function round($price, $order = null) {
            $precision = 2;
            if (is_object($order)) {
                $woocommerce_currency = version_compare(WC_VERSION, '3.0', '<') ? $order->get_order_currency() : $order->get_currency();
            } else {
                $woocommerce_currency = get_woocommerce_currency();
            }
            if (!self::currency_has_decimals($woocommerce_currency)) {
                $precision = 0;
            }
            return round($price, $precision);
        }

        public function http_api_curl_ec_add_curl_parameter($handle, $r, $url) {
            $Force_tls_one_point_two = get_option('Force_tls_one_point_two', 'no');
            if ((strstr($url, 'https://') && strstr($url, '.paypal.com')) && isset($Force_tls_one_point_two) && $Force_tls_one_point_two == 'yes') {
                curl_setopt($handle, CURLOPT_VERBOSE, 1);
                curl_setopt($handle, CURLOPT_SSLVERSION, 6);
            }
        }

        public static function number_format($price, $order = null) {
            $decimals = 2;
            if (is_object($order)) {
                $woocommerce_currency = $order->get_currency();
            } else {
                $woocommerce_currency = get_woocommerce_currency();
            }
            if (!self::currency_has_decimals($woocommerce_currency)) {
                $decimals = 0;
            }
            return number_format($price, $decimals, '.', '');
        }
        
        public function goopter_woocommerce_get_checkout_order_received_url($order_received_url, $order) {
            $lang_code = $order->get_meta('wpml_language', true);
            if (empty($lang_code)) {
                $lang_code = $order->get_meta('_wpml_language');
            }
            if (!empty($lang_code)) {
                $order_received_url = apply_filters('wpml_permalink', $order_received_url, $lang_code);
            }
            return $order_received_url;
        }

        public function load_plugin_textdomain() {
            load_plugin_textdomain('paypal-advanced-for-woocommerce', false, plugin_basename(dirname(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_FILE)) . '/i18n/languages');
        }

        public function goopter_dismiss_notice() {
            global $current_user;
            $user_id = $current_user->ID;
            if (!empty($_POST['action']) && $_POST['action'] == 'goopter_dismiss_notice') {
                $notices = array('ignore_pp_ssl', 'ignore_pp_sandbox', 'ignore_pp_woo', 'ignore_pp_check', 'ignore_pp_donate', 'ignore_paypal_plus_move_notice', 'ignore_billing_agreement_notice', 'ignore_token_multi_account', 'ignore_token_multi_account_payflow');
                foreach ($notices as $notice) {
                    if (!empty($_POST['data']) && $_POST['data'] == $notice) {
                        add_user_meta($user_id, $notice, 'true', true);
                        wp_send_json_success();
                    }
                }
                if (isset($_POST['data'])) {
                    add_user_meta($user_id, wc_clean(sanitize_text_field(wp_unslash($_POST['data']))), 'true', true);
                    wp_send_json_success();
                }
            }
        }

        public function goopter_paypal_for_woo_woocommerce_product_data_tabs($product_data_tabs) {
            global $woocommerce;
            $gateways = $woocommerce->payment_gateways->payment_gateways();
            if (isset($gateways['goopter_ppcp']) && 'yes' === $gateways['goopter_ppcp']->enabled) {
                $product_data_tabs['goopter_paypal_for_woo_payment_action'] = array(
                    'label' => __('Payment Action', 'paypal-advanced-for-woocommerce'),
                    'target' => 'goopter_paypal_for_woo_payment_action',
                );
            }
            return $product_data_tabs;
        }

        public function goopter_paypal_for_woo_product_process_product_meta($post_id) {
            if (isset($_REQUEST['enable_payment_action']) && ('yes' === $_REQUEST['enable_payment_action'])) {
                update_post_meta($post_id, 'enable_payment_action', 'yes');
            } else {
                update_post_meta($post_id, 'enable_payment_action', '');
            }
            $woo_product_payment_action = !empty($_POST['woo_product_payment_action']) ? wc_clean(sanitize_text_field(wp_unslash($_POST['woo_product_payment_action']))) : '';
            update_post_meta($post_id, 'woo_product_payment_action', $woo_product_payment_action);
            if (!empty($woo_product_payment_action) && 'Authorization' == $woo_product_payment_action) {
                $woo_product_payment_action_authorization = !empty($_POST['woo_product_payment_action_authorization']) ? wc_clean(sanitize_text_field(wp_unslash($_POST['woo_product_payment_action_authorization']))) : '';
                update_post_meta($post_id, 'woo_product_payment_action_authorization', $woo_product_payment_action_authorization);
            } else {
                update_post_meta($post_id, 'woo_product_payment_action_authorization', '');
            }
        }

        public function goopter_paypal_for_woo_product_level_payment_action($gateways, $request = null, $order_id = null) {
            if (is_null(WC()->cart)) {
                return true;
            }

            $payment_action = array();
            if (WC()->cart->is_empty()) {
                return true;
            } else {
                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                    $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
                    $is_enable_payment_action = get_post_meta($product_id, 'enable_payment_action', true);
                    if ($is_enable_payment_action == 'yes') {
                        $woo_product_payment_action = get_post_meta($product_id, 'woo_product_payment_action', true);
                        if (!empty($woo_product_payment_action)) {
                            $payment_action[$woo_product_payment_action] = $woo_product_payment_action;
                            $woo_product_payment_action_authorization = get_post_meta($product_id, 'woo_product_payment_action_authorization', true);
                            if (!empty($woo_product_payment_action_authorization)) {
                                $payment_action[$woo_product_payment_action] = $woo_product_payment_action_authorization;
                            }
                        }
                    }
                }
                if (empty($payment_action)) {
                    return;
                }
            }
        }

        public function load_cartflow_pro_plugin() {
            if (defined('CARTFLOWS_PRO_FILE')) {
                include_once plugin_dir_path(__FILE__) . 'goopter-includes/cartflows-pro/class-goopter-cartflow-pro-helper.php';
            }
        }

        public function goopter_cc_ui_style() {
            wp_register_style('goopter-cc-ui', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/css/goopter-cc-ui.css', array(), VERSION_PFW);
        }

        public function goopter_pfw_clean_script_tag($tag, $handle, $src) {
            if (in_array($handle, ['jquery', 'wp-i18n', 'wp-hooks'])) {
                $tag = str_replace(['defer="defer"', "defer='defer'", " defer", " async"], '', $tag);
            }
            return $tag;
        }

        public function goopter_wc_order_statuses($order_statuses) {
            $order_statuses['wc-partial-payment'] = _x('Partially Paid', 'Order status', 'paypal-advanced-for-woocommerce');
            return $order_statuses;
        }

        public function goopter_register_post_status() {
            register_post_status('wc-partial-payment', array(
                'label' => _x('Partially Paid', 'Order status', 'paypal-advanced-for-woocommerce'),
                'public' => false,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                // Translators: %s is the count of partially paid items.
                'label_count' => _n_noop('Partially Paid <span class="count">(%s)</span>', 'Partially Paid <span class="count">(%s)</span>', 'paypal-advanced-for-woocommerce'),
            ));
        }

        public function goopter_woocommerce_email_classes($emails) {
            $emails['WC_Email_Partially_Paid_Order'] = include PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/classes/wc-email-customer-partial-paid-order.php';
            $emails['WC_Email_Admin_Partially_Paid_Order'] = include PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/classes/wc-email-new-partial-paid-order.php';
            return $emails;
        }

        public function own_goopter_woocommerce_email_actions($actions) {
            $actions[] = 'woocommerce_order_status_cancelled_to_partial-payment';
            $actions[] = 'woocommerce_order_status_failed_to_partial-payment';
            $actions[] = 'woocommerce_order_status_on-hold_to_partial-payment';
            $actions[] = 'woocommerce_order_status_pending_to_partial-payment';
            $actions[] = 'woocommerce_order_status_processing_to_partial-payment';
            return $actions;
        }

        public function goopter_include_admin_body_class($classes) {
            try {
                global $post;
                if (!isset($post->post_type)) {
                    return $classes;
                }
                $order = ($post instanceof WP_Post) ? wc_get_order($post->ID) : $post;
                if (!is_a($order, 'WC_Order')) {
                    return $classes;
                }
                if (ae_is_active_screen(ae_get_shop_order_screen_id())) {
                    $order = wc_get_order(absint($post->ID));
                    $payment_method = $order->get_payment_method();
                    if (!empty($payment_method)) {
                        $classes .= ' goopter_' . $payment_method;
                    }
                }
                return $classes;
            } catch (Exception $ex) {
                return $classes;
            }
        }

        public function goopter_redirect_to_onboard() {
            $woocommerce_goopter_ppcp_settings = get_option('woocommerce_goopter_ppcp_settings', false);
            $displayed_goopter_onboard_screen = get_option('displayed_goopter_onboard_screen', false);
            if ($woocommerce_goopter_ppcp_settings === false && $displayed_goopter_onboard_screen === false) {
                update_option('displayed_goopter_onboard_screen', 'yes');
                wp_safe_redirect(admin_url('options-general.php?page=paypal-advanced-for-woocommerce&tab=general_settings&gateway=paypal_payment_gateway_products'));
                exit;
            }
        }

        public function load_funnelkit_pro_plugin_compatible_gateways() {
            try {
                // Check for Funnel Builder Pro Plugin Activation
                if (defined('WFFN_PRO_FILE')) { 
                    require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/funnelkit/class-wfocu-paypal-for-wc-gateway-goopter-ppcp.php';
                    require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/funnelkit/class-wfocu-paypal-for-wc-gateway-goopter-ppcp-cc.php';
                }
                // Check for Upstroke Plugin Activation and the Upsell Functionality
                if (class_exists('WC_Subscriptions') && function_exists('wcs_create_renewal_order')) {
                    require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/funnelkit/class-upstroke-subscriptions-goopter-ppcp.php';
                    require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/funnelkit/class-upstroke-subscriptions-goopter-ppcp-cc.php';
                }
            } catch (Exception $ex) {
                // Handle exception if any of the checks fail
            }
        }

        public function add_meta_boxes() {
            $screen = ae_get_shop_order_screen_id();
            if (ae_is_active_screen($screen)) {
                require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/admin/class-wc-meta-box-order-items-ppcp.php';
                remove_meta_box('woocommerce-order-items', $screen, 'normal');
                add_meta_box('woocommerce-order-items', __('Items', 'woocommerce'), 'Custom_WC_Meta_Box_Order_Items::output', $screen, 'normal', 'high');
            }
        }
    }

}

new Goopter_Gateway_Paypal();

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('woocommerce_blocks_loaded', function () {
    try {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }
        require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/checkout-block/goopter-ppcp-checkout-block.php');
        require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/checkout-block/goopter-ppcp-cc-block.php');
        add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new Goopter_PPCP_Checkout_Block);
                    $payment_method_registry->register(new Goopter_PPCP_CC_Block);
                }
        );
    } catch (Exception $ex) {
        
    }
});