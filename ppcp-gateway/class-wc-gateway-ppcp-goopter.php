<?php

class WC_Gateway_PPCP_Goopter extends WC_Payment_Gateway {
    use WC_Gateway_Base_Goopter;
    use WC_PPCP_Pre_Orders_Trait;
    const PAYMENT_METHOD = 'goopter_ppcp';
    public static $_instance;
    public $settings_fields;
    public $advanced_card_payments;
    public $checkout_disable_smart_button;
    public $minified_version;
    public bool $enable_tokenized_payments;
    public $sandbox;
    public $sandbox_merchant_id;
    public $live_merchant_id;
    public $sandbox_client_id;
    public $sandbox_secret_id;
    public $live_client_id;
    public $live_secret_id;
    public $soft_descriptor;
    public $is_sandbox_third_party_used;
    public $is_sandbox_first_party_used;
    public $is_live_third_party_used;
    public $is_live_first_party_used;
    public $merchant_id;
    public $client_id;
    public $secret_id;
    public $paymentaction;
    public $three_d_secure_contingency;
    public $is_enabled;

    public function __construct() {
        try {
            self::$_instance = $this;
            $this->id = 'goopter_ppcp';
            $this->setup_properties();
            $this->goopter_ppcp_load_class(true);
            $this->init_form_fields();
            $this->init_settings();
            $this->goopter_get_settings();
            $this->goopter_defined_hooks();
            if (goopter_ppcp_has_active_session()) {
                $this->order_button_text = apply_filters('goopter_ppcp_order_review_page_place_order_button_text', __('Complete Order Payment', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'));
            }

            $this->setGatewaySupports();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function setup_properties() {
        $this->icon = apply_filters('woocommerce_goopter_paypal_checkout_icon', 'https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png');
        $this->has_fields = true;
        $this->method_title = apply_filters('goopter_ppcp_gateway_method_title', sprintf('%s - Built by Goopter', GT_PPCP_NAME));
        $this->method_description = __('The easiest one-stop solution for accepting PayPal, Venmo, Debit/Credit Cards with cheaper fees than other processors!', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment');
    }

    public function goopter_get_settings() {
        $this->title = $this->get_option('title', 'PayPal');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
        if (isset($_GET['page']) && 'wc-settings' === $_GET['page'] && isset($_GET['tab']) && 'checkout' === $_GET['tab']) {
            $this->title = sprintf('%s - Built by Goopter', GT_PPCP_NAME);
        }
        $this->description = $this->get_option('description', '');
        $this->enabled = $this->get_option('enabled', 'no');
        $this->sandbox = 'yes' === $this->get_option('testmode', 'no');
        $this->sandbox_merchant_id = $this->get_option('sandbox_merchant_id', '');
        $this->live_merchant_id = $this->get_option('live_merchant_id', '');
        $this->checkout_disable_smart_button = 'yes' === $this->get_option('checkout_disable_smart_button', 'no');
        $this->sandbox_client_id = $this->get_option('sandbox_client_id', '');
        $this->sandbox_secret_id = $this->get_option('sandbox_api_secret', '');
        $this->live_client_id = $this->get_option('api_client_id', '');
        $this->live_secret_id = $this->get_option('api_secret', '');
        $this->soft_descriptor = $this->get_option('soft_descriptor', substr(get_bloginfo('name'), 0, 21));
        if (!empty($this->sandbox_client_id) && !empty($this->sandbox_secret_id)) {
            $this->is_sandbox_first_party_used = 'yes';
            $this->is_sandbox_third_party_used = 'no';
        } else if (!empty($this->sandbox_merchant_id)) {
            $this->is_sandbox_third_party_used = 'yes';
            $this->is_sandbox_first_party_used = 'no';
        } else {
            $this->is_sandbox_third_party_used = 'no';
            $this->is_sandbox_first_party_used = 'no';
        }
        if (!empty($this->live_client_id) && !empty($this->live_secret_id)) {
            $this->is_live_first_party_used = 'yes';
            $this->is_live_third_party_used = 'no';
        } else if (!empty($this->live_merchant_id)) {
            $this->is_live_third_party_used = 'yes';
            $this->is_live_first_party_used = 'no';
        } else {
            $this->is_live_third_party_used = 'no';
            $this->is_live_first_party_used = 'no';
        }
        if ($this->sandbox) {
            $this->merchant_id = $this->get_option('sandbox_merchant_id', '');
            $this->client_id = $this->sandbox_client_id;
            $this->secret_id = $this->sandbox_secret_id;
        } else {
            $this->merchant_id = $this->get_option('live_merchant_id', '');
            $this->client_id = $this->live_client_id;
            $this->secret_id = $this->live_secret_id;
        }
        $this->paymentaction = $this->get_option('paymentaction', 'capture');
        $this->advanced_card_payments = 'yes' === $this->get_option('enable_advanced_card_payments', 'no');
        $this->enable_tokenized_payments = 'yes' === $this->get_option('enable_tokenized_payments', 'no');
        $this->three_d_secure_contingency = $this->get_option('3d_secure_contingency', 'SCA_WHEN_REQUIRED');
        $this->is_enabled = 'yes' === $this->get_option('enabled', 'no');
        $this->minified_version = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '' : '.min';
    }

    public function is_available() {
        return $this->is_enabled == true && $this->is_credentials_set();
    }

    public function goopter_defined_hooks() {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        if (!has_action('woocommerce_admin_order_totals_after_total', array('WC_Gateway_PPCP_Goopter', 'goopter_ppcp_display_order_fee'))) {
            add_action('woocommerce_admin_order_totals_after_total', array('WC_Gateway_PPCP_Goopter', 'goopter_ppcp_display_order_fee'));
        }
        if ($this->enable_tokenized_payments === false) {
            add_filter('woocommerce_payment_gateways_renewal_support_status_html', array($this, 'payment_gateways_support_tooltip'), 10, 1);
        }
    }

    public function process_admin_options() {
        delete_transient(GT_FEE);
        $cacheCleared = false;
        $clearCache = function () {
            delete_option('gt_apple_pay_domain_reg_retries');
            delete_transient('gt_seller_onboarding_status');
            delete_transient('goopter_apple_pay_domain_list_cache');
            return true;
        };
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
        if (!isset($_POST['woocommerce_goopter_ppcp_enabled']) || $_POST['woocommerce_goopter_ppcp_enabled'] == "0") {
            // run the automatic domain remove feature
            try {
                Goopter_PayPal_PPCP_Apple_Pay_Configurations::autoUnRegisterDomain();
                wp_remote_request(PAYPAL_FOR_WOOCOMMERCE_PPCP_GOOPTER_WEB_SERVICE . '?removed_url=' . site_url());
            } catch (Exception $exception) {
                $this->api_log->log("The exception was created on line: " . $exception->getFile() . ' ' .$exception->getLine(), 'error');
                $this->api_log->log($exception->getMessage(), 'error');
            }
            $cacheCleared = $clearCache();
        } else {
            $oldSandboxMode = $this->get_option('testmode', 'no') == 'yes';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
            $newSandboxMode = isset($_POST['woocommerce_goopter_ppcp_testmode']);
            if ($oldSandboxMode != $newSandboxMode) {
                $cacheCleared = $clearCache();
            }
        }
        parent::process_admin_options();
        if ($cacheCleared) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp'));
            die;
        }
    }

    public function init_form_fields() {
        try {
            $this->form_fields = $this->setting_obj_fields;
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function payment_fields() {
        if ($this->supports('tokenization')) {
            $this->tokenization_script();
        }
        goopter_ppcp_add_css_js();
        $description = $this->get_description();
        if ($description) {
            echo wp_kses_post(wpautop($description));
        }
        if (is_checkout() && goopter_ppcp_get_order_total() === 0) {
            if (goopter_ppcp_get_order_total() === 0 && goopter_ppcp_is_cart_subscription() === true || goopter_ppcp_is_subs_change_payment() === true) {
                if (count($this->get_tokens()) > 0) {
                    $this->saved_payment_methods();
                }
            }
        } elseif (goopter_ppcp_is_subs_change_payment() === true) {
            if (count($this->get_tokens()) > 0) {
                $this->saved_payment_methods();
            }
        }

        if ($this->checkout_disable_smart_button === false && goopter_ppcp_get_order_total() > 0 && goopter_ppcp_is_subs_change_payment() === false) {
            do_action('goopter_ppcp_display_paypal_button_checkout_page');
            if (goopter_ppcp_is_cart_subscription() === false && $this->enable_tokenized_payments) {
                if ($this->supports('tokenization') && is_account_page() === false) {
                    $html = '<ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods" data-count="">';
                    $html .= '</ul>';
                    echo wp_kses_post($html);
                    $this->save_payment_method_checkbox();
                }
            }
        }
    }

    public function form() {
        wp_enqueue_script('wc-credit-card-form');
        $fields = array();
        $cvc_field = '<div class="form-row form-row-last">
                        <label for="' . esc_attr($this->id) . '-card-cvc">' . apply_filters('cc_form_label_card_code', __('Card Security Code', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'), $this->id) . ' </label>
                        <div id="' . esc_attr($this->id) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc hosted-field-braintree"></div>
                    </div>';
        $default_fields = array(
            'card-number-field' => '<div class="form-row form-row-wide">
                        <label for="' . esc_attr($this->id) . '-card-number">' . apply_filters('cc_form_label_card_number', __('Card number', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'), $this->id) . '</label>
                        <div id="' . esc_attr($this->id) . '-card-number"  class="input-text wc-credit-card-form-card-number hosted-field-braintree"></div>
                    </div>',
            'card-expiry-field' => '<div class="form-row form-row-first">
                        <label for="' . esc_attr($this->id) . '-card-expiry">' . apply_filters('cc_form_label_expiry', __('Expiration Date', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'), $this->id) . ' </label>
                        <div id="' . esc_attr($this->id) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry hosted-field-braintree"></div>
                    </div>',
        );
        if (!$this->supports('credit_card_form_cvc_on_saved_method')) {
            $default_fields['card-cvc-field'] = $cvc_field;
        }
        $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form' >
            <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
            <?php
            foreach ($fields as $field) {
                echo wp_kses_post($field);
            }
            ?>
            <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
        <?php
        if ($this->supports('credit_card_form_cvc_on_saved_method')) {
            echo '<fieldset>' . wp_kses_post($cvc_field) . '</fieldset>';
        }
    }

