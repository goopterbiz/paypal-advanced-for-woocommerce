<?php
/**
 * PayPal for WooCommerce - Settings
 */
?>
<?php
$active_tab = isset($_GET['tab']) ? wc_clean($_GET['tab']) : 'general_settings';
$gateway = isset($_GET['gateway']) ? wc_clean($_GET['gateway']) : 'paypal_payment_gateway_products';
?>
<div class="wrap">

    <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
    <br>
    <?php if ($active_tab == 'general_settings') { ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=<?php echo $this->plugin_slug; ?>" class="nav-tab <?php echo $gateway == 'paypal_payment_gateway_products' ? 'nav-tab-active' : ''; ?>"><?php echo sprintf(__('%s', 'paypal-advanced-for-woocommerce'), AE_PPCP_NAME); ?></a>
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