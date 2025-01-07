<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('WC_Email_Partially_Paid_Order', false)) :

    class WC_Email_Partially_Paid_Order extends WC_Email {

        public function __construct() {
            $this->id = 'customer_partially_paid_order';
            $this->customer_email = true;

            $this->title = __('Partially Paid order - Customer', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce');
            $this->description = __('This is an order notification sent to customers containing order details after partially paid.', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce');
            $this->template_html = 'emails/goopter-customer-partial-paid-order.php';
            $this->template_plain = 'emails/plain/goopter-customer-partial-paid-order.php';
            $this->placeholders = array(
                '{order_date}' => '',
                '{order_number}' => '',
            );

            // Triggers for this email.
            add_action('woocommerce_order_status_cancelled_to_partial-payment_notification', array($this, 'trigger'), 10, 2);
            add_action('woocommerce_order_status_failed_to_partial-payment_notification', array($this, 'trigger'), 10, 2);
            add_action('woocommerce_order_status_on-hold_to_partial-payment_notification', array($this, 'trigger'), 10, 2);
            add_action('woocommerce_order_status_pending_to_partial-payment_notification', array($this, 'trigger'), 10, 2);
            add_action('woocommerce_order_status_processing_to_partial-payment_notification', array($this, 'trigger'), 10, 2);

            // Call parent constructor.
            parent::__construct();
            
            $this->template_base = PAYPAL_FOR_WOOCOMMERCE_DIR_PATH . '/template/';
        }

        public function get_default_subject() {
            return __('[{site_title}]: Order #{order_number} has Partially Paid', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce');
        }

        public function get_default_heading() {
            return __('Order Partially Paid: #{order_number}', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce');
        }

        public function trigger($order_id, $order = false) {
            $this->setup_locale();

            if ($order_id && !is_a($order, 'WC_Order')) {
                $order = wc_get_order($order_id);
            }

            if (is_a($order, 'WC_Order')) {
                $this->object = $order;
                $this->recipient = $this->object->get_billing_email();
                $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{order_number}'] = $this->object->get_order_number();
            }

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
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $this,
                    )
            );
        }

        public function get_content_plain() {
            return wc_get_template_html(
                    $this->template_plain, array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin' => false,
                'plain_text' => true,
                'email' => $this,
                    )
            );
        }

        public function get_default_additional_content() {
            return __('We look forward to fulfilling your order soon.', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce');
        }
        
        /**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			/* translators: %s: list of placeholders */
			$placeholder_text  = sprintf( __( 'Available placeholders: %s', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );
			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'Enable/Disable', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'default' => 'yes',
				),
				'recipient'          => array(
					'title'       => __( 'Recipient(s)', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'type'        => 'text',
					/* translators: %s: WP admin email */
					'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
					'placeholder' => '',
					'default'     => '',
					'desc_tip'    => true,
				),
				'subject'            => array(
					'title'       => __( 'Subject', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'            => array(
					'title'       => __( 'Email heading', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'additional_content' => array(
					'title'       => __( 'Additional content', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'description' => __( 'Text to appear below the main email content.', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ) . ' ' . $placeholder_text,
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => __( 'N/A', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'type'        => 'textarea',
					'default'     => $this->get_default_additional_content(),
					'desc_tip'    => true,
				),
				'email_type'         => array(
					'title'       => __( 'Email type', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'goopter-advanced-payment-for-paypal-complete-payment-and-woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}

    }

    endif;

return new WC_Email_Partially_Paid_Order();