    public function is_valid_for_use() {
        return in_array(
                get_woocommerce_currency(), apply_filters(
                        'woocommerce_paypal_supported_currencies', array('AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB', 'RUB', 'INR')
                ), true
        );
    }

    public function enqueue_scripts() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
        if (isset($_GET['section']) && 'goopter_ppcp' === $_GET['section']) {
            wp_enqueue_style('wc-gateway-ppcp-goopter-settings-css', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/css/goopter-ppcp-gateway-admin' . $this->minified_version . '.css', array(), VERSION_PFW, 'all');
            wp_enqueue_script('wc-gateway-ppcp-goopter-settings', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/js/wc-gateway-ppcp-goopter-settings' . $this->minified_version . '.js', array('jquery', 'wp-color-picker'), ($this->minified_version ? VERSION_PFW : time()), true);
            wp_localize_script('wc-gateway-ppcp-goopter-settings', 'ppcp_goopter_param', array(
                'goopter_ppcp_is_local_server' => ( goopter_ppcp_is_local_server() == true) ? 'yes' : 'no',
                'goopter_ppcp_onboarding_endpoint' => WC_AJAX::get_endpoint('ppcp_login_seller'),
                'goopter_ppcp_onboarding_endpoint_nonce' => wp_create_nonce('ppcp_login_seller'),
                'is_sandbox_first_party_used' => $this->is_sandbox_first_party_used,
                'is_sandbox_third_party_used' => $this->is_sandbox_third_party_used,
                'is_live_first_party_used' => $this->is_live_first_party_used,
                'is_live_third_party_used' => $this->is_live_third_party_used,
                'is_advanced_card_payments' => ($this->dcc_applies->for_country_currency() === false) ? 'no' : 'yes',
                'woocommerce_enable_guest_checkout' => get_option('woocommerce_enable_guest_checkout', 'yes'),
                'disable_terms' => ( apply_filters('woocommerce_checkout_show_terms', true) && function_exists('wc_terms_and_conditions_checkbox_enabled') && wc_terms_and_conditions_checkbox_enabled() && get_option('woocommerce_enable_guest_checkout', 'yes') === 'yes') ? 'yes' : 'no'
                    )
            );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
        if (isset($_GET['page']) && 'wc-settings' === $_GET['page'] && isset($_GET['tab']) && 'checkout' === $_GET['tab']) {
            wp_enqueue_script('wc-gateway-ppcp-goopter-settings-list', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/js/wc-gateway-ppcp-goopter-settings-list' . $this->minified_version . '.js', array('jquery'), VERSION_PFW, true);
        }
    }

    public function generate_goopter_ppcp_text_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'goopter_ppcp_text') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <div class="ppcp_paypal_connection_image">
                        <div class="ppcp_paypal_connection_image_status">
                            <img src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/images/ppcp_check_mark_status.png'); ?>" width="65" height="65">
                        </div>
                    </div>
                    <div class="ppcp_paypal_connection">
                        <div class="ppcp_paypal_connection_status">
                            <h3><?php // Translators: %s is the name of the PayPal service (e.g., PayPal Advanced).
                            echo sprintf(esc_html__('Congratulations, %s is Connected!', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'), esc_html(GT_PPCP_NAME)); ?></h3>
                        </div>
                    </div>
                    <button type="button" class="button goopter-ppcp-disconnect"><?php echo esc_html__('Disconnect', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></button>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_goopter_ppcp_onboarding_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'goopter_ppcp_onboarding') {
            $field_key = $this->get_field_key($field_key);
            $testmode = ( $data['mode'] === 'live' ) ? 'no' : 'yes';
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?><?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <?php
                    if (($this->is_live_first_party_used !== 'yes' && $this->is_live_third_party_used !== 'yes' && $testmode === 'no') || ($this->is_sandbox_first_party_used !== 'yes' && $this->is_sandbox_third_party_used !== 'yes' && $testmode === 'yes')) {
                        $setup_url = add_query_arg(array('testmode' => $testmode, 'utm_nooverride' => '1'), untrailingslashit(admin_url('options-general.php?page=goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment&tab=general_settings&gateway=paypal_payment_gateway_products')));
                        ?>
                        <a class="button-primary" href="<?php echo esc_url($setup_url); ?>"><?php echo esc_html__('Go To Setup', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></a>
                        <?php
                    }
                    ?>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_copy_text_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?><?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="text" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo wp_kses_post($this->get_custom_attribute_html($data)); ?> />
                    <button type="button" class="button-secondary <?php echo esc_attr($data['button_class']); ?>" data-tip="Copied!">Copy</button>
                    <?php echo wp_kses_post($this->get_description_html($data)); // WPCS: XSS ok.         ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function process_payment($woo_order_id) {
        try {
            $order = wc_get_order($woo_order_id);
            if($this->has_pre_order($woo_order_id) && $this->is_paypal_vault_used_for_pre_order() && $this->has_pre_order_charged_upon_release($woo_order_id)) {
                return $this->goopter_ppcp_process_free_signup_with_free_trial($woo_order_id);
            }
            $this->paymentaction = apply_filters('goopter_ppcp_paymentaction', $this->paymentaction, $woo_order_id);
            $order = wc_get_order($woo_order_id);
            $goopter_ppcp_paypal_order_id = Goopter_Session_Manager::get('paypal_order_id');
            $goopter_ppcp_payment_method_title = Goopter_Session_Manager::get('payment_method_title');
            $goopter_ppcp_used_payment_method = Goopter_Session_Manager::get('used_payment_method');
            $order->update_meta_data('_goopter_ppcp_used_payment_method', $goopter_ppcp_used_payment_method);
            if (!empty($goopter_ppcp_payment_method_title)) {
                $order->set_payment_method_title($goopter_ppcp_payment_method_title);
            }
            $payment_method_id = Goopter_Session_Manager::get('payment_method_id', false);
            if (!empty($payment_method_id)) {
                $order->set_payment_method($payment_method_id);
            }
            $order->save();
            $saved_tokens = ['wc-goopter_ppcp_apple_pay-payment-token', 'wc-goopter_ppcp-payment-token'];
            $token_id = null;
            foreach ($saved_tokens as $saved_token) {
                // phpcs:disable WordPress.Security.NonceVerification.Missing -- no security issue
                if (!empty($_POST[$saved_token]) && $_POST[$saved_token] !== 'new') {
                    $token_id = wc_clean(sanitize_text_field(wp_unslash($_POST[$saved_token])));
                }
                // phpcs:enable WordPress.Security.NonceVerification.Missing -- no security issue
            }

            if (!empty($token_id)) {
                $token = WC_Payment_Tokens::get($token_id);
                $used_payment_method = get_metadata('payment_token', $token_id, '_goopter_ppcp_used_payment_method', true);
                $order->update_meta_data('_goopter_ppcp_used_payment_method', $used_payment_method);
                $order->update_meta_data('_payment_tokens_id', $token->get_token());
                // CHECKME Here the environment key spelling is wrong, check if we can fix this, though it will break previous shop orders or we run migration
                $order->update_meta_data('_enviorment', ($this->sandbox) ? 'sandbox' : 'live');
                $order->update_meta_data('_enviorment', '_paymentaction', $this->paymentaction);
                $order->save();
                goopter_ppcp_add_used_payment_method_name_to_subscription($woo_order_id);
                $this->payment_request->save_payment_token($order, $token->get_token());
                $is_success = $this->payment_request->goopter_ppcp_capture_order_using_payment_method_token($woo_order_id);
                if ($is_success) {
                    WC()->cart->empty_cart();
                    Goopter_Session_Manager::clear();
                    if (ob_get_length()) {
                        ob_end_clean();
                    }
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
                    );
                }
                exit();
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
                if (isset($_GET['from']) && 'checkout' === $_GET['from']) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
                    Goopter_Session_Manager::set('checkout_post', isset($_POST) ? $_POST : false);
                    $this->payment_request->goopter_ppcp_create_order_request($woo_order_id);
                    exit();
                } elseif (!empty($goopter_ppcp_paypal_order_id)) {
                    $order = wc_get_order($woo_order_id);
                    if ($this->paymentaction === 'capture') {
                        $is_success = $this->payment_request->goopter_ppcp_order_capture_request($woo_order_id);
                    } else {
                        $is_success = $this->payment_request->goopter_ppcp_order_auth_request($woo_order_id);
                    }
                    $order->update_meta_data('_paymentaction', $this->paymentaction);
                    $order->update_meta_data('_enviorment', ($this->sandbox) ? 'sandbox' : 'live');
                    $order->save();
                    if ($is_success) {
                        WC()->cart->empty_cart();
                        Goopter_Session_Manager::clear();
                        if (ob_get_length()) {
                            ob_end_clean();
                        }
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order),
                        );
                    } else {
                        Goopter_Session_Manager::clear();
                        if (ob_get_length()) {
                            ob_end_clean();
                        }
                        return array(
                        'result' => 'success',
                            'redirect' => wc_get_checkout_url()
                        );
                    }
                } elseif ($this->checkout_disable_smart_button === true) {
                    $result = $this->payment_request->goopter_ppcp_regular_create_order_request($woo_order_id);
                    if (ob_get_length()) {
                        ob_end_clean();
                    }
                    return $result;
                } else {
                    $result = $this->payment_request->goopter_ppcp_regular_create_order_request($woo_order_id, $return_url = true);
                    if (ob_get_length()) {
                        ob_end_clean();
                    }
                    return $result;
                }
            }
        } catch (Exception $ex) {

        }
    }

    public function get_title() {
        try {
            $payment_method_title = '';
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- no security issue
            if (isset($_GET['post'])) {
                $theorder = wc_get_order(sanitize_text_field(wp_unslash($_GET['post'])));
                if ($theorder) {
                    $payment_method_title = $theorder->get_payment_method_title();
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended -- no security issue
            if (!empty($payment_method_title)) {
                return $payment_method_title;
            } else {
                return parent::get_title();
            }
        } catch (Exception $ex) {

        }
    }

    public function get_transaction_url($order) {
        $enviorment = goopter_ppcp_get_post_meta($order, '_enviorment', true);
        if ($enviorment === 'sandbox') {
            $this->view_transaction_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        } else {
            $this->view_transaction_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        }
        return parent::get_transaction_url($order);
    }

    public function can_refund_order($order) {
        $parent_return = parent::can_refund_order($order);
        if($parent_return === false) {
            return false;
        }
        $has_api_creds = false;
        if ($this->is_credentials_set()) {
            $has_api_creds = true;
        }
        return $order && $order->get_transaction_id() && $has_api_creds;
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        if ($amount <= 0) {
            return new WP_Error('error', __('Invalid refund amount', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'));
        }
        
        $order = wc_get_order($order_id);
        if (apply_filters('goopter_is_ppcp_parallel_payment_not_used', true, $order_id)) {
            if($order && $this->can_refund_order($order) && goopter_ppcp_order_item_meta_key_exists($order, '_ppcp_capture_details')) {
                $capture_data_list = $this->payment_request->goopter_ppcp_prepare_refund_request_data_for_capture($order, $amount);
                if(empty($capture_data_list)) {
                    throw new Exception( esc_html__( 'No Capture transactions available for refund.', 'woocommerce' ) );
                }
                $failed_result_count = 0;
                $successful_transaction = 0;
                foreach ($capture_data_list as $item_id => $capture_data) {
                    foreach ($capture_data as $transaction_id => $amount) {
                        if ($this->payment_request->goopter_ppcp_refund_capture_order($order_id, $amount, $reason, $transaction_id, $item_id)) {
                            $successful_transaction++;
                        } else {
                            $failed_result_count++;
                        }
                    }
                }
                if($failed_result_count > 0) {
                    return false;
                }
                return true;
            } else {
                if (!$this->can_refund_order($order)) {
                    return new WP_Error('error', __('Refund failed.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'));
                }
                $transaction_id = $order->get_transaction_id();
                $bool = $this->payment_request->goopter_ppcp_refund_order($order_id, $amount, $reason, $transaction_id);
                return $bool;
            }
        } else {
            return apply_filters('goopter_is_ppcp_parallel_payment_handle', true, $order_id, $this);
        }
    }

    public static function goopter_ppcp_display_order_fee($order_id) {
        $order = wc_get_order($order_id);
        $payment_method = $order->get_payment_method();
        if ('goopter_ppcp' !== $payment_method) {
            return false;
        }
        $payment_method = version_compare(WC_VERSION, '3.0', '<') ? $order->payment_method : $order->get_payment_method();
        if ('on-hold' === $order->get_status()) {
            return false;
        }

        $fee = goopter_ppcp_get_post_meta($order, '_paypal_fee', true);
        $currency = goopter_ppcp_get_post_meta($order, '_paypal_fee_currency_code', true);
        if ($order->get_status() == 'refunded') {
            return true;
        }
        if($fee < 0.1) {
            return;
        }
        ?>
        <tr class="paypal-fee-tr">
            <td class="label paypal-fee">
                <?php echo wp_kses_post(wc_help_tip(__('This represents the fee PayPal collects for the transaction.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'))); ?>
                <?php esc_html_e('PayPal Fee:', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
                -&nbsp;<?php echo wp_kses_post(wc_price($fee, array('currency' => $currency))); ?>
            </td>
        </tr>
        <?php
    }

    public function get_icon() {
        $icon = $this->icon ? '<img src="' . WC_HTTPS::force_https_url($this->icon) . '" alt="' . esc_attr($this->get_title()) . '" />' : '';
        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function goopter_ppcp_admin_notices() {
        $is_saller_onboarding_done = false;
        $is_saller_onboarding_failed = false;
        $onboarding_success_message = sprintf(esc_html__('Your PayPal account has been connected successfully and you are ready to rock!', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
        if (false !== get_transient('goopter_ppcp_sandbox_seller_onboarding_process_done')) {
            $is_saller_onboarding_done = true;
            delete_transient('goopter_ppcp_sandbox_seller_onboarding_process_done');
        } elseif (false !== get_transient('goopter_ppcp_live_seller_onboarding_process_done')) {
            $is_saller_onboarding_done = true;
            delete_transient('goopter_ppcp_live_seller_onboarding_process_done');
        }

        if (false !== get_transient('goopter_ppcp_applepay_onboarding_done')) {
            $is_saller_onboarding_done = true;
            $onboarding_success_message = "Apple Pay feature has been enabled successfully.";
            delete_transient('goopter_ppcp_applepay_onboarding_done');
        }

        if (false !== get_transient('goopter_ppcp_googlepay_onboarding_done')) {
            $is_saller_onboarding_done = true;
            $onboarding_success_message = "Google Pay feature has been enabled successfully.";
            delete_transient('goopter_ppcp_googlepay_onboarding_done');
        }

        if ($is_saller_onboarding_done) {
            echo '<div class="notice notice-success goopter-notice is-dismissible" id="ppcp_success_notice_onboarding" style="display:none;">'
            . '<div class="goopter-notice-logo-original">'
            . '<div class="ppcp_success_logo"><img src="' . esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/images/ppcp_check_mark.png') . '" width="65" height="65"></div>'
            . '</div>'
            . '<div class="goopter-notice-message">'
            . '<h3>' . esc_html($onboarding_success_message) . '</h3>'
            . '</div>'
            . '</div>';
        } else {
            if (false !== get_transient('goopter_ppcp_sandbox_seller_onboarding_process_failed')) {
                $is_saller_onboarding_failed = true;
                delete_transient('goopter_ppcp_sandbox_seller_onboarding_process_failed');
            } elseif (false !== get_transient('goopter_ppcp_live_seller_onboarding_process_failed')) {
                $is_saller_onboarding_failed = true;
                delete_transient('goopter_ppcp_live_seller_onboarding_process_failed');
            }
            if ($is_saller_onboarding_failed) {
                echo '<div class="notice notice-error is-dismissible">'
                . '<p>We could not properly connect to PayPal. Please reload the page to continue.</p>'
                . '</div>';
            }
        }
        if (($this->is_live_first_party_used === 'yes' || $this->is_live_third_party_used === 'yes') || ($this->is_sandbox_first_party_used === 'yes' || $this->is_sandbox_third_party_used === 'yes')) {
            return false;
        }
        $message = sprintf(
            // Translators: %1$s is the PayPal service name, %2$s is the URL to connect the account.
            __('%1$s is almost ready. To get started, <a href="%2$s">connect your account</a>.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'),
            esc_html(GT_PPCP_NAME),
            esc_url(admin_url('options-general.php?page=goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment&tab=general_settings&gateway=paypal_payment_gateway_products'))
        );
        // $message = sprintf(__('%s is almost ready. To get started, <a href="%1$s">connect your account</a>.','goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'),GT_PPCP_NAME,admin_url('options-general.php?page=goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment&tab=general_settings&gateway=paypal_payment_gateway_products'));
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }

    public function process_subscription_payment($order, $amount_to_charge) {
        try {
            $order_id = $order->get_id();
            $this->payment_request->goopter_ppcp_capture_order_using_payment_method_token($order_id);
        } catch (Exception $ex) {

        }
    }

    public function subscription_change_payment($order_id) {
        try {
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- no security issue
            if ((!empty($_POST['wc-goopter_ppcp-payment-token']) && $_POST['wc-goopter_ppcp-payment-token'] != 'new')) {
                $order = wc_get_order($order_id);
                $token_id = wc_clean(sanitize_text_field(wp_unslash($_POST['wc-goopter_ppcp-payment-token'])));
                $token = WC_Payment_Tokens::get($token_id);
                $used_payment_method = get_metadata('payment_token', $token_id, '_goopter_ppcp_used_payment_method', true);
                $order->update_meta_data('_goopter_ppcp_used_payment_method', $used_payment_method);
                $order->save();
                $this->payment_request->save_payment_token($order, $token->get_token());
                return array(
                    'result' => 'success',
                    'redirect' => goopter_ppcp_get_view_sub_order_url($order_id)
                );
            } else {
                return $this->payment_request->goopter_ppcp_paypal_setup_tokens_sub_change_payment($order_id);
            }
        } catch (Exception $ex) {

        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing -- no security issue
    }

    public function free_signup_order_payment($order_id) {
        try {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
            if ((!empty($_POST['wc-goopter_ppcp-payment-token']) && $_POST['wc-goopter_ppcp-payment-token'] != 'new')) {
                $order = wc_get_order($order_id);
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
                $token_id = wc_clean(sanitize_text_field(wp_unslash($_POST['wc-goopter_ppcp-payment-token'])));
                $token = WC_Payment_Tokens::get($token_id);
                $order->payment_complete($token->get_token());
                $this->payment_request->save_payment_token($order, $token->get_token());
                WC()->cart->empty_cart();
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }
        } catch (Exception $ex) {

        }
    }

    public function add_payment_method() {
        try {
            return $this->payment_request->goopter_ppcp_paypal_setup_tokens();
        } catch (Exception $ex) {

        }
    }

    public function goopter_ppcp_process_free_signup_with_free_trial($order_id) {
        try {
            return $this->payment_request->goopter_ppcp_paypal_setup_tokens_free_signup_with_free_trial($order_id);
        } catch (Exception $ex) {

        }
    }

    public function save_payment_method_checkbox() {
        $html = sprintf(
                '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
                esc_attr($this->id),
                esc_html__('Save payment method to my account.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment')
        );

        echo apply_filters('woocommerce_payment_gateway_save_new_payment_method_option_html', $html, $this); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function get_saved_payment_method_option_html($token) {
        try {
            $goopter_ppcp_used_payment_method = get_metadata('payment_token', $token->get_id(), '_goopter_ppcp_used_payment_method', true);
            if (!empty($goopter_ppcp_used_payment_method)) {
                if ($goopter_ppcp_used_payment_method === 'paypal') {
                    $image_url = PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/icon/paypal.png';
                } elseif ($goopter_ppcp_used_payment_method === 'venmo') {
                    $image_url = PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/icon/venmo.png';
                }
                $image_path = '<img class="ppcp_payment_method_icon" src="' . $image_url . '" alt="Credit card">';
                $html = sprintf(
                        '<li class="woocommerce-SavedPaymentMethods-token">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
				<label for="wc-%1$s-payment-token-%2$s">%5$s  %3$s</label>
			</li>',
                        esc_attr($this->id),
                        esc_attr($token->get_id()),
                        esc_html($token->get_card_type()),
                        checked($token->is_default(), true, false),
                        $image_path
                );
                return apply_filters('woocommerce_payment_gateway_get_saved_payment_method_option_html', $html, $token, $this);
            } else {
                parent::get_saved_payment_method_option_html($token);
            }
        } catch (Exception $ex) {
            parent::get_saved_payment_method_option_html($token);
        }
    }

    public function generate_checkbox_enable_paypal_vault_html($key, $data) {
        if (isset($data['type']) && $data['type'] === 'checkbox_enable_paypal_vault') {
            $testmode = $this->sandbox ? 'yes' : 'no';
            $field_key = $this->get_field_key($key);
            $defaults = array(
                'title' => '',
                'label' => '',
                'disabled' => false,
                'class' => '',
                'css' => '',
                'type' => 'text',
                'desc_tip' => false,
                'description' => '',
                'custom_attributes' => array(),
            );
            $data = wp_parse_args($data, $defaults);
            if (!$data['label']) {
                $data['label'] = $data['title'];
            }
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <label for="<?php echo esc_attr($field_key); ?>">
                            <input <?php disabled($data['disabled'], true); ?> class="<?php echo esc_attr($data['class']); ?>" type="checkbox" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="1" <?php checked($this->get_option($key), 'yes'); ?> <?php echo wp_kses_post($this->get_custom_attribute_html($data)); ?> /> <?php echo wp_kses_post($data['label']); ?>
                            <?php
                            if (isset($data['is_paypal_vault_enable']) && true === $data['is_paypal_vault_enable']) {
                                ?>
                                <img src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/images/ppcp_check_mark_status.png'); ?>" width="25" height="25" style="display: inline-block;margin: 0 5px -10px 10px;">
                                <b><?php echo esc_html__('Vault is Connected!', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></b>
                            <?php } ?>
                        </label>
                        <?php
                        echo wp_kses_post($this->get_description_html($data));
                        if (isset($data['need_to_display_paypal_vault_onboard_button']) && true === $data['need_to_display_paypal_vault_onboard_button']) {
                            $signup_link = $this->goopter_get_signup_link($testmode);
                            if ($signup_link) {
                                $args = array(
                                    'displayMode' => 'minibrowser',
                                );
                                $url = add_query_arg($args, $signup_link);
                                ?>
                                <br>
                                <a target="_blank" class="wplk-button button-primary" id="<?php echo esc_attr('wplk-button'); ?>" data-paypal-onboard-complete="onboardingCallback" href="<?php echo esc_url($url); ?>" data-paypal-button="true"><?php echo esc_html__('Activate PayPal Vault', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></a>
                                <?php
                                $script_url = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
                                ?>
                                <script type="text/javascript">
                                    document.querySelectorAll('[data-paypal-onboard-complete=onboardingCallback]').forEach((element) => {
                                        element.addEventListener('click', (e) => {
                                            if ('undefined' === typeof PAYPAL) {
                                                e.preventDefault();
                                                alert('PayPal');
                                            }
                                        });
                                    });</script>
                                <script id="paypal-js" src="<?php echo esc_url($script_url); ?>"></script> <?php
                            } else {
                                echo esc_html__('We could not properly connect to PayPal', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment');
                            }
                        }
                        ?>
                    </fieldset>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_checkbox_enable_paypal_apple_pay_html($key, $data) {
        if (isset($data['type']) && $data['type'] === 'checkbox_enable_paypal_apple_pay') {
            $testmode = $this->sandbox ? 'yes' : 'no';
            $field_key = $this->get_field_key($key);
            $defaults = array(
                'title' => '',
                'label' => '',
                'disabled' => false,
                'class' => '',
                'css' => '',
                'type' => 'text',
                'desc_tip' => false,
                'description' => '',
                'custom_attributes' => array(),
            );
            $data = wp_parse_args($data, $defaults);
            if (!$data['label']) {
                $data['label'] = $data['title'];
            }
            $is_enabled = $this->get_option($key);
            $is_domain_added_new = false;
            $is_domain_added = $this->get_option('apple_pay_domain_added', null) == 'yes';
            $is_apple_pay_approved = $data['is_apple_pay_approved'] ?? false;
            $is_apple_pay_enabled = $data['is_apple_pay_enable'] ?? false;
            $is_ppcp_connected = $data['is_ppcp_connected'] ?? false;
            $need_to_display_apple_pay_button = $data['need_to_display_apple_pay_button'] ?? false;
            if ($is_apple_pay_approved && $is_apple_pay_enabled) {
                $is_domain_added_new = Goopter_PayPal_PPCP_Apple_Pay_Configurations::autoRegisterDomain($is_domain_added);
            }
            $is_disabled = $data['disabled'] || isset($data['custom_attributes']['disabled']) || !$is_domain_added_new || !$is_apple_pay_approved;

            if ($is_domain_added == null || $is_domain_added_new != $is_domain_added) {
                if ($is_domain_added_new) {
                    $is_domain_added = true;
                    $this->update_option('apple_pay_domain_added', 'yes');
                } else {
                    $is_domain_added = false;
                    $this->update_option('apple_pay_domain_added', 'no');
                }
            }
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <label for="<?php echo esc_attr($field_key); ?>">
                            <input <?php disabled($is_disabled, true); ?> class="<?php echo esc_attr($data['class']); ?>" type="checkbox" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="1" <?php !$is_disabled && checked($is_enabled, 'yes'); ?> <?php echo wp_kses_post($this->get_custom_attribute_html($data)); // WPCS: XSS ok.          ?> /> <?php echo wp_kses_post($data['label']); ?>
                            <?php
                            if ($is_apple_pay_enabled && $is_apple_pay_approved) {
                                ?>
                                <img src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/images/' . ($is_domain_added ? 'ppcp_check_mark_status.png' : 'ppcp_info_icon.png')); ?>" width="25" height="25" style="display: inline-block;margin: 0 5px -10px 10px;">
                                
                                <?php 
                                    $message = $is_domain_added 
                                        ? __('Apple Pay is connected!', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment') 
                                        : __('Register your domain to activate Apple Pay.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment');
                                    echo '<b>' . esc_html($message) . '</b>';
                                ?>
                                
                            <?php } else if ($is_ppcp_connected && !$is_apple_pay_approved && !$need_to_display_apple_pay_button) {
                                ?>
                                <br><br><b style="color:red"><?php echo esc_html__('Apple Pay is currently available in the following countries: AU, AT, BE, BG, CA, CY, CZ, DK, EE, FI, FR, DE, GR, HU, IE, IT, LV, LI, LT, LU, MT, NL, NO, PL, PT, RO, SK, SI, ES, SE, US, GB. PayPal is working to expand this availability to additional countries as quickly as possible.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></b>
                                <?php
                            }?>
                        </label>
                        <?php
                        echo wp_kses_post($this->get_description_html($data));
                        if ($is_apple_pay_approved && $is_apple_pay_enabled && !$is_domain_added) {
                            add_thickbox();
                            ?>
                            <div style="margin-top: 10px"><a title="Apple Pay Domains" href="<?php echo esc_url(add_query_arg(['action' => 'goopter_list_apple_pay_domain'], admin_url('admin-ajax.php'))) ?>" class="thickbox wplk-button button-primary"><?php echo esc_html__('Manage Apple Pay Domains', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></a></div>
                            <?php
                        }
                        ?>
                        <?php
                        if ($need_to_display_apple_pay_button) {
                            $signup_link = $this->goopter_get_signup_link($testmode, 'apple_pay');
                            if ($signup_link) {
                                $args = array(
                                    'displayMode' => 'minibrowser',
                                );
                                $url = add_query_arg($args, $signup_link);
                                ?>
                                <br>
                                <a target="_blank" class="wplk-button button-primary" id="<?php echo esc_attr('wplk-button'); ?>" data-paypal-onboard-complete="onboardingCallback" href="<?php echo esc_url($url); ?>" data-paypal-button="true"><?php echo esc_html__('Activate Apple Pay', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></a>
                            <?php
                            $script_url = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
                            ?>
                                <script type="text/javascript">
									document.querySelectorAll('[data-paypal-onboard-complete=onboardingCallback]').forEach((element) => {
										element.addEventListener('click', (e) => {
											if ('undefined' === typeof PAYPAL) {
												e.preventDefault();
												alert('PayPal');
											}
										});
									});</script>
                                <script id="paypal-js" src="<?php echo esc_url($script_url); ?>"></script> <?php
                            } else {
                                echo esc_html__('We could not properly connect to PayPal', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment');
                            }
                        }
                        ?>
                    </fieldset>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_checkbox_enable_paypal_google_pay_html($key, $data) {
        if (isset($data['type']) && $data['type'] === 'checkbox_enable_paypal_google_pay') {
            $testmode = $this->sandbox ? 'yes' : 'no';
            $field_key = $this->get_field_key($key);
            $defaults = array(
                'title' => '',
                'label' => '',
                'disabled' => false,
                'class' => '',
                'css' => '',
                'type' => 'text',
                'desc_tip' => false,
                'description' => '',
                'custom_attributes' => array(),
            );
            $data = wp_parse_args($data, $defaults);
            if (!$data['label']) {
                $data['label'] = $data['title'];
            }
            $is_google_pay_approved = $data['is_google_pay_approved'] ?? false;
            $is_google_pay_enabled = $data['is_google_pay_enable'] ?? false;
            $is_ppcp_connected = $data['is_ppcp_connected'] ?? false;
            $need_to_display_google_pay_button = $data['need_to_display_google_pay_button'] ?? false;
            $is_disabled = $data['disabled'] || isset($data['custom_attributes']['disabled']) || !$is_google_pay_approved;
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <label for="<?php echo esc_attr($field_key); ?>">
                            <input <?php disabled($is_disabled, true); ?> class="<?php echo esc_attr($data['class']); ?>" type="checkbox" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="1" <?php !$is_disabled && checked($this->get_option($key), 'yes'); ?> <?php echo wp_kses_post($this->get_custom_attribute_html($data)); // WPCS: XSS ok.          ?> /> <?php echo wp_kses_post($data['label']); ?>
                            <?php
                            if ($is_google_pay_enabled && $is_google_pay_approved) {
                                ?>
                                <img src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/images/ppcp_check_mark_status.png'); ?>" width="25" height="25" style="display: inline-block;margin: 0 5px -10px 10px;">
                                <b><?php echo esc_html__('Google Pay is connected!', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></b>
                            <?php } else if ($is_ppcp_connected && !$is_google_pay_approved && !$need_to_display_google_pay_button) {
                                ?>
                                <br><br><b style="color:red"><?php echo esc_html__('Google Pay is currently available in the following countries: AU, AT, BE, BG, CA, CY, CZ, DK, EE, FI, FR, DE, GR, HU, IE, IT, LV, LI, LT, LU, MT, NL, NO, PL, PT, RO, SK, SI, ES, SE, US, GB. PayPal is working to expand this availability to additional countries as quickly as possible.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></b>
                                <?php
                            }?>
                        </label>
                        <?php
                        echo wp_kses_post($this->get_description_html($data));
                        ?>
                        <?php
                        if ($need_to_display_google_pay_button) {
                            $signup_link = $this->goopter_get_signup_link($testmode, 'google_pay');
                            if ($signup_link) {
                                $args = array(
                                    'displayMode' => 'minibrowser',
                                );
                                $url = add_query_arg($args, $signup_link);
                                ?>
                                <br>
                                <a target="_blank" class="wplk-button button-primary" id="<?php echo esc_attr('wplk-button'); ?>" data-paypal-onboard-complete="onboardingCallback" href="<?php echo esc_url($url); ?>" data-paypal-button="true"><?php echo esc_html__('Activate Google Pay', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'); ?></a>
                            <?php
                            $script_url = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
                            ?>
                                <script type="text/javascript">
									document.querySelectorAll('[data-paypal-onboard-complete=onboardingCallback]').forEach((element) => {
										element.addEventListener('click', (e) => {
											if ('undefined' === typeof PAYPAL) {
												e.preventDefault();
												alert('PayPal');
											}
										});
									});</script>
                                <script id="paypal-js" src="<?php echo esc_url($script_url); ?>"></script> <?php
                            } else {
                                echo esc_html__('We could not properly connect to PayPal', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment');
                            }
                        }
                        ?>
                    </fieldset>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function goopter_get_signup_link($testmode, $featureName = 'tokenized_payments') {
        try {
            if (!class_exists('Goopter_PayPal_PPCP_Seller_Onboarding')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-seller-onboarding.php';
            }
            $seller_onboarding = Goopter_PayPal_PPCP_Seller_Onboarding::instance();
            $seller_onboarding->setTestMode($testmode);
            switch ($featureName) {
                case 'apple_pay':
                    $body = $seller_onboarding->ppcp_apple_pay_data();
                    break;
                case 'google_pay':
                    $body = $seller_onboarding->ppcp_google_pay_data();
                default:
                    $body = $seller_onboarding->ppcp_vault_data();
                    break;
            }
            $seller_onboarding_result = $seller_onboarding->goopter_generate_signup_link_with_feature($testmode, 'gateway_settings', $body);
            if (isset($seller_onboarding_result['links'])) {
                foreach ($seller_onboarding_result['links'] as $link) {
                    if (isset($link['rel']) && 'action_url' === $link['rel']) {
                        return $link['href'] ?? false;
                    }
                }
            } else {
                return false;
            }
        } catch (Exception $ex) {

        }
    }

    public function payment_gateways_support_tooltip($status_html) {
        try {
            $status_html = '<span class="status-enabled tips" data-tip="' . esc_attr__('Note: You will need to activate Tokenization in settings to enable Subscription functionality.', 'woocommerce-subscriptions') . '">' . esc_html__('Yes', 'woocommerce-subscriptions') . '</span>';
            return $status_html;
        } catch (Exception $ex) {

        }
    }

    public function validate_checkbox_enable_paypal_vault_field($key, $value) {
        return ! is_null( $value ) ? 'yes' : 'no';
    }

    public function validate_checkbox_enable_paypal_apple_pay_field($key, $value) {
        return ! is_null( $value ) ? 'yes' : 'no';
    }

    public function generate_color_picker_html($key, $data) {
        wp_enqueue_style( 'wp-color-picker' );
        $field_key = $this->get_field_key($key);
        $field_value = $this->get_option($key);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo wp_kses_post($this->get_tooltip_html($data)); ?></label>
            </th>
            <td class="forminp">
                <input name="<?php echo esc_attr($key); ?>" type="text" value="<?php echo esc_attr($field_value); ?>" class="goopter_color_picker <?php echo esc_attr($data['class'] ?? ''); ?>" data-default-color="<?php echo esc_attr($data['default']); ?>" />
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function validate_color_picker_field($key, $value) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : $value;
    }

    public function validate_checkbox_enable_paypal_google_pay_field($key, $value) {
        return ! is_null( $value ) ? 'yes' : 'no';
    }

}