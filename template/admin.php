<?php
/**
 * PayPal for WooCommerce - Settings
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<?php
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
$active_tab = isset($_GET['tab']) ? wc_clean(sanitize_text_field(wp_unslash($_GET['tab']))) : 'general_settings';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
$gateway = isset($_GET['gateway']) ? wc_clean(sanitize_text_field(wp_unslash($_GET['gateway']))) : 'paypal_payment_gateway_products';
?>
<div class="wrap">

    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <br>
    <?php if ($active_tab == 'general_settings') { ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=<?php echo esc_attr($this->plugin_slug); ?>" class="nav-tab <?php echo $gateway == 'paypal_payment_gateway_products' ? 'nav-tab-active' : ''; ?>">
                <?php 
                // phpcs:disable WordPress.WP.I18n.NoEmptyStrings
                // Translators: %s is the name of the PayPal solution (e.g., PayPal Advanced).
                echo sprintf(esc_html__('%s', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), esc_html(GOOPTER_PPCP_NAME));
                // phpcs:enable WordPress.WP.I18n.NoEmptyStrings
                ?></a>
            <?php do_action('goopter_paypal_for_woocommerce_general_settings_tab'); ?>
        </h2>
        <?php
        if ($gateway == 'paypal_payment_gateway_products') {
            if (!class_exists('Goopter_PayPal_PPCP_Admin_Onboarding')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-admin-onboarding.php';
            }
            $admin_onboarding = Goopter_PayPal_PPCP_Admin_Onboarding::instance();
            ?>
            <div class="wrap goopter_addons_wrap">
                <?php
                $admin_onboarding->display_view();
                ?>
            </div>
        <?php
        } else {
            do_action('goopter_paypal_for_woocommerce_general_settings_tab_content');
        }
    }
    ?>
</div>