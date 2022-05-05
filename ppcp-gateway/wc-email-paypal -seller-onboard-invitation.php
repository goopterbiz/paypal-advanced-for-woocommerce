<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('WC_Email_PayPal_Onboard_Seller_Invitation', false)) :

    class WC_Email_PayPal_Onboard_Seller_Invitation extends WC_Email {

        public $post_id;

        public function __construct() {
            $this->id = 'paypal_onboard_seller_invitation';

            $this->title = __('PayPal Onboard Seller Invitation', 'paypal-for-woocommerce');
            $this->description = __('PayPal onboard seller invitation emails are sent to chosen recipient(s) when a new account added in Multi-account plugin.', 'paypal-for-woocommerce');
            $this->template_html = 'emails/angelleye-paypal -seller-onboard-invitation.php';
            $this->template_plain = 'emails/plain/angelleye-paypal -seller-onboard-invitation.php';
            $this->placeholders = array();

            // Triggers for this email.

            add_action('angelleye_ppcp_multi_account_send_saller_onboard_invitation', array($this, 'trigger'), 10);

            // Call parent constructor.
            parent::__construct();

            $this->recipient = $this->get_option('recipient', get_option('admin_email'));

            $this->template_base = PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/template/';
        }

        public function get_default_subject() {
            return __('[{site_title}]: PayPal Seller Onboard.', 'paypal-for-woocommerce');
        }

        public function get_default_heading() {
            return __('One step away to receive money on your paypal account.', 'paypal-for-woocommerce');
        }

        public function trigger($post_id) {
            $this->setup_locale();
            $this->post_id = $post_id;
            if ($this->is_enabled() && $this->get_recipient()) {
                $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            }
            $this->restore_locale();
        }

        public function get_content_html() {
            return wc_get_template_html(
                    $this->template_html, array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => true,
                'plain_text' => false,
                'email' => $this,
                'post_id' => $this->post_id
                    ),
                    $this->template_base, $this->template_base
            );
        }

        public function get_content_plain() {
            return wc_get_template_html(
                    $this->template_plain, array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => true,
                'plain_text' => true,
                'email' => $this,
                'post_id' => $this->post_id
                    ), $this->template_base, $this->template_base
            );
        }

        public function get_default_additional_content() {
            return __('If you haven\'t linked your account within few hours. invitation link will be expired.', 'paypal-for-woocommerce');
        }

        /**
         * Initialise settings form fields.
         */
        public function init_form_fields() {
            /* translators: %s: list of placeholders */
            $placeholder_text = sprintf(__('Available placeholders: %s', 'paypal-for-woocommerce'), '<code>' . implode('</code>, <code>', array_keys($this->placeholders)) . '</code>');
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'paypal-for-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable this email notification', 'paypal-for-woocommerce'),
                    'default' => 'yes',
                ),
                'recipient' => array(
                    'title' => __('Recipient(s)', 'paypal-for-woocommerce'),
                    'type' => 'text',
                    /* translators: %s: WP admin email */
                    'description' => sprintf(__('Enter recipients (comma separated) for this email. Defaults to %s.', 'paypal-for-woocommerce'), '<code>' . esc_attr(get_option('admin_email')) . '</code>'),
                    'placeholder' => '',
                    'default' => '',
                    'desc_tip' => true,
                ),
                'subject' => array(
                    'title' => __('Subject', 'paypal-for-woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => $placeholder_text,
                    'placeholder' => $this->get_default_subject(),
                    'default' => '',
                ),
                'heading' => array(
                    'title' => __('Email heading', 'paypal-for-woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => $placeholder_text,
                    'placeholder' => $this->get_default_heading(),
                    'default' => '',
                ),
                'additional_content' => array(
                    'title' => __('Additional content', 'paypal-for-woocommerce'),
                    'description' => __('Text to appear below the main email content.', 'paypal-for-woocommerce') . ' ' . $placeholder_text,
                    'css' => 'width:400px; height: 75px;',
                    'placeholder' => __('N/A', 'paypal-for-woocommerce'),
                    'type' => 'textarea',
                    'default' => $this->get_default_additional_content(),
                    'desc_tip' => true,
                ),
                'email_type' => array(
                    'title' => __('Email type', 'paypal-for-woocommerce'),
                    'type' => 'select',
                    'description' => __('Choose which format of email to send.', 'paypal-for-woocommerce'),
                    'default' => 'html',
                    'class' => 'email_type wc-enhanced-select',
                    'options' => $this->get_email_type_options(),
                    'desc_tip' => true,
                ),
            );
        }

    }

    endif;

return new WC_Email_PayPal_Onboard_Seller_Invitation();
