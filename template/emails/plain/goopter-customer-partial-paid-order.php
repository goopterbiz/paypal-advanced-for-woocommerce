<?php

defined('ABSPATH') || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Translators: %s is the billing first name of the customer.
echo sprintf(esc_html__('Hi %s,', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), esc_html($order->get_billing_first_name())) . "\n\n";

// Translators: %s is the order number.
echo sprintf(esc_html__('Just to let you know &mdash; we\'ve received your order #%s, and it is now being processed:', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), esc_html($order->get_order_number())) . "\n\n";

do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n----------------------------------------\n\n";

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n\n----------------------------------------\n\n";

if ($additional_content) {
    echo esc_html(wp_strip_all_tags(wptexturize($additional_content)));
    echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
