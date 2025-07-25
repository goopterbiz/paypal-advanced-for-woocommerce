<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Goopter advanced integration for PayPal Complete Payments and for WooCommerce
 * Description:       Integrate the PayPal Complete Payments Platform into your WooCommerce site, offering PayPal Checkout, Pay Later, Venmo, direct credit card processing, and various alternative payment options such as Apple Pay, Google Pay, and others!
 * Version:           1.0.9
 * Author:            Goopter
 * Author URI:        https://www.goopter.com/
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce
 * Domain Path:       /i18n/languages/
 * Requires at least: 5.8
 * Tested up to: 6.7.1
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
        protected $plugin_slug = 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce';
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
                
                // The following keys are used to identify merchants onboarded with Goopter.  
                // For details, refer to: https://developer.paypal.com/api/rest/requests/#paypal-auth-assertion  
                'PAYPAL_PPCP_SANDBOX_PARTNER_MERCHANT_ID' => '58MRAGUA3QU7J',
                'PAYPAL_PPCP_PARTNER_MERCHANT_ID' => 'BNLF2FXLXTS6J',
                'PAYPAL_PPCP_SANDBOX_PARTNER_CLIENT_ID' => 'AUCjmZviwYLNMzMOXAxGgfxIB06HO4QaG4tGTiK7VjErSbGiJUcTqTNhvR3X0k58-ROEPj3PWGpBwNJ_',
                'PAYPAL_PPCP_PARTNER_CLIENT_ID' => 'ATYIBuWDPfFXuRoYNYx2spSQNyTOi0fm_zLo8G55Pe6oF5gLBKmOZJm7bpDTSlDXfsbiu8-qCd8nt1TY',

                // Goopter
                'PAYPAL_FOR_WOOCOMMERCE_PPCP_GOOPTER_WEB_SERVICE' => 'https://api.goopter.com/api/v8/ppcpRequest',
                
                'GOOPTER_FEE' => 'goopter_p_f',
                'GOOPTER_PPCP_NAME' => 'PayPal Complete Payments',
                'GOOPTER_PPCP_CC' => 'Credit or Debit Card',
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
            add_action('http_api_curl', array($this, 'http_api_curl_ec_add_curl_parameter'), 10, 3);
            add_action('woocommerce_product_data_tabs', array($this, 'goopter_paypal_for_woo_woocommerce_product_data_tabs'), 99, 1);
            add_action('woocommerce_process_product_meta', array($this, 'goopter_paypal_for_woo_product_process_product_meta'));
            add_action('goopter_paypal_for_woocommerce_product_level_payment_action', array($this, 'goopter_paypal_for_woo_product_level_payment_action'), 10, 3);
            add_action('wp_head', array($this, 'paypal_for_woo_head_mark'), 1);
            add_action('init', array($this, 'goopter_register_post_status'), 99);
            add_action('current_screen', array($this, 'goopter_redirect_to_onboard'), 9);
            add_action('woocommerce_before_thankyou', array($this, 'goopter_direct_pay_thankyou_msg'));
            add_filter('woocommerce_my_account_my_orders_actions', array($this, 'goopter_direct_pay_custom_pay_link'), 10, 2);
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
                        esc_html('v' . VERSION_PFW),
                        esc_url('https://www.goopter.com')
                );
                echo "\n\r";
            }
        }

        public function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
            global $woocommerce;
            $gateways = $woocommerce->payment_gateways->payment_gateways();
            $base_url = admin_url('options-general.php?page=goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
            $configure_url = $base_url;
            if (isset($gateways['goopter_ppcp']) && (
                    ($gateways['goopter_ppcp']->sandbox === true && $gateways['goopter_ppcp']->sandbox_merchant_id) ||
                    ($gateways['goopter_ppcp']->sandbox === false && $gateways['goopter_ppcp']->sandbox_merchant_id)
                    )) {
                $configure_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp');
            }
            $configure = sprintf('<a href="%s">%s</a>', $configure_url, __('Configure', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
            $custom_actions = array(
                'configure' => $configure,
                'contact' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://www.goopter.com/contact-us/', __('Contact', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')),
            );
            return array_merge($custom_actions, $actions);
        }

        function set_ignore_tag() {
            global $current_user;
            $plugin = plugin_basename(__FILE__);
            $plugin_data = get_plugin_data(__FILE__, false);
            if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !is_plugin_active_for_network('woocommerce/woocommerce.php')) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- internal action no security issue
                if (!empty($_GET['action']) && !in_array($_GET['action'], array('activate-plugin', 'upgrade-plugin', 'activate', 'do-plugin-upgrade')) && is_plugin_active($plugin)) {
                    deactivate_plugins($plugin);
                    wp_die(
                        wp_kses_post(
                            '<strong>' . esc_html($plugin_data['Name']) . '</strong> requires <strong>WooCommerce</strong> plugin to work normally. Please activate it or install it from <a href="' . esc_url('http://wordpress.org/plugins/woocommerce/') . '" target="_blank">here</a>.<br /><br />Back to the WordPress <a href="' . esc_url(get_admin_url(null, 'plugins.php')) . '">Plugins page</a>.'
                        )
                    );                    
                }
            }

            $user_id = $current_user->ID;

            /* If user clicks to ignore the notice, add that to their user meta */

            $notices = array('ignore_pp_ssl', 'ignore_pp_sandbox', 'ignore_pp_woo', 'ignore_pp_check', 'ignore_pp_donate', 'ignore_paypal_plus_move_notice', 'ignore_billing_agreement_notice', 'payflow_sb_autopopulate_new_credentials', 'agree_disgree_opt_in_logging');

            foreach ($notices as $notice) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- internal action no security issue
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

            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/goopter-ppcp-constants.php');
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
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-direct-payment-goopter.php');
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
            global $post;
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
                'choose_image' => __('Choose Image', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
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
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- internal action no security issue
                if ($this->subscription_support_enabled) {
                    if ((isset($_GET['tab']) && 'checkout' === $_GET['tab']) && !isset($_GET['section'])) {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
                        $methods[] = 'Goopter_WC_Gateway_PPCP_Subscriptions';
                        $methods[] = 'Goopter_WC_Gateway_PPCP';
                    } else {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/wc-gateway-ppcp-goopter-subscriptions-base.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-apple-pay-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-google-pay-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-cc-goopter-subscriptions.php');
                        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
                            $methods[] = 'Goopter_WC_Gateway_PPCP_Apple_Pay_Subscriptions';
                            $methods[] = 'Goopter_WC_Gateway_PPCP_Google_Pay_Subscriptions';
                            $methods[] = 'Goopter_WC_Gateway_CC_Subscriptions';
                        }
                        $methods[] = 'Goopter_WC_Gateway_PPCP_Subscriptions';
                    }
                } else {
                    if ((isset($_GET['tab']) && 'checkout' === $_GET['tab']) && !isset($_GET['section'])) {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
                        $methods[] = 'Goopter_WC_Gateway_PPCP';
                    } else {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-apple-pay-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-google-pay-goopter.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-direct-payment-goopter.php');
                        $methods[] = 'Goopter_WC_Gateway_PPCP';
                        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
                            $methods[] = 'Goopter_WC_Gateway_Apple_Pay';
                            $methods[] = 'Goopter_WC_Gateway_Google_Pay';
                            $methods[] = 'Goopter_WC_Gateway_CC';
                            $methods[] = 'Goopter_WC_Gateway_Direct_Pay';
                        }
                    }
                }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended -- internal action no security issue
            } else {
                if ($this->subscription_support_enabled) {
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/wc-gateway-ppcp-goopter-subscriptions-base.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-apple-pay-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-goopter-google-pay-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-cc-goopter-subscriptions.php');
                    $methods[] = 'Goopter_WC_Gateway_PPCP_Subscriptions';
                    $methods[] = 'Goopter_WC_Gateway_CC_Subscriptions';
                    $methods[] = 'Goopter_WC_Gateway_PPCP_Apple_Pay_Subscriptions';
                    $methods[] = 'Goopter_WC_Gateway_PPCP_Google_Pay_Subscriptions';
                } else {
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-goopter.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-apple-pay-goopter.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-google-pay-goopter.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-direct-payment-goopter.php');
                    $methods[] = 'Goopter_WC_Gateway_PPCP';
                    $methods[] = 'Goopter_WC_Gateway_CC';
                    $methods[] = 'Goopter_WC_Gateway_Apple_Pay';
                    $methods[] = 'Goopter_WC_Gateway_Google_Pay';
                    $methods[] = 'Goopter_WC_Gateway_Direct_Pay';
                }
            }
            return $methods;
        }
    
        public function goopter_admin_menu_own() {
            $this->plugin_screen_hook_suffix = add_submenu_page(
                    'options-general.php',
                    __('PayPal for WooCommerce - Settings', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                    GOOPTER_PPCP_NAME,
                    'manage_options',
                    'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce',
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
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by woocommerce hook: woocommerce_process_product_meta
            $no_shipping_required = isset($_POST['_no_shipping_required']) ? 'yes' : 'no';
            update_post_meta($post_id, '_no_shipping_required', $no_shipping_required);
            $_paypal_billing_agreement = isset($_POST['_paypal_billing_agreement']) ? 'yes' : 'no';
            update_post_meta($post_id, '_paypal_billing_agreement', $_paypal_billing_agreement);
            $_enable_sandbox_mode = isset($_POST['_enable_sandbox_mode']) ? 'yes' : 'no';
            update_post_meta($post_id, '_enable_sandbox_mode', $_enable_sandbox_mode);
            $_enable_ec_button = isset($_POST['_enable_ec_button']) ? 'yes' : 'no';
            update_post_meta($post_id, '_enable_ec_button', $_enable_ec_button);
            // phpcs:enable WordPress.Security.NonceVerification.Missing -- checked by woocommerce hook: woocommerce_process_product_meta
        }

        public function goopter_woocommerce_admin_enqueue_scripts($hook) {
            wp_enqueue_style('ppe_cart', plugins_url('assets/css/admin.css', __FILE__), array(), VERSION_PFW);
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

        // public function http_api_curl_ec_add_curl_parameter($handle, $r, $url) {
        //     $Force_tls_one_point_two = get_option('Force_tls_one_point_two', 'no');
        //     if ((strstr($url, 'https://') && strstr($url, '.paypal.com')) && isset($Force_tls_one_point_two) && $Force_tls_one_point_two == 'yes') {
        //         curl_setopt($handle, CURLOPT_VERBOSE, 1);
        //         curl_setopt($handle, CURLOPT_SSLVERSION, 6);
        //     }
        // }

        public function http_api_curl_ec_add_curl_parameter($handle, $r, $url) {
            $Force_tls_one_point_two = get_option('Force_tls_one_point_two', 'no');
        
            // Check if the URL matches the conditions
            if ((strstr($url, 'https://') && strstr($url, '.paypal.com')) && isset($Force_tls_one_point_two) && $Force_tls_one_point_two == 'yes') {
                // Prepare arguments for wp_remote_get
                $args = [
                    'sslverify' => true,
                    'sslversion' => CURL_SSLVERSION_TLSv1_2, // Force TLS 1.2
                    'headers'   => [
                        'User-Agent' => 'WordPress/' . get_bloginfo('version'),
                    ],
                ];
        
                // Perform the HTTP request using wp_remote_get
                $response = wp_remote_get($url, $args);
        
                // Handle the response
                if (is_wp_error($response)) {
                    // error_log('Error with PayPal request: ' . $response->get_error_message());
                    return false; // Log and return false if the request fails
                }
        
                // If successful, retrieve and return the response body
                return wp_remote_retrieve_body($response);
            }
        
            // Return false if conditions are not met
            return false;
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
            load_plugin_textdomain('goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce', false, plugin_basename(dirname(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_FILE)) . '/i18n/languages');
        }

        public function goopter_dismiss_notice() {
            global $current_user;
            $user_id = $current_user->ID;
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- no security issue
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
            // phpcs:enable WordPress.Security.NonceVerification.Missing -- no security issue
        }

        public function goopter_paypal_for_woo_woocommerce_product_data_tabs($product_data_tabs) {
            global $woocommerce;
            $gateways = $woocommerce->payment_gateways->payment_gateways();
            if (isset($gateways['goopter_ppcp']) && 'yes' === $gateways['goopter_ppcp']->enabled) {
                $product_data_tabs['goopter_paypal_for_woo_payment_action'] = array(
                    'label' => __('Payment Action', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                    'target' => 'goopter_paypal_for_woo_payment_action',
                );
            }
            return $product_data_tabs;
        }

        public function goopter_paypal_for_woo_product_process_product_meta($post_id) {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- checked by woocommerce hook: woocommerce_process_product_meta
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
            // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- checked by woocommerce hook: woocommerce_process_product_meta
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
            $order_statuses['wc-partial-payment'] = _x('Partially Paid', 'Order status', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
            return $order_statuses;
        }

        public function goopter_register_post_status() {
            register_post_status('wc-partial-payment', array(
                'label' => _x('Partially Paid', 'Order status', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'public' => false,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                // Translators: %s is the count of partially paid items.
                'label_count' => _n_noop('Partially Paid <span class="count">(%s)</span>', 'Partially Paid <span class="count">(%s)</span>', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            ));
        }

        public function goopter_woocommerce_email_classes($emails) {
            $emails['Goopter_WC_Email_Partially_Paid_Order'] = include PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/classes/wc-email-customer-partial-paid-order.php';
            $emails['Goopter_WC_Email_Admin_Partially_Paid_Order'] = include PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/classes/wc-email-new-partial-paid-order.php';
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
                if (goopter_is_active_screen(goopter_get_shop_order_screen_id())) {
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
            $goopter_displayed_onboard_screen = get_option('goopter_displayed_onboard_screen', false);
            if ($woocommerce_goopter_ppcp_settings === false && $goopter_displayed_onboard_screen === false) {
                update_option('goopter_displayed_onboard_screen', 'yes');
                wp_safe_redirect(admin_url('options-general.php?page=goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce&tab=general_settings&gateway=paypal_payment_gateway_products'));
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
            $screen = goopter_get_shop_order_screen_id();
            if (goopter_is_active_screen($screen)) {
                require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/admin/class-wc-meta-box-order-items-ppcp.php';
                remove_meta_box('woocommerce-order-items', $screen, 'normal');
                add_meta_box('woocommerce-order-items', __('Items', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), 'Goopter_WC_Meta_Box_Order_Items::output', $screen, 'normal', 'high');
            }
        }

        public function goopter_direct_pay_thankyou_msg( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order && $order->get_payment_method() === 'goopter_direct_pay' ) {
                add_filter( 'woocommerce_thankyou_order_received_text', '__return_empty_string');
                if($order->is_paid()){
                    echo '<h1 style="font-weight:bold;color:green;">Payment successful</h1>';
                }elseif($order->get_status()=='pending'){
                    $pay_link = $order->get_meta('_goopter_direct_pay_href', true);
                    if ( $pay_link ) {
                        echo '<h1 style="font-weight:bold;color:black;">Choose a Payment Option</h1>';
                        echo '<iframe src="'.$pay_link.'" style="width:100%;border:none;min-height: 500px;"></iframe>';
                    }          
                }
            }
        }

        function goopter_direct_pay_custom_pay_link( $actions, $order ) {
            if ($order->get_payment_method() === 'goopter_direct_pay') {
                // in order_received_page(thankhyou page), we don't want to show the pay and cancel actions
                if (function_exists('is_order_received_page') && is_order_received_page()) {
                    unset($actions['pay']);
                    unset($actions['cancel']);
                }
                // update the pay action URL for my account order page
                if (isset($actions['pay'])) {
                    $return_url = $order->get_checkout_order_received_url();
                    // Swap out the URL
                    $actions['pay']['url'] = esc_url( $return_url );
                }
            }
            return $actions;
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
        require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/checkout-block/goopter-ppcp-apple-pay-block.php');
        require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/checkout-block/goopter-ppcp-google-pay-block.php');
        require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/checkout-block/goopter-direct-pay-block.php');
        add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new Goopter_PPCP_Checkout_Block);
                    $payment_method_registry->register(new Goopter_PPCP_CC_Block);
                    $payment_method_registry->register(new Goopter_Apple_Pay_Checkout_Block);
                    $payment_method_registry->register(new Goopter_Google_Pay_Checkout_Block);
                    $payment_method_registry->register(new Goopter_Direct_Pay_Block);
                }
        );
    } catch (Exception $ex) {
        
    }
});