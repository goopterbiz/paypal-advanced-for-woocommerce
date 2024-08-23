<?php
/**
 * @wordpress-plugin
 * Plugin Name:       PayPal for WooCommerce
 * Plugin URI:        http://www.angelleye.com/product/paypal-for-woocommerce-plugin/
 * Description:       Easily add the PayPal Complete Payments Platform including PayPal Checkout, Pay Later, Venmo, Direct Credit Processing, and alternative payment methods like Apple Pay, Google Pay, and more! Also fully supports Braintree Payments.
 * Version:           4.5.12
 * Author:            Angell EYE
 * Author URI:        http://www.angelleye.com/
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       paypal-for-woocommerce
 * Domain Path:       /i18n/languages/
 * GitHub Plugin URI: https://github.com/angelleye/paypal-woocommerce
 * Requires at least: 5.8
 * Tested up to: 6.6.0
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0.0
 * WC tested up to: 9.2.2
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

require_once('angelleye-includes/angelleye-functions.php');
require_once('angelleye-includes/angelleye-session-functions.php');
require_once('angelleye-includes/angelleye-conditional-functions.php');

if (!class_exists('AngellEYE_Gateway_Paypal')) {

    class AngellEYE_Gateway_Paypal {

        protected $plugin_screen_hook_suffix = null;
        protected $plugin_slug = 'paypal-for-woocommerce';
        private $subscription_support_enabled = false;
        public $minified_version;
        public $use_wp_locale_code;
        public $customer_id = '';
        public $pp_settings;

        public function __construct() {
            $this->define_constants();
            $this->minified_version = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            $this->pp_settings = get_option('woocommerce_paypal_express_settings', array());
            $this->initialize_actions();
            $this->include_files_and_classes();

            register_activation_hook(__FILE__, array($this, 'activate_paypal_for_woocommerce'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate_paypal_for_woocommerce'));

            add_filter('script_loader_tag', [$this, 'angelleye_pfw_clean_script_tag'], 10000, 3);
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ('angelleye_ppcp' !== $handle) {
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
                'VERSION_PFW' => '4.5.5',
                'PAYPAL_FOR_WOOCOMMERCE_PLUGIN_FILE' => __FILE__,
                'PAYPAL_FOR_WOOCOMMERCE_BASENAME' => plugin_basename(__FILE__),
                'PAYPAL_FOR_WOOCOMMERCE_DIR_PATH' => untrailingslashit(plugin_dir_path(__FILE__)),
                'PAYPAL_FOR_WOOCOMMERCE_PUSH_NOTIFICATION_WEB_URL' => 'https://www.angelleye.com/',
                'AEU_ZIP_URL' => 'https://updates.angelleye.com/ae-updater/angelleye-updater/angelleye-updater.zip',
                'PAYPAL_PPCP_SANDBOX_PARTNER_MERCHANT_ID' => 'B82TS7QWRJ6TS',
                'PAYPAL_PPCP_PARTNER_MERCHANT_ID' => 'J9L24TCUDZ6ZS',
                'PAYPAL_PPCP_SANDBOX_PARTNER_CLIENT_ID' => 'AaYsUf4lXeKOnLmKhDWbak0YYWNk5SW0Lt1lk22gFvsgu74h1Vawg1y6rcmt60f8JIx-x81J5bMA-q7O',
                'PAYPAL_PPCP_PARTNER_CLIENT_ID' => 'ATgw55qRjaDSlPur2FAkdAiB-QQuG5jlLsees-8dcxLiLla_nwbBSvSnCbUaGlmzxq9t2b8R9JGGSz1e',
                'PAYPAL_FOR_WOOCOMMERCE_PPCP_AWS_WEB_SERVICE' => 'https://zpyql2kd39.execute-api.us-east-2.amazonaws.com/production/PayPalMerchantIntegration/',
                'PAYPAL_FOR_WOOCOMMERCE_PPCP_ANGELLEYE_WEB_SERVICE' => 'https://ppcp.angelleye.com/production/PayPalMerchantIntegration/',
                'AE_FEE' => 'ae_p_f',
                'AE_PPCP_NAME' => 'PayPal Complete Payments'
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
            add_action('admin_notices', array($this, 'admin_notices'));
            add_action('admin_init', array($this, 'set_ignore_tag'));
            add_action('wp_enqueue_scripts', array($this, 'angelleye_cc_ui_style'), 100);
            add_action('parse_request', array($this, 'wc_gateway_payment_token_api_parser'), 99);
            add_action('wp_ajax_angelleye_dismiss_notice', array($this, 'angelleye_dismiss_notice'), 10);
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('admin_print_styles', array($this, 'admin_styles'));
            add_action('admin_menu', array($this, 'angelleye_admin_menu_own'));
            add_action('product_type_options', array($this, 'angelleye_product_type_options_own'), 10, 1);
            add_action('woocommerce_process_product_meta', array($this, 'angelleye_woocommerce_process_product_meta_own'), 10, 1);
            add_action('admin_enqueue_scripts', array($this, 'angelleye_woocommerce_admin_enqueue_scripts'));
            add_action('wp_ajax_pfw_ed_shipping_bulk_tool', array($this, 'angelleye_woocommerce_pfw_ed_shipping_bulk_tool'));
            add_action('http_api_curl', array($this, 'http_api_curl_ec_add_curl_parameter'), 10, 3);
            add_action('woocommerce_product_data_tabs', array($this, 'angelleye_paypal_for_woo_woocommerce_product_data_tabs'), 99, 1);
            add_action('woocommerce_product_data_panels', array($this, 'angelleye_paypal_for_woo_product_date_panels'));
            add_action('woocommerce_process_product_meta', array($this, 'angelleye_paypal_for_woo_product_process_product_meta'));
            add_action('angelleye_paypal_for_woocommerce_product_level_payment_action', array($this, 'angelleye_paypal_for_woo_product_level_payment_action'), 10, 3);
            add_action('wp_head', array($this, 'paypal_for_woo_head_mark'), 1);
            add_action('admin_footer', array($this, 'angelleye_add_deactivation_form'));
            add_action('wp_ajax_angelleye_send_deactivation', array($this, 'angelleye_handle_plugin_deactivation_request'));
            add_action('wp', array(__CLASS__, 'angelleye_delete_payment_method_action'), 10);
            add_action('init', array($this, 'angelleye_register_post_status'), 99);
            add_action('current_screen', array($this, 'angelleye_redirect_to_onboard'), 9);
        }

        private function include_files_and_classes() {
            include_once plugin_dir_path(__FILE__) . 'angelleye-includes/angelleye-payment-logger.php';
            AngellEYE_PFW_Payment_Logger::instance();
            if (!class_exists('AngellEYE_Utility')) {
                require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/angelleye-includes/angelleye-utility.php');
            }
            if (is_admin()) {
                include_once plugin_dir_path(__FILE__) . 'angelleye-includes/angelleye-admin-order-payment-process.php';
                new AngellEYE_Admin_Order_Payment_Process();
            }
            new AngellEYE_Utility($this->plugin_slug, VERSION_PFW);
        }

        private function initialize_other_actions_and_filters() {
            add_filter("pre_option_woocommerce_paypal_express_settings", array($this, 'angelleye_express_checkout_decrypt_gateway_api'), 10, 1);
            add_filter("pre_option_woocommerce_paypal_advanced_settings", array($this, 'angelleye_paypal_advanced_decrypt_gateway_api'), 10, 1);
            add_filter("pre_option_woocommerce_paypal_credit_card_rest_settings", array($this, 'angelleye_paypal_credit_card_rest_decrypt_gateway_api'), 10, 1);
            add_filter("pre_option_woocommerce_paypal_pro_settings", array($this, 'angelleye_paypal_pro_decrypt_gateway_api'), 10, 1);
            add_filter("pre_option_woocommerce_paypal_pro_payflow_settings", array($this, 'angelleye_paypal_pro_payflow_decrypt_gateway_api'), 10, 1);
            add_filter("pre_option_woocommerce_braintree_settings", array($this, 'angelleye_braintree_decrypt_gateway_api'), 10, 1);
            add_filter("pre_option_woocommerce_enable_guest_checkout", array($this, 'angelleye_express_checkout_woocommerce_enable_guest_checkout'), 10, 1);
            add_filter('woocommerce_get_checkout_order_received_url', array($this, 'angelleye_woocommerce_get_checkout_order_received_url'), 10, 2);
            add_filter('woocommerce_saved_payment_methods_list', array($this, 'angelleye_synce_braintree_save_payment_methods'), 5, 2);
            add_filter('wc_order_statuses', array($this, 'angelleye_wc_order_statuses'), 10, 1);
            add_filter('woocommerce_email_classes', array($this, 'angelleye_woocommerce_email_classes'), 10, 1);
            add_filter('woocommerce_email_actions', array($this, 'own_angelleye_woocommerce_email_actions'), 10);
            add_action('angelleye_classic_gateway_sub_menu', array($this, 'angelleye_classic_gateway_sub_menu'));
            add_action('wcv_save_product', array($this, 'angelleye_wcv_save_product'));
            add_filter('admin_body_class', array($this, 'angelleye_include_admin_body_class'), 9999);
            add_action('angelleye_pppc_seller_onboard_html', array($this, 'own_angelleye_pppc_seller_onboard_html'), 10, 1);
        }

        public function paypal_for_woo_head_mark() {
            $hide_watermark = get_option('pfw_hide_frontend_mark', 'no');
            if ($hide_watermark != 'yes') {
                echo sprintf(
                        '<!-- This site has installed %1$s %2$s - %3$s -->',
                        esc_html('PayPal for WooCommerce'),
                        ('v' . VERSION_PFW),
                        esc_url('https://www.angelleye.com/product/woocommerce-paypal-plugin/')
                );
                echo "\n\r";
            }
        }

        public function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
            global $woocommerce;
            $gateways = $woocommerce->payment_gateways->payment_gateways();
            $base_url = admin_url('options-general.php?page=paypal-for-woocommerce');
            $configure_url = $base_url;
            if (isset($gateways['angelleye_ppcp']) && (
                    ($gateways['angelleye_ppcp']->sandbox === true && $gateways['angelleye_ppcp']->sandbox_merchant_id) ||
                    ($gateways['angelleye_ppcp']->sandbox === false && $gateways['angelleye_ppcp']->sandbox_merchant_id)
                    )) {
                $configure_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=angelleye_ppcp');
            }
            $configure = sprintf('<a href="%s">%s</a>', $configure_url, __('Configure', 'paypal-for-woocommerce'));
            $custom_actions = array(
                'configure' => $configure,
                'docs' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://www.angelleye.com/paypal-for-woocommerce-documentation/?utm_source=paypal_for_woocommerce&utm_medium=docs_link&utm_campaign=paypal_for_woocommerce', __('Docs', 'paypal-for-woocommerce')),
                'support' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://www.angelleye.com/support', __('Support', 'paypal-for-woocommerce')),
                'review' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://www.angelleye.com/product/woocommerce-paypal-plugin?utm_source=pfw&utm_medium=support_link#tab-reviews', __('Write a Review', 'paypal-for-woocommerce')),
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

            require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/template/sidebar-process.php');

            $user_id = $current_user->ID;

            /* If user clicks to ignore the notice, add that to their user meta */

            $notices = array('ignore_pp_ssl', 'ignore_pp_sandbox', 'ignore_pp_woo', 'ignore_pp_check', 'ignore_pp_donate', 'ignore_paypal_plus_move_notice', 'ignore_billing_agreement_notice', 'ignore_paypal_pro_payflow_reference_transaction_notice', 'payflow_sb_autopopulate_new_credentials', 'agree_disgree_opt_in_logging');

            foreach ($notices as $notice) {
                if (isset($_GET[$notice]) && '0' == $_GET[$notice]) {
                    add_user_meta($user_id, $notice, 'true', true);
                    $set_ignore_tag_url = remove_query_arg($notice);
                    wp_redirect($set_ignore_tag_url);
                }
            }
        }

        function admin_notices() {
            global $current_user;
            $user_id = $current_user->ID;

            $this->pp_settings['testmode'] = isset($this->pp_settings['testmode']) ? $this->pp_settings['testmode'] : '';
            $this->pp_settings['enabled'] = isset($this->pp_settings['enabled']) ? $this->pp_settings['enabled'] : '';
            $screen = get_current_screen();
            if ($screen->id == "settings_page_paypal-for-woocommerce") {
                $processed = (isset($_GET['processed']) ) ? $_GET['processed'] : FALSE;
                if ($processed) {
                    echo '<div class="updated">';
                    echo '<p>' . sprintf(__('Action completed; %s records processed. ', 'paypal-for-woocommerce'), ($processed == 'zero') ? 0 : $processed) . '</p>';
                    echo '</div>';
                }
            }

            if (false === ($response = get_transient('angelleye_push_notification_result'))) {
                $response = AngellEYE_Utility::angelleye_get_push_notifications();
                if (is_object($response)) {
                    set_transient('angelleye_push_notification_result', $response, 12 * HOUR_IN_SECONDS);
                }
            }
            if (is_object($response)) {
                foreach ($response->data as $key => $response_data) {
                    $display = false;
                    if (!get_user_meta($user_id, $response_data->id)) {
                        if (!empty($response_data->ans_plugins) && is_array($response_data->ans_plugins)) {
                            foreach ($response_data->ans_plugins as $key => $gateway_id) {
                                if ('paypal-for-woocommerce' === $gateway_id) {
                                    $display = true;
                                    break;
                                } else {
                                    $gateway_option = get_option('woocommerce_' . $gateway_id . '_settings');
                                    if (!empty($gateway_option) && is_array($gateway_option)) {
                                        if (isset($gateway_option['enabled']) && 'yes' === $gateway_option['enabled']) {
                                            $display = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            if ($display) {
                                AngellEYE_Utility::angelleye_display_push_notification($response_data);
                            }
                        }
                    }
                }
            }
        }

        public function init() {
            if (!class_exists("WC_Payment_Gateway"))
                return;

            if (is_angelleye_multi_account_active()) {
                include_once plugin_dir_path(__FILE__) . 'angelleye-includes/express-checkout/class-wc-gateway-paypal-express-helper-angelleye-v1.php';
            } else {
                include_once plugin_dir_path(__FILE__) . 'angelleye-includes/express-checkout/class-wc-gateway-paypal-express-helper-angelleye-v2.php';
            }
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/ae-ppcp-constants.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/trait-angelleye-ppcp-core.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/pre-order/trait-wc-ppcp-pre-orders.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/class-angelleye-session-manager.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-base-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-angelleye-paypal-ppcp-seller-onboarding.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/angelleye-paypal-ppcp-common-functions.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-angelleye-paypal-ppcp-smart-button.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-angelleye-paypal-ppcp-pay-later-messaging.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-angelleye-paypal-ppcp-admin-action.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-angelleye-paypal-ppcp-front-action.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-pro-payflow-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-advanced-angelleye.php');
            if (is_angelleye_multi_account_active()) {
                include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-express-angelleye-v1.php');
            } else {
                include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-express-angelleye-v2.php');
            }
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-pro-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-braintree-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-credit-cards-rest-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-apple-pay-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-google-pay-angelleye.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/lib/class-angelleye-wordpress-custom-routes-handler.php');
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/includes/class-angelleye-paypal-ppcp-apple-domain-validation.php');
            AngellEYE_PayPal_PPCP_Smart_Button::instance();
            Angelleye_PayPal_Express_Checkout_Helper::instance();
            AngellEYE_PayPal_PPCP_Seller_Onboarding::instance();
            AngellEYE_PayPal_PPCP_Pay_Later::instance();
            AngellEYE_PayPal_PPCP_Admin_Action::instance();
            AngellEYE_PayPal_PPCP_Front_Action::instance();
            add_filter('woocommerce_payment_gateways', array($this, 'angelleye_add_paypal_pro_gateway'), 1000);
            AngellEye_PayPal_PPCP_Apple_Domain_Validation::instance();
            AngellEye_Session_Manager::instance();
            add_filter('woocommerce_payment_gateways', array($this, 'angelleye_add_paypal_pro_gateway'), 3);
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
            if (is_angelleye_multi_account_active()) {
                wp_register_script('angelleye_admin', plugins_url('/assets/js/angelleye-admin-v1.js', __FILE__), array('jquery'), $script_versions);
            } else {
                wp_register_script('angelleye_admin', plugins_url('/assets/js/angelleye-admin-v2.js', __FILE__), array('jquery'), $script_versions);
            }
            $this->use_wp_locale_code = !empty($this->pp_settings['use_wp_locale_code']) ? $this->pp_settings['use_wp_locale_code'] : 'yes';
            $translation_array = array(
                'is_ssl' => is_ssl() ? "yes" : "no",
                'choose_image' => __('Choose Image', 'paypal-for-woocommerce'),
                'payment_method' => $payment_method,
                'payment_action' => $payment_action,
                'is_paypal_credit_enable' => "yes",
                'locale' => ($this->use_wp_locale_code === 'yes' && AngellEYE_Utility::get_button_locale_code() != '') ? AngellEYE_Utility::get_button_locale_code() : ''
            );
            if (!empty($_GET['tab']) && !empty($_GET['section']) && $_GET['tab'] == 'checkout' && $_GET['section'] == 'paypal_express') {
                if (is_angelleye_multi_account_active()) {
                    wp_enqueue_script('angelleye-in-context-checkout-js-admin', 'https://www.paypalobjects.com/api/checkout.min.js', array(), null, true);
                } else {
                    $smart_js_arg = array();
                    $smart_js_arg['components'] = "buttons,messages";
                    $smart_js_arg['currency'] = get_woocommerce_currency();
                    $smart_js_arg['locale'] = AngellEYE_Utility::get_button_locale_code();
                    $disallowed_funding_methods = !empty($this->pp_settings['disallowed_funding_methods']) ? (array) $this->pp_settings['disallowed_funding_methods'] : array();
                    if ($disallowed_funding_methods !== false && count($disallowed_funding_methods) > 0) {
                        $smart_js_arg['disable-funding'] = implode(',', $disallowed_funding_methods);
                    }
                    if (isset($this->pp_settings['testmode']) && $this->pp_settings['testmode'] == 'yes') {
                        $smart_js_arg['buyer-country'] = WC()->countries->get_base_country();
                        $smart_js_arg['client-id'] = 'sb';
                    } else {
                        $merchant_id_array = get_option('angelleye_express_checkout_default_pal');
                        if (!empty($merchant_id_array) && !empty($merchant_id_array['PAL'])) {
                            $smart_js_arg['merchant-id'] = $merchant_id_array['PAL'];
                        }
                        $smart_js_arg['client-id'] = 'AUESd5dCP7FmcZnzB7v32UIo-gGgnJupvdfLle9TBJwOC4neACQhDVONBv3hc1W-pXlXS6G-KA5y4Kzv';
                    }
                    $admin_paypal_sdk_js = add_query_arg($smart_js_arg, 'https://www.paypal.com/sdk/js');
                    $translation_array['paypal_sdk_url'] = $admin_paypal_sdk_js;
                    wp_enqueue_script('admin-checkout-js', $admin_paypal_sdk_js, array(), null, true);
                }
            }
            wp_enqueue_script('angelleye_admin');
            wp_localize_script('angelleye_admin', 'angelleye_admin', $translation_array);
        }

        public function admin_styles() {
            wp_enqueue_style('thickbox');
        }

        public function activate_paypal_for_woocommerce() {
            if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && !is_plugin_active_for_network('woocommerce/woocommerce.php')) {
                deactivate_plugins(plugin_basename(__FILE__));
            }
            delete_option('angelleye_paypal_woocommerce_submited_feedback');
            $log_url = site_url();
            $log_plugin_id = 1;
            $log_activation_status = 1;
            wp_remote_request('http://www.angelleye.com/web-services/wordpress/update-plugin-status.php?url=' . $log_url . '&plugin_id=' . $log_plugin_id . '&activation_status=' . $log_activation_status);
        }

        public function deactivate_paypal_for_woocommerce() {
            $is_submited_feedback = get_option('angelleye_paypal_woocommerce_submited_feedback', 'no');
            if ($is_submited_feedback == 'no') {
                $log_url = site_url();
                $log_plugin_id = 1;
                $log_activation_status = 0;
                wp_remote_request('http://www.angelleye.com/web-services/wordpress/update-plugin-status.php?url=' . $log_url . '&plugin_id=' . $log_plugin_id . '&activation_status=' . $log_activation_status);
            }
        }

        public function angelleye_add_paypal_pro_gateway($methods) {
            if (class_exists('WC_Subscriptions') && function_exists('wcs_create_renewal_order')) {
                $this->subscription_support_enabled = true;
            }
            foreach ($methods as $key => $method) {
                if (in_array($method, array('WC_Gateway_PayPal_Pro', 'WC_Gateway_PayPal_Pro_Payflow', 'WC_Gateway_PayPal_Express'))) {
                    unset($methods[$key]);
                    break;
                }
            }
            if (is_admin()) {
                if ($this->subscription_support_enabled) {
                    if ((isset($_GET['tab']) && 'checkout' === $_GET['tab']) && !isset($_GET['section'])) {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-braintree-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-braintree-subscriptions-angelleye.php');
                        if (is_angelleye_multi_account_active()) {
                            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-express-angelleye-v1.php');
                        } else {
                            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-express-angelleye-v2.php');
                        }
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-express-subscriptions-angelleye.php');
                        $methods[] = 'WC_Gateway_Braintree_Subscriptions_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Express_Subscriptions_AngellEYE';
                        $methods[] = 'WC_Gateway_PPCP_AngellEYE_Subscriptions';
                        $methods[] = 'WC_Gateway_PPCP_AngellEYE';
                    } else {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-pro-payflow-subscriptions-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-advanced-subscriptions-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-express-subscriptions-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-pro-subscriptions-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-braintree-subscriptions-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-credit-cards-rest-subscriptions-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/wc-gateway-ppcp-angelleye-subscriptions-base.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-angelleye-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-angelleye-apple-pay-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-angelleye-google-pay-subscriptions.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-cc-angelleye-subscriptions.php');
                        $methods[] = 'WC_Gateway_PayPal_Pro_PayFlow_Subscriptions_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Advanced_Subscriptions_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Pro_Subscriptions_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Express_Subscriptions_AngellEYE';
                        $methods[] = 'WC_Gateway_Braintree_Subscriptions_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Credit_Card_Rest_Subscriptions_AngellEYE';
                        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
                            $methods[] = 'WC_Gateway_PPCP_AngellEYE_Apple_Pay_Subscriptions';
                            $methods[] = 'WC_Gateway_PPCP_AngellEYE_Google_Pay_Subscriptions';
                            $methods[] = 'WC_Gateway_CC_AngellEYE_Subscriptions';
                        }
                        $methods[] = 'WC_Gateway_PPCP_AngellEYE_Subscriptions';
                    }
                } else {
                    if ((isset($_GET['tab']) && 'checkout' === $_GET['tab']) && !isset($_GET['section'])) {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-braintree-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-angelleye.php');
                        if (is_angelleye_multi_account_active()) {
                            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-express-angelleye-v1.php');
                        } else {
                            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/wc-gateway-paypal-express-angelleye-v2.php');
                        }
                        $methods[] = 'WC_Gateway_Braintree_AngellEYE';
                        $methods[] = 'WC_Gateway_PPCP_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Express_AngellEYE';
                    } else {
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-apple-pay-angelleye.php');
                        include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-google-pay-angelleye.php');
                        $methods[] = 'WC_Gateway_PayPal_Pro_Payflow_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Advanced_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Pro_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Express_AngellEYE';
                        $methods[] = 'WC_Gateway_Braintree_AngellEYE';
                        $methods[] = 'WC_Gateway_PayPal_Credit_Card_Rest_AngellEYE';
                        $methods[] = 'WC_Gateway_PPCP_AngellEYE';
                        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
                            $methods[] = 'WC_Gateway_Apple_Pay_AngellEYE';
                            $methods[] = 'WC_Gateway_Google_Pay_AngellEYE';
                            $methods[] = 'WC_Gateway_CC_AngellEYE';
                        }
                    }
                }
            } else {
                if ($this->subscription_support_enabled) {
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-pro-payflow-subscriptions-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-advanced-subscriptions-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-express-subscriptions-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-pro-subscriptions-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-braintree-subscriptions-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/classes/subscriptions/wc-gateway-paypal-credit-cards-rest-subscriptions-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/wc-gateway-ppcp-angelleye-subscriptions-base.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-angelleye-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-angelleye-apple-pay-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-ppcp-angelleye-google-pay-subscriptions.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/subscriptions/class-wc-gateway-cc-angelleye-subscriptions.php');
                    $methods[] = 'WC_Gateway_PayPal_Pro_PayFlow_Subscriptions_AngellEYE';
                    $methods[] = 'WC_Gateway_PayPal_Advanced_Subscriptions_AngellEYE';
                    $methods[] = 'WC_Gateway_PayPal_Pro_Subscriptions_AngellEYE';
                    $methods[] = 'WC_Gateway_PayPal_Express_Subscriptions_AngellEYE';
                    $methods[] = 'WC_Gateway_Braintree_Subscriptions_AngellEYE';
                    $methods[] = 'WC_Gateway_PayPal_Credit_Card_Rest_Subscriptions_AngellEYE';
                    $methods[] = 'WC_Gateway_PPCP_AngellEYE_Apple_Pay_Subscriptions';
                    $methods[] = 'WC_Gateway_PPCP_AngellEYE_Google_Pay_Subscriptions';
                    $methods[] = 'WC_Gateway_PPCP_AngellEYE_Subscriptions';
                    $methods[] = 'WC_Gateway_CC_AngellEYE_Subscriptions';
                } else {
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-cc-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-apple-pay-angelleye.php');
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-google-pay-angelleye.php');
                    $methods[] = 'WC_Gateway_PayPal_Pro_Payflow_AngellEYE';
                    $methods[] = 'WC_Gateway_PayPal_Advanced_AngellEYE';
                    $methods[] = 'WC_Gateway_PayPal_Pro_AngellEYE';
                    $methods[] = 'WC_Gateway_PayPal_Express_AngellEYE';
                    $methods[] = 'WC_Gateway_Braintree_AngellEYE';
                    $methods[] = 'WC_Gateway_PayPal_Credit_Card_Rest_AngellEYE';
                    $methods[] = 'WC_Gateway_Apple_Pay_AngellEYE';
                    $methods[] = 'WC_Gateway_Google_Pay_AngellEYE';
                    $methods[] = 'WC_Gateway_PPCP_AngellEYE';
                    $methods[] = 'WC_Gateway_CC_AngellEYE';
                }
            }
            return $methods;
        }

        public function angelleye_admin_menu_own() {
            $this->plugin_screen_hook_suffix = add_submenu_page(
                    'options-general.php',
                    __('PayPal for WooCommerce - Settings', 'paypal-for-woocommerce'),
                    AE_PPCP_NAME,
                    'manage_options',
                    'paypal-for-woocommerce',
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

        function angelleye_product_type_options_own($product_type) {
            global $pagenow;
            if (empty($this->pp_settings['enabled']) || $this->pp_settings['enabled'] != 'yes') {
                return $product_type;
            }
            if (isset($product_type) && !empty($product_type)) {
                $product_type['no_shipping_required'] = array(
                    'id' => '_no_shipping_required',
                    'wrapper_class' => '',
                    'label' => __('No Shipping Required', 'paypal-for-woocommerce'),
                    'description' => __('Disables shipping requirements in the PayPal checkout flow.', 'paypal-for-woocommerce'),
                    'default' => 'no'
                );
                $product_type['paypal_billing_agreement'] = array(
                    'id' => '_paypal_billing_agreement',
                    'wrapper_class' => '',
                    'label' => __('Enable PayPal Billing Agreement', 'paypal-for-woocommerce'),
                    'description' => __('Adds a billing agreement to the product.  The user must agree to the billing agreement on the PayPal checkout pages, and then you can process future payments for the buyer using reference transactions.', 'paypal-for-woocommerce'),
                    'default' => 'no'
                );
                $product_type['enable_sandbox_mode'] = array(
                    'id' => '_enable_sandbox_mode',
                    'wrapper_class' => '',
                    'label' => __('Enable Sandbox Mode', 'paypal-for-woocommerce'),
                    'description' => __('If this product is included in the cart the order will be processed in the PayPal sandbox for testing purposes.', 'paypal-for-woocommerce'),
                    'default' => 'no'
                );

                $default_ec_button = 'no';
                if (($pagenow == 'post-new.php') && (get_post_type() == 'product')) {
                    if (!empty($this->pp_settings['show_on_product_page']) && $this->pp_settings['show_on_product_page'] == 'yes' && !empty($this->pp_settings['enable_newly_products']) && $this->pp_settings['enable_newly_products'] == 'yes') {
                        $default_ec_button = 'yes';
                    }
                }

                $product_type['enable_ec_button'] = array(
                    'id' => '_enable_ec_button',
                    'wrapper_class' => '',
                    'label' => __('Enable Express Checkout Button', 'paypal-for-woocommerce'),
                    'description' => __('Adds the PayPal Express Checkout button to the product page allowing buyers to checkout directly from the product page.', 'paypal-for-woocommerce'),
                    'default' => $default_ec_button
                );
                if (is_angelleye_multi_account_active()) {
                    unset($product_type['paypal_billing_agreement']);
                }
                return $product_type;
            } else {
                return $product_type;
            }
        }

        function angelleye_woocommerce_process_product_meta_own($post_id) {
            $no_shipping_required = isset($_POST['_no_shipping_required']) ? 'yes' : 'no';
            update_post_meta($post_id, '_no_shipping_required', $no_shipping_required);
            $_paypal_billing_agreement = isset($_POST['_paypal_billing_agreement']) ? 'yes' : 'no';
            update_post_meta($post_id, '_paypal_billing_agreement', $_paypal_billing_agreement);
            $_enable_sandbox_mode = isset($_POST['_enable_sandbox_mode']) ? 'yes' : 'no';
            update_post_meta($post_id, '_enable_sandbox_mode', $_enable_sandbox_mode);
            $_enable_ec_button = isset($_POST['_enable_ec_button']) ? 'yes' : 'no';
            update_post_meta($post_id, '_enable_ec_button', $_enable_ec_button);
        }

        public static function angelleye_paypal_for_woocommerce_curl_error_handler($PayPalResult, $method_name = null, $gateway = null, $error_email_notify = true, $redirect_url = null) {
            if (isset($PayPalResult['CURL_ERROR'])) {
                try {
                    if ($error_email_notify == true) {
                        $admin_email = get_option("admin_email");
                        $message = __($method_name . " call failed.", "paypal-for-woocommerce") . "\n\n";
                        $message .= __('Error Code: 0', 'paypal-for-woocommerce') . "\n";
                        $message .= __('Detailed Error Message: ', 'paypal-for-woocommerce') . $PayPalResult['CURL_ERROR'];
                        wp_mail($admin_email, $gateway . " Error Notification", $message);
                    }
                    $display_error = 'There was a problem connecting to the payment gateway.';
                    wc_add_notice($display_error, 'error');
                    if (!wp_doing_ajax()) {
                        if ($redirect_url == null) {
                            wp_redirect(wc_get_cart_url());
                        } else {
                            wp_redirect($redirect_url);
                        }
                        exit;
                    } else {
                        wp_send_json_error(array('error' => $display_error));
                    }
                } catch (Exception $e) {
                    if (!empty($e)) {
                        throw new Exception(__($e->getMessage(), 'paypal-for-woocommerce'));
                    }
                }
            }
        }

        public function angelleye_woocommerce_add_to_cart_sold_individually_quantity($qtyone, $quantity, $product_id, $variation_id, $cart_item_data) {
            if ((isset($_REQUEST['express_checkout']) && $_REQUEST['express_checkout'] == 1) && (isset($_REQUEST['add-to-cart']) && !empty($_REQUEST['add-to-cart']))) {
                if (sizeof(WC()->cart->get_cart()) != 0) {
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        $cart_product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
                        if ($product_id == $cart_product_id || $variation_id == $cart_product_id) {
                            wp_redirect(add_query_arg(array('pp_action' => 'set_express_checkout', 'utm_nooverride' => '1'), untrailingslashit(WC()->api_request_url('WC_Gateway_PayPal_Express_AngellEYE'))));
                            exit();
                        }
                    }
                } else {
                    return $qtyone;
                }
            } else {
                return $qtyone;
            }
        }

        public function angelleye_woocommerce_admin_enqueue_scripts($hook) {
            wp_enqueue_style('ppe_cart', plugins_url('assets/css/admin.css', __FILE__), array(), VERSION_PFW);
            if ('plugins.php' === $hook) {
                wp_enqueue_style('deactivation-modal', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/css/deactivation-modal.css', null, VERSION_PFW);
                wp_enqueue_script('deactivation-modal', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/js/deactivation-form-modal.js', null, VERSION_PFW, true);
                wp_localize_script('deactivation-modal', 'angelleye_ajax_data', array('nonce' => wp_create_nonce('angelleye-ajax')));
            }
        }

        public function angelleye_woocommerce_pfw_ed_shipping_bulk_tool() {
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
                $pfw_bulk_action_type = (isset($_POST["actionType"])) ? $_POST['actionType'] : FALSE;
                $pfw_bulk_action_target_type = (isset($_POST["actionTargetType"])) ? $_POST['actionTargetType'] : FALSE;
                $pfw_bulk_action_target_where_type = (isset($_POST["actionTargetWhereType"])) ? $_POST['actionTargetWhereType'] : FALSE;
                $pfw_bulk_action_target_where_category = (isset($_POST["actionTargetWhereCategory"])) ? $_POST['actionTargetWhereCategory'] : FALSE;
                $pfw_bulk_action_target_where_product_type = (isset($_POST["actionTargetWhereProductType"])) ? $_POST['actionTargetWhereProductType'] : FALSE;
                $pfw_bulk_action_target_where_price_value = (isset($_POST["actionTargetWherePriceValue"])) ? $_POST['actionTargetWherePriceValue'] : FALSE;
                $pfw_bulk_action_target_where_stock_value = (isset($_POST["actionTargetWhereStockValue"])) ? $_POST['actionTargetWhereStockValue'] : FALSE;

                if (!$pfw_bulk_action_type || !$pfw_bulk_action_target_type) {
                    $errors = TRUE;
                }

                $is_enable_value = explode("_", $pfw_bulk_action_type);
                $is_enable = (isset($is_enable_value[0]) && $is_enable_value[0] == 'enable') ? 'yes' : 'no';

                if ($pfw_bulk_action_type == 'enable_no_shipping' || $pfw_bulk_action_type == 'disable_no_shipping') {
                    $action_key = "_no_shipping_required";
                } elseif ($pfw_bulk_action_type == 'enable_paypal_billing_agreement' || $pfw_bulk_action_type == 'disable_paypal_billing_agreement') {
                    $action_key = "_paypal_billing_agreement";
                } elseif ($pfw_bulk_action_type == 'enable_express_checkout_button' || $pfw_bulk_action_type == 'disable_express_checkout_button') {
                    $action_key = "_enable_ec_button";
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
                                        if (update_post_meta($target_product_id, 'woo_product_payment_action', wc_clean($_POST['payment_action']))) {
                                            $processed_product_id[$target_product_id] = $target_product_id;
                                        }
                                        if ($_POST['payment_action'] == 'Authorization') {
                                            if (update_post_meta($target_product_id, 'woo_product_payment_action_authorization', wc_clean($_POST['authorization_type']))) {
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
                                        if (update_post_meta($target_product_id, 'woo_product_payment_action', wc_clean($_POST['payment_action']))) {
                                            $processed_product_id[$target_product_id] = $target_product_id;
                                        }
                                        if ($_POST['payment_action'] == 'Authorization') {
                                            if (update_post_meta($target_product_id, 'woo_product_payment_action_authorization', wc_clean($_POST['authorization_type']))) {
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
                    $redirect_url = admin_url('options-general.php?page=paypal-for-woocommerce&tab=general_settings&gateway=tool&processed=' . $update_count);
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

        public function wc_gateway_payment_token_api_parser() {
            if (!empty($_GET['do_action']) && $_GET['do_action'] == 'update_payment_method') {
                if (!empty($_GET['method_name']) && $_GET['method_name'] == 'paypal_express') {
                    switch ($_GET['action_name']) {
                        case 'SetExpressCheckout':
                            $woocommerce_token_api = new WC_Gateway_PayPal_Express_AngellEYE();
                            $woocommerce_token_api->paypal_express_checkout_token_request_handler();
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        public function angelleye_express_checkout_decrypt_gateway_api($bool) {
            $gateway_settings = AngellEYE_Utility::angelleye_get_pre_option($bool, 'woocommerce_paypal_express_settings');
            if (!empty($gateway_settings) && !empty($gateway_settings['is_encrypt'])) {
                $gateway_settings_key_array = array('sandbox_api_username', 'sandbox_api_password', 'sandbox_api_signature', 'api_username', 'api_password', 'api_signature');
                foreach ($gateway_settings_key_array as $gateway_setting_key => $gateway_settings_value) {
                    if (!empty($gateway_settings[$gateway_settings_value])) {
                        $gateway_settings[$gateway_settings_value] = AngellEYE_Utility::crypting($gateway_settings[$gateway_settings_value], $action = 'd');
                    }
                }
                return $gateway_settings;
            } else {
                return $bool;
            }
        }

        public function angelleye_paypal_advanced_decrypt_gateway_api($bool) {
            $gateway_settings = AngellEYE_Utility::angelleye_get_pre_option($bool, 'woocommerce_paypal_advanced_settings');
            if (!empty($gateway_settings) && !empty($gateway_settings['is_encrypt'])) {
                $gateway_settings_key_array = array('loginid', 'resellerid', 'user', 'password');
                foreach ($gateway_settings_key_array as $gateway_settings_key => $gateway_settings_value) {
                    if (!empty($gateway_settings[$gateway_settings_value])) {
                        $gateway_settings[$gateway_settings_value] = AngellEYE_Utility::crypting($gateway_settings[$gateway_settings_value], $action = 'd');
                    }
                }
                return $gateway_settings;
            } else {
                return $bool;
            }
        }

        public function angelleye_paypal_credit_card_rest_decrypt_gateway_api($bool) {
            $gateway_settings = AngellEYE_Utility::angelleye_get_pre_option($bool, 'woocommerce_paypal_credit_card_rest_settings');
            if (!empty($gateway_settings) && !empty($gateway_settings['is_encrypt'])) {
                $gateway_settings_key_array = array('rest_client_id_sandbox', 'rest_secret_id_sandbox', 'rest_client_id', 'rest_secret_id');
                foreach ($gateway_settings_key_array as $gateway_settings_key => $gateway_settings_value) {
                    if (!empty($gateway_settings[$gateway_settings_value])) {
                        $gateway_settings[$gateway_settings_value] = AngellEYE_Utility::crypting($gateway_settings[$gateway_settings_value], $action = 'd');
                    }
                }
                return $gateway_settings;
            } else {
                return $bool;
            }
        }

        public function angelleye_paypal_pro_decrypt_gateway_api($bool) {
            $gateway_settings = AngellEYE_Utility::angelleye_get_pre_option($bool, 'woocommerce_paypal_pro_settings');
            if (!empty($gateway_settings) && !empty($gateway_settings['is_encrypt'])) {
                $gateway_settings_key_array = array('sandbox_api_username', 'sandbox_api_password', 'sandbox_api_signature', 'api_username', 'api_password', 'api_signature');
                foreach ($gateway_settings_key_array as $gateway_settings_key => $gateway_settings_value) {
                    if (!empty($gateway_settings[$gateway_settings_value])) {
                        $gateway_settings[$gateway_settings_value] = AngellEYE_Utility::crypting($gateway_settings[$gateway_settings_value], $action = 'd');
                    }
                }
                return $gateway_settings;
            } else {
                return $bool;
            }
        }

        public function angelleye_paypal_pro_payflow_decrypt_gateway_api($bool) {
            $gateway_settings = AngellEYE_Utility::angelleye_get_pre_option($bool, 'woocommerce_paypal_pro_payflow_settings');
            if (!empty($gateway_settings) && !empty($gateway_settings['is_encrypt'])) {
                $gateway_settings_key_array = array('sandbox_paypal_vendor', 'sandbox_paypal_password', 'sandbox_paypal_user', 'sandbox_paypal_partner', 'paypal_vendor', 'paypal_password', 'paypal_user', 'paypal_partner');
                foreach ($gateway_settings_key_array as $gateway_settings_key => $gateway_settings_value) {
                    if (!empty($gateway_settings[$gateway_settings_value])) {
                        $gateway_settings[$gateway_settings_value] = AngellEYE_Utility::crypting($gateway_settings[$gateway_settings_value], $action = 'd');
                    }
                }
                return $gateway_settings;
            } else {
                return $bool;
            }
        }

        public function angelleye_express_checkout_woocommerce_enable_guest_checkout($bool) {
            global $wpdb;
            $return = $bool;
            if (!class_exists('WooCommerce') || WC()->session == null) {
                return false;
            }
            $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'woocommerce_enable_guest_checkout'));
            $paypal_express_checkout = angelleye_get_session('paypal_express_checkout');
            $ec_save_to_account = angelleye_get_session('ec_save_to_account');
            if (!empty($row->option_value) && $row->option_value == 'yes' && isset($paypal_express_checkout) && !empty($paypal_express_checkout) && isset($ec_save_to_account) && $ec_save_to_account == 'on') {
                $return = 'no';
            } else {
                $return = $bool;
            }
            return apply_filters('woocommerce_enable_guest_checkout', $return);
        }

        public function angelleye_braintree_decrypt_gateway_api($bool) {
            $gateway_settings = AngellEYE_Utility::angelleye_get_pre_option($bool, 'woocommerce_braintree_settings');
            if (!empty($gateway_settings) && !empty($gateway_settings['is_encrypt'])) {
                $gateway_settings_key_array = array('sandbox_public_key', 'sandbox_private_key', 'sandbox_merchant_id', 'public_key', 'private_key', 'merchant_id');
                foreach ($gateway_settings_key_array as $gateway_settings_key => $gateway_settings_value) {
                    if (!empty($gateway_settings[$gateway_settings_value])) {
                        $gateway_settings[$gateway_settings_value] = AngellEYE_Utility::crypting($gateway_settings[$gateway_settings_value], $action = 'd');
                    }
                }
                return $gateway_settings;
            } else {
                return $bool;
            }
        }

        public static function clean_product_title($product_title) {
            $product_title = strip_tags($product_title);
            $product_title = str_replace(array("&#8211;", "&#8211"), array("-"), $product_title);
            $product_title = str_replace('&', '-', $product_title);
            return $product_title;
        }

        public function angelleye_woocommerce_get_checkout_order_received_url($order_received_url, $order) {
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
            load_plugin_textdomain('paypal-for-woocommerce', false, plugin_basename(dirname(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_FILE)) . '/i18n/languages');
        }

        public function angelleye_dismiss_notice() {
            global $current_user;
            $user_id = $current_user->ID;
            if (!empty($_POST['action']) && $_POST['action'] == 'angelleye_dismiss_notice') {
                $notices = array('ignore_pp_ssl', 'ignore_pp_sandbox', 'ignore_pp_woo', 'ignore_pp_check', 'ignore_pp_donate', 'ignore_paypal_plus_move_notice', 'ignore_billing_agreement_notice', 'ignore_paypal_pro_payflow_reference_transaction_notice', 'ignore_token_multi_account', 'ignore_token_multi_account_payflow');
                foreach ($notices as $notice) {
                    if (!empty($_POST['data']) && $_POST['data'] == $notice) {
                        add_user_meta($user_id, $notice, 'true', true);
                        wp_send_json_success();
                    }
                }
                if (isset($_POST['data'])) {
                    add_user_meta($user_id, wc_clean($_POST['data']), 'true', true);
                    wp_send_json_success();
                }
            }
        }

        public function angelleye_paypal_for_woo_woocommerce_product_data_tabs($product_data_tabs) {
            global $woocommerce;
            $gateways = $woocommerce->payment_gateways->payment_gateways();
            if ((isset($gateways['paypal_express']) && 'yes' === $gateways['paypal_express']->enabled) || (isset($gateways['angelleye_ppcp']) && 'yes' === $gateways['angelleye_ppcp']->enabled)) {
                $product_data_tabs['angelleye_paypal_for_woo_payment_action'] = array(
                    'label' => __('Payment Action', 'paypal-for-woocommerce'),
                    'target' => 'angelleye_paypal_for_woo_payment_action',
                );
            }
            return $product_data_tabs;
        }

        public function angelleye_paypal_for_woo_product_date_panels() {
            global $woocommerce, $post;
            if (empty($this->pp_settings['enabled']) || $this->pp_settings['enabled'] != 'yes') {
                return false;
            }
            ?>
            <div id="angelleye_paypal_for_woo_payment_action" class="panel woocommerce_options_panel">
            <?php
            $gateways = $woocommerce->payment_gateways->payment_gateways();
            if ((isset($gateways['paypal_express']) && 'yes' === $gateways['paypal_express']->enabled) || (isset($gateways['angelleye_ppcp']) && 'yes' === $gateways['angelleye_ppcp']->enabled)) {
                woocommerce_wp_checkbox(
                        array(
                            'id' => 'enable_payment_action',
                            'label' => __('Enable Payment Action', 'paypal-for-woocommerce'),
                        )
                );
                woocommerce_wp_select(
                        array(
                            'id' => 'woo_product_payment_action',
                            'label' => __('Payment Action', 'paypal-for-woocommerce'),
                            'options' => array(
                                '' => 'Select Payment Action',
                                'Sale' => 'Sale',
                                'Authorization' => 'Authorization',
                            ),
                            'desc_tip' => 'true',
                            'description' => __('Sale will capture the funds immediately when the order is placed.  Authorization will authorize the payment but will not capture the funds.'),
                        )
                );
                if (isset($gateways['paypal_pro_payflow']) && (isset($gateways['paypal_pro_payflow']->enabled) && 'no' != $gateways['paypal_pro_payflow']->enabled)) {
                    woocommerce_wp_select(
                            array(
                                'id' => 'woo_product_payment_action_authorization',
                                'label' => __('Authorization Type', 'paypal-for-woocommerce'),
                                'options' => array(
                                    'Full Authorization' => 'Full Authorization',
                                    'Card Verification' => 'Card Verification',
                                ),
                                'description' => __('This option will only work with <b>PayPal Payments Pro 2.0 (PayFlow)</b> payment method.'),
                            )
                    );
                }
                ?>
                </div>
                <?php
            }
        }

        public function angelleye_paypal_for_woo_product_process_product_meta($post_id) {
            if (isset($_REQUEST['enable_payment_action']) && ('yes' === $_REQUEST['enable_payment_action'])) {
                update_post_meta($post_id, 'enable_payment_action', 'yes');
            } else {
                update_post_meta($post_id, 'enable_payment_action', '');
            }
            $woo_product_payment_action = !empty($_POST['woo_product_payment_action']) ? wc_clean($_POST['woo_product_payment_action']) : '';
            update_post_meta($post_id, 'woo_product_payment_action', $woo_product_payment_action);
            if (!empty($woo_product_payment_action) && 'Authorization' == $woo_product_payment_action) {
                $woo_product_payment_action_authorization = !empty($_POST['woo_product_payment_action_authorization']) ? wc_clean($_POST['woo_product_payment_action_authorization']) : '';
                update_post_meta($post_id, 'woo_product_payment_action_authorization', $woo_product_payment_action_authorization);
            } else {
                update_post_meta($post_id, 'woo_product_payment_action_authorization', '');
            }
        }

        public function angelleye_paypal_for_woo_product_level_payment_action($gateways, $request = null, $order_id = null) {
            if (is_null(WC()->cart)) {
                return true;
            }
            if ($request == null) {
                $gateway_setting = $gateways;
            } else {
                $gateway_setting = $request;
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
                if ($gateway_setting->id === 'braintree' || $gateway_setting->id === 'paypal_express') {
                    if (isset($payment_action['Authorization']) && !empty($payment_action['Authorization'])) {
                        $gateway_setting->payment_action = 'Authorization';
                    } elseif (isset($payment_action['Sale']) && !empty($payment_action['Sale'])) {
                        $gateway_setting->payment_action = 'Sale';
                    }
                } elseif ($gateway_setting->id === 'paypal_pro_payflow') {
                    if (isset($payment_action['Authorization']) && !empty($payment_action['Authorization'])) {
                        $gateway_setting->payment_action = 'Authorization';
                        if ($payment_action['Authorization'] == 'Full Authorization') {
                            $gateway_setting->payment_action_authorization = 'Full Authorization';
                        } elseif ($payment_action['Authorization'] == 'Card Verification') {
                            $gateway_setting->payment_action_authorization = 'Card Verification';
                        }
                    } elseif (isset($payment_action['Sale']) && !empty($payment_action['Sale'])) {
                        $gateway_setting->payment_action = 'Sale';
                    }
                }
            }
        }

        public function angelleye_add_deactivation_form() {
            $current_screen = get_current_screen();
            if ('plugins' !== $current_screen->id && 'plugins-network' !== $current_screen->id) {
                return;
            }
            include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/template/deactivation-form.php');
        }

        public function angelleye_handle_plugin_deactivation_request() {
            $log_url = wc_clean($_SERVER['HTTP_HOST']);
            $log_plugin_id = 1;
            $web_services_url = 'http://www.angelleye.com/web-services/wordpress/update-plugin-status.php';
            $request_url = add_query_arg(array(
                'url' => $log_url,
                'plugin_id' => $log_plugin_id,
                'activation_status' => 0,
                'reason' => wc_clean($_POST['reason']),
                'reason_details' => wc_clean($_POST['reason_details']),
                    ), $web_services_url);
            $response = wp_remote_request($request_url);
            update_option('angelleye_paypal_woocommerce_submited_feedback', 'yes');
            if (is_wp_error($response)) {
                wp_send_json(wp_remote_retrieve_body($response));
            } else {
                wp_send_json(wp_remote_retrieve_body($response));
            }
        }

        public function load_cartflow_pro_plugin() {
            if (defined('CARTFLOWS_PRO_FILE')) {
                include_once plugin_dir_path(__FILE__) . 'angelleye-includes/cartflows-pro/class-angelleye-cartflow-pro-helper.php';
            }
        }

        public static function angelleye_delete_payment_method_action() {
            global $wp, $woocommerce;
            if (isset($wp->query_vars['delete-payment-method'])) {
                wc_nocache_headers();
                $token_id = absint($wp->query_vars['delete-payment-method']);
                $token = WC_Payment_Tokens::get($token_id);
                if (!is_null($token) && $token->get_gateway_id() === 'braintree' && get_current_user_id() == $token->get_user_id() && isset($_REQUEST['_wpnonce']) || true === wp_verify_nonce(wp_unslash($_REQUEST['_wpnonce']), 'delete-payment-method-' . $token_id)) {
                    try {
                        $gateways = $woocommerce->payment_gateways->payment_gateways();
                        $gateways['braintree']->angelleye_braintree_lib();
                        $token_value = $token->get_token();
                        $gateways['braintree']->braintree_gateway->paymentMethod()->delete($token_value);
                    } catch (\Braintree\Exception\NotFound $e) {
                        $gateways['braintree']->add_log("Braintree_PaymentMethod::delete Braintree_Exception_NotFound: " . $e->getMessage());
                    } catch (\Braintree\Exception\Authentication $e) {
                        $gateways['braintree']->add_log("Braintree_ClientToken::generate Exception: API keys are incorrect, Please double-check that you haven't accidentally tried to use your sandbox keys in production or vice-versa.");
                    } catch (\Braintree\Exception\Authorization $e) {
                        $gateways['braintree']->add_log("Braintree_ClientToken::generate Exception: The API key that you're using is not authorized to perform the attempted action according to the role assigned to the user who owns the API key.");
                    } catch (\Braintree\Exception\ServiceUnavailable $e) {
                        $gateways['braintree']->add_log("Braintree_Exception_ServiceUnavailable: Request times out.");
                    } catch (\Braintree\Exception\ServerError $e) {
                        $gateways['braintree']->add_log("Braintree_Exception_ServerError" . $e->getMessage());
                    } catch (\Braintree\Exception\SSLCertificate $e) {
                        $gateways['braintree']->add_log("Braintree_Exception_SSLCertificate" . $e->getMessage());
                    } catch (Exception $ex) {
                        $gateways['braintree']->add_log("Exception" . $ex->getMessage());
                    }
                }
            }
        }

        public function angelleye_synce_braintree_save_payment_methods($list, $customer_id) {
            global $wp, $woocommerce;
            try {
                $gateways = $woocommerce->payment_gateways->payment_gateways();
                $gateways['braintree']->angelleye_braintree_lib();
                if (!empty($gateways['braintree'])) {
                    if ($gateways['braintree']->enable_tokenized_payments == 'yes') {
                        $payment_tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, 'braintree');
                        foreach ($payment_tokens as $payment_token) {
                            $token_value = $payment_token->get_token();
                            try {
                                if (!empty($gateways['braintree']->braintree_gateway)) {
                                    $gateways['braintree']->braintree_gateway->paymentMethod()->find($token_value);
                                }
                            } catch (\Braintree\Exception\NotFound $e) {
                                $gateways['braintree']->add_log("Braintree_PaymentMethod::find Braintree_Exception_NotFound: " . $e->getMessage());
                                WC_Payment_Tokens::delete($payment_token->get_id());
                            } catch (\Braintree\Exception\Authentication $e) {
                                $gateways['braintree']->add_log("Braintree_ClientToken::generate Exception: API keys are incorrect, Please double-check that you haven't accidentally tried to use your sandbox keys in production or vice-versa.");
                            } catch (\Braintree\Exception\Authorization $e) {
                                $gateways['braintree']->add_log("Braintree_ClientToken::generate Exception: The API key that you're using is not authorized to perform the attempted action according to the role assigned to the user who owns the API key.");
                            } catch (\Braintree\Exception\ServiceUnavailable $e) {
                                $gateways['braintree']->add_log("Braintree_Exception_ServiceUnavailable: Request times out.");
                            } catch (\Braintree\Exception\ServerError $e) {
                                $gateways['braintree']->add_log("Braintree_Exception_ServerError" . $e->getMessage());
                            } catch (\Braintree\Exception\SSLCertificate $e) {
                                $gateways['braintree']->add_log("Braintree_Exception_SSLCertificate" . $e->getMessage());
                            } catch (Exception $ex) {
                                $gateways['braintree']->add_log("Exception" . $ex->getMessage());
                            }
                        }
                    }
                }
            } catch (Exception $ex) {
                
            }
            return $list;
        }

        public function angelleye_cc_ui_style() {
            wp_register_style('angelleye-cc-ui', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/css/angelleye-cc-ui.css', array(), VERSION_PFW);
        }

        public function angelleye_pfw_clean_script_tag($tag, $handle, $src) {
            if (in_array($handle, ['jquery', 'wp-i18n', 'wp-hooks'])) {
                $tag = str_replace(['defer="defer"', "defer='defer'", " defer", " async"], '', $tag);
            }
            return $tag;
        }

        public function angelleye_wc_order_statuses($order_statuses) {
            $order_statuses['wc-partial-payment'] = _x('Partially Paid', 'Order status', 'paypal-for-woocommerce');
            return $order_statuses;
        }

        public function angelleye_register_post_status() {
            register_post_status('wc-partial-payment', array(
                'label' => _x('Partially Paid', 'Order status', 'paypal-for-woocommerce'),
                'public' => false,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Partially Paid <span class="count">(%s)</span>', 'Partially Paid <span class="count">(%s)</span>', 'paypal-for-woocommerce'),
            ));
        }

        public function angelleye_woocommerce_email_classes($emails) {
            $emails['WC_Email_Partially_Paid_Order'] = include PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/classes/wc-email-customer-partial-paid-order.php';
            $emails['WC_Email_Admin_Partially_Paid_Order'] = include PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/classes/wc-email-new-partial-paid-order.php';
            $emails['WC_Email_PayPal_Onboard_Seller_Invitation'] = include PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/ppcp-gateway/wc-email-paypal -seller-onboard-invitation.php';
            return $emails;
        }

        public function own_angelleye_woocommerce_email_actions($actions) {
            $actions[] = 'woocommerce_order_status_cancelled_to_partial-payment';
            $actions[] = 'woocommerce_order_status_failed_to_partial-payment';
            $actions[] = 'woocommerce_order_status_on-hold_to_partial-payment';
            $actions[] = 'woocommerce_order_status_pending_to_partial-payment';
            $actions[] = 'woocommerce_order_status_processing_to_partial-payment';
            return $actions;
        }

        public function angelleye_classic_gateway_sub_menu() {
            global $current_section;
            echo '<ul class="subsubsub">';
            $sections = array('paypal_express' => 'PayPal Express Checkout', 'paypal_pro' => 'PayPal Website Payments Pro (DoDirectPayment)', 'paypal_pro_payflow' => 'PayPal Payments Pro 2.0 (PayFlow)', 'paypal_advanced' => 'PayPal Advanced', 'paypal_credit_card_rest' => 'PayPal Credit Card (REST)');
            $array_keys = array_keys($sections);
            foreach ($sections as $id => $label) {
                echo '<li><a href="' . admin_url('admin.php?page=wc-settings&tab=checkout' . '&section=' . sanitize_title($id)) . '" class="' . ($current_section == $id ? 'current' : '') . '">' . $label . '</a> ' . (end($array_keys) == $id ? '' : '|') . ' </li>';
            }
            echo '</ul><br class="clear" />';
        }

        public function angelleye_wcv_save_product($product_id) {
            if (!empty($this->pp_settings['show_on_product_page']) && $this->pp_settings['show_on_product_page'] == 'yes' && !empty($this->pp_settings['enable_newly_products']) && $this->pp_settings['enable_newly_products'] == 'yes') {
                update_post_meta($product_id, '_enable_ec_button', 'yes');
            }
        }

        public function angelleye_include_admin_body_class($classes) {
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
                        $classes .= ' angelleye_' . $payment_method;
                    }
                }
                return $classes;
            } catch (Exception $ex) {
                return $classes;
            }
        }

        public function own_angelleye_pppc_seller_onboard_html($post_id) {
            try {
                if (!class_exists('AngellEYE_PayPal_PPCP_Seller_Onboarding')) {
                    include_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-angelleye-paypal-ppcp-seller-onboarding.php');
                }
                $seller_onboard = AngellEYE_PayPal_PPCP_Seller_Onboarding::instance();
                $seller_onboard->angelleye_ppcp_multi_account_generate_signup_link($post_id);
            } catch (Exception $ex) {
                
            }
        }

        public function angelleye_redirect_to_onboard() {
            $woocommerce_angelleye_ppcp_settings = get_option('woocommerce_angelleye_ppcp_settings', false);
            $displayed_angelleye_onboard_screen = get_option('displayed_angelleye_onboard_screen', false);
            if ($woocommerce_angelleye_ppcp_settings === false && $displayed_angelleye_onboard_screen === false) {
                update_option('displayed_angelleye_onboard_screen', 'yes');
                wp_safe_redirect(admin_url('options-general.php?page=paypal-for-woocommerce&tab=general_settings&gateway=paypal_payment_gateway_products'));
                exit;
            }
        }

        public function load_funnelkit_pro_plugin_compatible_gateways() {
            try {
                if (defined('WFFN_PRO_FILE')) {
                    require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/funnelkit/class-wfocu-paypal-for-wc-gateway-angelleye-ppcp.php';
                    require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/funnelkit/class-wfocu-paypal-for-wc-gateway-angelleye-ppcp-cc.php';
                    if (class_exists('WC_Subscriptions') && function_exists('wcs_create_renewal_order')) {
                        require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/funnelkit/class-upstroke-subscriptions-angelleye-ppcp.php';
                        require_once plugin_dir_path(__FILE__) . 'ppcp-gateway/funnelkit/class-upstroke-subscriptions-angelleye-ppcp-cc.php';
                    }
                }
            } catch (Exception $ex) {
                
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

new AngellEYE_Gateway_Paypal();

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
        require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/checkout-block/angelleye-ppcp-checkout-block.php');
        require_once(PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/checkout-block/angelleye-ppcp-cc-block.php');
        add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new AngellEYE_PPCP_Checkout_Block);
                    $payment_method_registry->register(new AngellEYE_PPCP_CC_Block);
                }
        );
    } catch (Exception $ex) {
        
    }
});