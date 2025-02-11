<?php
/**
 * @var array $jsonResponse
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ($jsonResponse['status']) {
    $domain_validation_file = $this->apple_pay_domain_validation->getDomainAssociationFilePath(true);
    ?>
    <h4 style="border-bottom: 0px solid #ccc;margin-bottom: 2px;padding-bottom: 2px;margin-top: 8px;" class="center">
    <?php echo esc_html( __( 'Add Domain', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce' ) ); ?>
    </h4>
    <?php if (isset($successMessage)) echo '<div style="    margin: 10px 0;" class="updated">' . esc_html( $successMessage ) . '</div>'; ?>
    <div class="border-box" style="border: 1px solid #c3c4c7;padding: 8px;">
        <p class="no-padding no-margin">
        <?php echo esc_html(__('Please ensure that the following link is accessible in order to verify the domain. When you click the link you should see a separate page load with a bunch of numbers displayed. This means it is accessible.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')); ?>
            echo '<br />';
            <?php echo esc_html(__('Once you have verified the page is accessible, click the Add Domain button. Your domain will then show up in the list below, and this means you are ready to accept Apple Pay on your website!', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')); ?>
            ?><br /><br />
            <a target="_blank" href="<?php echo esc_url( $domain_validation_file ); ?>"><?php echo esc_html( $domain_validation_file ); ?></a>
        </p>
        <div class="apple-pay-domain-add-form">
            <form method="post" action="<?php echo esc_url( add_query_arg( [ 'action' => 'goopter_register_apple_pay_domain' , 'goopter_register_apple_pay_domain_nonce' => wp_create_nonce('goopter_register_apple_pay_domain_nonce')], admin_url( 'admin-ajax.php' ) ) ); ?>" class="goopter_apple_pay_ajax_form_submit">
                <label>Domain Name: </label>
                    <input type="text" name="apple_pay_domain" 
                        value="<?php 
                            $site_url = get_site_url();
                            $parsed_url = wp_parse_url($site_url);
                            echo isset($parsed_url['host']) ? esc_attr($parsed_url['host']) : ''; 
                        ?>">
                <input type="submit" value="Add Domain" class="wplk-button button-primary submit_btn">
            </form>
        </div>
    </div>
    <h4 style="margin-bottom: 7px;" class="center"><?php echo esc_html( __( 'Domains in Your PayPal Account', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce' ) ); ?></h4>
    <table class="wp-list-table widefat fixed striped table-view-list apple-pay-domain-listing-table">
        <tr><th><?php echo esc_html( __( 'Domain Name', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce' ) ); ?></th><th><?php echo esc_html( __( 'Action', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce' ) ); ?></th></tr>
        <?php
        if (count($jsonResponse['domains'])) {
            foreach ($jsonResponse['domains'] as $domain) { ?>
                <tr>
                    <td><?php echo esc_html( $domain['domain'] ); ?></td>
                    <td>
                        <a class="goopter_apple_pay_remove_api_call"
                            href="<?php echo esc_url( add_query_arg( [ 'domain' => $domain['domain'], 'action' => 'goopter_remove_apple_pay_domain', 'goopter_remove_apple_pay_domain_nonce' => wp_create_nonce('goopter_remove_apple_pay_domain_nonce') ], admin_url( 'admin-ajax.php' ) ) ); ?>">
                            <?php esc_html_e( 'Delete', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce' ); ?>
                        </a>
                    </td>
                </tr>
            <?php }
        } else {
            echo '<tr class="no-apple-pay-domains-in-account"><td colspan="2">' . esc_html( __( 'No domains registered yet.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce' ) ) . '</td></tr>';
        }?>
    </table>

    <?php
} else {
    echo '<div class="error">' . esc_html( $jsonResponse['message'] ) . '</div>';
}
