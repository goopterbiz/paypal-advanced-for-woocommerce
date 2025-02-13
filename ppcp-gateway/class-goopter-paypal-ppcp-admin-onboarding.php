<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Admin_Onboarding {

    public $setting_obj;
    public ?Goopter_PayPal_PPCP_Seller_Onboarding $seller_onboarding;
    public $sandbox;
    public $settings_sandbox;
    public $sandbox_merchant_id;
    public $live_merchant_id;
    public $sandbox_client_id;
    public $sandbox_secret_id;
    public $live_client_id;
    public $live_secret_id;
    public $on_board_status = 'NOT_CONNECTED';
    public $result;
    public $dcc_applies;
    protected static $_instance = null;
    public $ppcp_paypal_country = null;
    public $is_sandbox_third_party_used;
    public $is_sandbox_first_party_used;
    public $is_live_first_party_used;
    public $is_live_third_party_used;
    public $paypal_fee_structure;
    public $is_paypal_vault_approved = false;
    public $subscription_support_enabled;
    public $setting_sandbox;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        try {
            $this->goopter_ppcp_load_class();
            $this->paypal_fee_structure = array(
                'US' => array('paypal' => '3.49% + 49¢', 'acc' => '2.59% + 49¢'),
                'UK' => array('paypal' => '2.9% + 30¢', 'acc' => '1.2% + 30¢'),
                'CA' => array('paypal' => '2.9% + 30¢', 'acc' => '2.7% + 30¢'),
                'AU' => array('paypal' => '2.60% + 30¢', 'acc' => '1.75% + 30¢'),
                'FR' => array('paypal' => '2.9% + 35¢', 'acc' => '1.2% + 35¢'),
                'DE' => array('paypal' => '2.99% + 39¢', 'acc' => '2.99% + 39¢'),
                'IT' => array('paypal' => '3,40% + 35¢', 'acc' => '1,20% + 35¢'),
                'ES' => array('paypal' => '2,90% + 05¢', 'acc' => '1,20% + 35¢'),
                'default' => array('paypal' => '3.49% + 49¢', 'acc' => '2.59% + 49¢'),
            );
            if (class_exists('WC_Subscriptions') && function_exists('wcs_create_renewal_order')) {
                $this->subscription_support_enabled = true;
            } else {
                $this->subscription_support_enabled = false;
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_load_class() {
        try {
            if (!class_exists('Goopter_WC_Gateway_PPCP_Settings')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_Seller_Onboarding')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-seller-onboarding.php';
            }
            if (!class_exists('Goopter_PayPal_PPCP_DCC_Validate')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-dcc-validate.php');
            }
            $this->dcc_applies = Goopter_PayPal_PPCP_DCC_Validate::instance();
            $this->setting_obj = Goopter_WC_Gateway_PPCP_Settings::instance();
            $this->seller_onboarding = Goopter_PayPal_PPCP_Seller_Onboarding::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function goopter_ppcp_load_variable() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
        if (isset($_GET['testmode'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
            if (($_GET['testmode'] === 'yes')) {
                $this->sandbox = true;
            } else {
                $this->sandbox = false;
            }
        } else {
            $this->sandbox = 'yes' === $this->setting_obj->get('testmode', 'no');
        }
        $this->setting_sandbox = $this->setting_obj->get('testmode', 'no');
        $this->sandbox_merchant_id = $this->setting_obj->get('sandbox_merchant_id', '');
        $this->live_merchant_id = $this->setting_obj->get('live_merchant_id', '');
        $this->sandbox_client_id = $this->setting_obj->get('sandbox_client_id', '');
        $this->sandbox_secret_id = $this->setting_obj->get('sandbox_api_secret', '');
        $this->live_client_id = $this->setting_obj->get('api_client_id', '');
        $this->live_secret_id = $this->setting_obj->get('api_secret', '');
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
        $this->ppcp_paypal_country = $this->dcc_applies->country();

        $region = wc_get_base_location();
        $this->ppcp_paypal_country = $region['country'];
        if ($this->sandbox) {
            if ($this->is_sandbox_third_party_used === 'no' && $this->is_sandbox_first_party_used === 'no') {
                $this->on_board_status = 'NOT_CONNECTED';
            } elseif ($this->is_sandbox_third_party_used === 'yes') {
                $this->result = $this->seller_onboarding->goopter_track_seller_onboarding_status($this->sandbox_merchant_id);
                if (isset($this->result['country'])) {
                    $this->ppcp_paypal_country = $this->result['country'];
                }
                if (defined('PPCP_PAYPAL_COUNTRY')) {
                    $this->ppcp_paypal_country = PPCP_PAYPAL_COUNTRY;
                }
                if ($this->dcc_applies->for_country_currency($this->ppcp_paypal_country) === false) {
                    $this->on_board_status = 'FULLY_CONNECTED';
                } else {
                    if (goopter_is_acdc_payments_enable($this->result)) {
                        $this->on_board_status = 'FULLY_CONNECTED';
                    } else {
                        $this->on_board_status = 'CONNECTED_BUT_NOT_ACC';
                    }
                    if ($this->seller_onboarding->goopter_ppcp_is_fee_enable($this->result)) {
                        set_transient(GOOPTER_FEE, 'yes', 24 * DAY_IN_SECONDS);
                    } else {
                        set_transient(GOOPTER_FEE, 'no', 24 * DAY_IN_SECONDS);
                    }
                }
                $this->is_paypal_vault_approved = goopter_is_vaulting_enable($this->result);
            } elseif ($this->is_sandbox_first_party_used === 'yes') {
                $this->on_board_status = 'USED_FIRST_PARTY';
            }
        } else {
            if ($this->is_live_third_party_used === 'no' && $this->is_live_first_party_used === 'no') {
                $this->on_board_status = 'NOT_CONNECTED';
            } elseif ($this->is_live_third_party_used === 'yes') {
                $this->result = $this->seller_onboarding->goopter_track_seller_onboarding_status($this->live_merchant_id);
                if (isset($this->result['country'])) {
                    $this->ppcp_paypal_country = $this->result['country'];
                }
                if ($this->dcc_applies->for_country_currency($this->ppcp_paypal_country) === false) {
                    $this->on_board_status = 'FULLY_CONNECTED';
                } else {
                    if (goopter_is_acdc_payments_enable($this->result)) {
                        $this->on_board_status = 'FULLY_CONNECTED';
                    } else {
                        $this->on_board_status = 'CONNECTED_BUT_NOT_ACC';
                    }
                    if ($this->seller_onboarding->goopter_ppcp_is_fee_enable($this->result)) {
                        set_transient(GOOPTER_FEE, 'yes', 24 * DAY_IN_SECONDS);
                    } else {
                        set_transient(GOOPTER_FEE, 'no', 24 * DAY_IN_SECONDS);
                    }
                }
                $this->is_paypal_vault_approved = goopter_is_vaulting_enable($this->result);
            } elseif ($this->is_live_first_party_used === 'yes' || $this->is_sandbox_third_party_used === 'yes') {
                $this->on_board_status = 'USED_FIRST_PARTY';
            }
        }
    }

    public function goopter_get_signup_link($testmode, $page) {
        try {
            $seller_onboarding_result = $this->seller_onboarding->goopter_generate_signup_link($testmode, $page);
            if (isset($seller_onboarding_result['links'])) {
                foreach ($seller_onboarding_result['links'] as $link) {
                    if (isset($link['rel']) && 'action_url' === $link['rel']) {
                        return isset($link['href']) ? $link['href'] : false;
                    }
                }
            } else {
                return false;
            }
        } catch (Exception $ex) {
            
        }
    }

    public function goopter_get_signup_link_for_vault($testmode, $page) {
        try {
            $body = $this->seller_onboarding->ppcp_vault_data();
            $seller_onboarding_result = $this->seller_onboarding->goopter_generate_signup_link_with_feature($testmode, $page, $body);
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

    public function display_view() {
        try {
            $this->goopter_ppcp_load_variable();
            $this->view();
        } catch (Exception $ex) {
            
        }
    }

    public function view() {

        try {
            $this->goopter_ppcp_load_variable();
            $goopter_ppcp_account_reconnect_notice = get_option('goopter_ppcp_account_reconnect_notice');
            ?>
            <div id="goopter_paypal_marketing_table">
               <?php if ($this->on_board_status === 'NOT_CONNECTED' || $this->on_board_status === 'USED_FIRST_PARTY') { ?>
                    <div class="paypal_woocommerce_product">
                        <div class="paypal_woocommerce_product_onboard" style="text-align:center;">
                            <span class="ppcp_onbard_icon"><img width="200px" class="image" src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/ppcp_admin_onbard_icon.png'); ?>"></span>
                            <br><br>
                            <div class="paypal_woocommerce_product_onboard_content">
                                <p><?php // Translators: %s is the name of the PayPal solution (e.g., PayPal Advanced).
                                echo sprintf(wp_kses_post(__('Welcome to the %s solution for WooCommerce. <br> Built by Goopter Commerce Solutions.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')), esc_html(GOOPTER_PPCP_NAME)); ?></p>
                                <?php
                                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- no security issue
                                if (isset($_GET['testmode'])) {
                                    $testmode = ($_GET['testmode'] === 'yes') ? 'yes' : 'no';
                                } else {
                                    $testmode = $this->sandbox ? 'yes' : 'no';
                                }
                                $signup_link = $this->goopter_get_signup_link($testmode, 'admin_settings_onboarding');
                                if ($signup_link) {
                                    $args = array(
                                        'displayMode' => 'minibrowser',
                                    );
                                    $url = add_query_arg($args, $signup_link);
                                    ?>
                                    <a target="_blank" class="wplk-button" id="<?php echo esc_attr('wplk-button'); ?>" data-paypal-onboard-complete="onboardingCallback" href="<?php echo esc_url($url); ?>" data-paypal-button="true"><?php echo esc_html__('Start Now', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></a>
                                    <?php
                                    $script_url = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
                                    wp_enqueue_script( 'paypal-js', $script_url, array( 'jquery' ), null, true );
                                    $inline_script = "
                                    document.querySelectorAll('[data-paypal-onboard-complete=onboardingCallback]').forEach((element) => {
                                        element.addEventListener('click', (e) => {
                                            if ('undefined' === typeof PAYPAL) {
                                                e.preventDefault();
                                                alert('PayPal');
                                            }
                                        });
                                    });
                                    ";
                                    wp_add_inline_script( 'paypal-js', $inline_script );
                                    ?>
                                    <?php
                                } else {
                                    echo esc_html__('We could not properly connect to PayPal', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                                }
                                ?>
                                <p class="ppcp_paypal_fee"><?php // Translators: %s is the PayPal and Goopter fee structure.
                                echo wp_kses_post(
                                    sprintf(
                                        /* translators: %s: PayPal fee structure */
                                        __('Increase average order totals and conversion rates with <br>PayPal Checkout, PayPal Credit, Buy Now Pay Later, Venmo, and more! <br>All for a total PayPal + Goopter fee of only %s.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                                        esc_html($this->goopter_ppcp_get_paypal_fee_structure($this->ppcp_paypal_country, 'paypal'))
                                    )
                                ); ?>
                                    <br><br>
                                    <?php if ($this->ppcp_paypal_country === 'DE') { ?>
                                        <?php 
                                        echo sprintf(
                                            wp_kses_post(
                                                // Translators: %s is the PayPal and Goopter fee structure for Visa/MasterCard/Discover transactions.
                                                __(
                                                    'Fees on Visa/MasterCard/Discover transactions <br>transactions are a total PayPal + Goopter fee of only %s.',
                                                    'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
                                                )
                                            ),
                                            esc_html($this->goopter_ppcp_get_paypal_fee_structure($this->ppcp_paypal_country, 'acc'))
                                        );
                                        ?>
                                    <?php } else { ?>
                                        <?php // Translators: %s is the PayPal and Goopter fee structure for Visa/MasterCard/Discover transactions.
                                        echo wp_kses_post(
                                            sprintf(
                                                /* translators: %s: PayPal + Goopter fee structure */
                                                __(
                                                    'Save money on Visa/MasterCard/Discover transactions <br>with a total PayPal + Goopter fee of only %s.',
                                                    'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
                                                ),
                                                esc_html($this->goopter_ppcp_get_paypal_fee_structure($this->ppcp_paypal_country, 'acc'))
                                            )
                                        );
                                        ?>
                                    <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php
                } elseif ($this->on_board_status === 'CONNECTED_BUT_NOT_ACC') {
                    wp_enqueue_style('ppcp_account_request_form_css', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/css/ppcp_account_request_form.css', null, time());
                    wp_enqueue_script('ppcp_account_request_form_js', PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'assets/js/ppcp_account_request-form-modal.js', null, time(), true);
                    $ppcp_account_request_form_url = add_query_arg(array('testmode' => $this->setting_sandbox), 'https://d1kjd56jkqxpts.cloudfront.net/ppcp-account-request/index.html');
                    include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/template/ppcp_account_request_form.php');
                    $paypal_vault_supported_country = goopter_ppcp_apple_google_vault_supported_country();
                    ?>
                    <div class="paypal_woocommerce_product">
                        <div class="paypal_woocommerce_product_onboard" style="text-align:center;">
                            <span class="ppcp_onbard_icon"><img width="200px" class="image" src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/ppcp_admin_onbard_icon.png'); ?>"></span>
                            <br><br>
                            <div class="paypal_woocommerce_product_onboard_content">
                                <br>
                                <span><img class="green_checkmark" src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/green_checkmark.png'); ?>"></span>
                                <p><?php // Translators: %s is the name of the PayPal solution (e.g., PayPal Advanced).
                                echo wp_kses_post(
                                    sprintf(
                                        /* translators: %s: Name of the product or service */
                                        __(
                                            'You’re currently set up and enjoying the benefits of %s. <br> Built by Goopter.',
                                            'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
                                        ),
                                        esc_html(GOOPTER_PPCP_NAME)
                                    )
                                );
                                ?></p>
                                <p><?php // Translators: %s is the reduced PayPal and Goopter fee rate for debit/credit card transactions.
                                echo wp_kses_post(
                                    sprintf(
                                        /* translators: %s: Reduced rate fee structure */
                                        __(
                                            'However, we need additional verification to approve you for the reduced <br>rate of %s on debit/credit cards.',
                                            'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
                                        ),
                                        esc_html($this->goopter_ppcp_get_paypal_fee_structure($this->ppcp_paypal_country, 'acc'))
                                    )
                                ); ?></p>
                                <p><?php 
                                echo wp_kses_post(
                                    __(
                                        'To apply for a reduced rate, modify your setup, <br>or learn more about additional options, please use the buttons below.',
                                        'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
                                    )
                                );
                                 ?></p>
                                <?php if ($this->is_paypal_vault_approved === false &&  in_array($this->ppcp_paypal_country, $paypal_vault_supported_country)) { ?>
                                    <p><?php 
                                    echo wp_kses_post(
                                        __(
                                            'Your PayPal account is not approved for the Vault functionality<br>which is required for Subscriptions (token payments). <br>Please Reconnect your PayPal account to apply for this feature.',
                                            'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
                                        )
                                    );
                                        ?></p>
                                <?php } ?>
                                <br>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp')); ?>" class="wplk-button"><?php echo esc_html__('Modify Setup', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></a>
                                <?php
                                if (isset($_GET['testmode'])) {
                                    $testmode = ($_GET['testmode'] === 'yes') ? 'yes' : 'no';
                                } else {
                                    $testmode = $this->sandbox ? 'yes' : 'no';
                                }
                                if ($this->is_paypal_vault_approved === false && in_array($this->ppcp_paypal_country, $paypal_vault_supported_country)) {
                                    $signup_link = $this->goopter_get_signup_link_for_vault($testmode, 'admin_settings_onboarding');
                                    if ($signup_link) {
                                        $args = array(
                                            'displayMode' => 'minibrowser',
                                        );
                                        $url = add_query_arg($args, $signup_link);
                                        ?>
                                        <a target="_blank" class="green-button" id="<?php echo esc_attr('wplk-button'); ?>" data-paypal-onboard-complete="onboardingCallback" href="<?php echo esc_url($url); ?>" data-paypal-button="true"><?php echo esc_html__('Reconnect PayPal Account', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></a>
                                        <?php
                                        $script_url = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
                                        wp_enqueue_script( 'paypal-js', $script_url, array( 'jquery' ), null, true );
                                        $inline_script = "
                                        document.querySelectorAll('[data-paypal-onboard-complete=onboardingCallback]').forEach((element) => {
                                            element.addEventListener('click', (e) => {
                                                if ('undefined' === typeof PAYPAL) {
                                                    e.preventDefault();
                                                    alert('PayPal');
                                                }
                                            });
                                        });
                                        ";
                                        wp_add_inline_script( 'paypal-js', $inline_script );
                                        ?>
                                        <?php
                                    } else {
                                        echo esc_html__('We could not properly connect to PayPal', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                                    }
                                } else if (!empty($goopter_ppcp_account_reconnect_notice)) {
                                    $this->print_general_reconnect_paypal_account_section($testmode);
                                }
                                ?>
                                <br><br>
                            </div>
                        </div>
                    </div>
                    <?php
                } elseif ($this->on_board_status === 'FULLY_CONNECTED') {
                    $is_apple_pay_approved = $this->seller_onboarding->goopter_is_apple_pay_approved($this->result);
                    if ($is_apple_pay_approved) {
                        Goopter_PayPal_PPCP_Apple_Pay_Configurations::autoRegisterDomain();
                    }
                    $paypal_vault_supported_country = goopter_ppcp_apple_google_vault_supported_country();
                    ?>
                    <div class="paypal_woocommerce_product">
                        <div class="paypal_woocommerce_product_onboard" style="text-align:center;">
                            <span class="ppcp_onbard_icon"><img width="200px" class="image" src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/ppcp_admin_onbard_icon.png'); ?>"></span>
                            <br><br>
                            <div class="paypal_woocommerce_product_onboard_content">
                                <br>
                                <span><img class="green_checkmark" src="<?php echo esc_url(PAYPAL_FOR_WOOCOMMERCE_ASSET_URL . 'ppcp-gateway/images/admin/green_checkmark.png'); ?>"></span>
                                <p><?php // Translators: %s is the name of the PayPal solution (e.g., PayPal Advanced).
                                echo sprintf(wp_kses_post(__('You’re currently set up and enjoying the benefits of %s. <br> Built by Goopter.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')), esc_html(GOOPTER_PPCP_NAME)); ?></p>
                                <p><?php echo wp_kses_post(__('To modify your setup or learn more about additional options, <br> please use the buttons below.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')); ?></p>
                                <?php if ($this->is_paypal_vault_approved === false && in_array($this->ppcp_paypal_country, $paypal_vault_supported_country)) { ?>
                                    <p>
                                    <?php
                                    echo wp_kses_post(
                                        __(
                                            'Your PayPal account is not approved for the Vault functionality<br>which is required for Subscriptions (token payments). <br>Please Reconnect your PayPal account to apply for this feature.',
                                            'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'
                                        )
                                    );
                                    ?>
                                    </p>
                                <?php } ?>
                                <br>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=goopter_ppcp')); ?>" class="wplk-button"><?php echo esc_html__('Modify Setup', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></a>
                                <?php
                                if (isset($_GET['testmode'])) {
                                    $testmode = ($_GET['testmode'] === 'yes') ? 'yes' : 'no';
                                } else {
                                    $testmode = $this->sandbox ? 'yes' : 'no';
                                }
                                if ($this->is_paypal_vault_approved === false && in_array($this->ppcp_paypal_country, $paypal_vault_supported_country)) {
                                    $signup_link = $this->goopter_get_signup_link_for_vault($testmode, 'admin_settings_onboarding');
                                    if ($signup_link) {
                                        $args = array(
                                            'displayMode' => 'minibrowser',
                                        );
                                        $url = add_query_arg($args, $signup_link);
                                        ?>
                                        <a target="_blank" class="green-button" id="<?php echo esc_attr('wplk-button'); ?>" data-paypal-onboard-complete="onboardingCallback" href="<?php echo esc_url($url); ?>" data-paypal-button="true"><?php echo esc_html__('Reconnect PayPal Account', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></a>
                                        <?php
                                        $script_url = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
                                        wp_enqueue_script( 'paypal-js', $script_url, array( 'jquery' ), null, true );
                                        $inline_script = "
                                        document.querySelectorAll('[data-paypal-onboard-complete=onboardingCallback]').forEach((element) => {
                                            element.addEventListener('click', (e) => {
                                                if ('undefined' === typeof PAYPAL) {
                                                    e.preventDefault();
                                                    alert('PayPal');
                                                }
                                            });
                                        });
                                        ";
                                        wp_add_inline_script( 'paypal-js', $inline_script );
                                        ?>
                                        <?php
                                    } else {
                                        echo esc_html__('We could not properly connect to PayPal', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                                    }
                                } elseif (!empty($goopter_ppcp_account_reconnect_notice)) {
                                    $this->print_general_reconnect_paypal_account_section($testmode);
                                }
                                ?>
                                <br><br>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                <ul class="paypal_woocommerce_support_downloads paypal_woocommerce_product_onboard ppcp_email_confirm">
                    <li>
                        <p><?php echo esc_html__('Have A Question Or Need Expert Help?', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></p>
                        <a class="wplk-button" href="https://www.goopter.com/contact-us/" target="_blank"><?php echo esc_html__('Contact Support', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></a>
                    </li>
                </ul>
            </div>
            <?php
        } catch (Exception $ex) {
            
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended -- no security issue
    }

    public function print_general_reconnect_paypal_account_section($testmode) {
        $signup_link = $this->goopter_get_signup_link($testmode, 'admin_settings_onboarding');
        if ($signup_link) {
            $args = array(
                'displayMode' => 'minibrowser',
            );
            $url = add_query_arg($args, $signup_link);
            ?>
            <a target="_blank" class="green-button" id="<?php echo esc_attr('wplk-button'); ?>" data-paypal-onboard-complete="generalOnboardingCallback" href="<?php echo esc_url($url); ?>" data-paypal-button="true"><?php echo esc_html__('Reconnect PayPal Account', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'); ?></a>
            <?php
            $script_url = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
            wp_enqueue_script( 'paypal-js', $script_url, array( 'jquery' ), null, true );
            $inline_script = "
            document.querySelectorAll('[data-paypal-onboard-complete=onboardingCallback]').forEach((element) => {
                element.addEventListener('click', (e) => {
                    if ('undefined' === typeof PAYPAL) {
                        e.preventDefault();
                        alert('PayPal');
                    }
                });
            });
            ";
            wp_add_inline_script( 'paypal-js', $inline_script );
            ?>
            <?php
        } else {
            echo esc_html__('We could not properly connect to PayPal', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        }
    }

    public function goopter_ppcp_get_paypal_fee_structure($country, $product) {
        try {
            if (isset($this->paypal_fee_structure[$country])) {
                return $this->paypal_fee_structure[$country][$product];
            } else {
                return $this->paypal_fee_structure['default'][$product];
            }
        } catch (Exception $ex) {
            
        }
    }
}
