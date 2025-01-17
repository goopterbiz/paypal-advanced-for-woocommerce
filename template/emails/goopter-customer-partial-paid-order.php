<?php
if (!defined('ABSPATH')) {
    exit;
}


do_action('woocommerce_email_header', $email_heading, $email);
?>

<?php ?>
<p><?php // Translators: %s is the billing first name of the customer.
printf(esc_html__('Hi %s,', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), esc_html($order->get_billing_first_name())); ?></p>
<?php ?>
<p><?php // Translators: %s is the order number.
printf(esc_html__('Just to let you know &mdash; we\'ve received your order #%s, and it is now being processed:', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), esc_html($order->get_order_number())); ?></p>

<?php
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email);
