<?php

if (class_exists('WC_Checkout')) {

    class Goopter_PayPal_PPCP_Checkout extends WC_Checkout {

        public static $_instance;

        public static function instance() {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        public function process_checkout() {
            try {
                wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);
                wc_set_time_limit(0);
                do_action('woocommerce_before_checkout_process');
                if (WC()->cart->is_empty()) {
                    // Translators: %s is the URL to the shop page.
                    throw new Exception(sprintf(__('Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'), esc_url(wc_get_page_permalink('shop'))));
                }
                do_action('woocommerce_checkout_process');
                $errors = new WP_Error();
                /**
                 * @var Goopter_PayPal_PPCP_Smart_Button $smart_button
                 */
                $smart_button = Goopter_PayPal_PPCP_Smart_Button::instance();
                $posted_data = $smart_button->goopter_ppcp_prepare_order_data($this->get_posted_data());
                $this->update_session($posted_data);
                $this->validate_checkout($posted_data, $errors);
                $paypal_order_id = Goopter_Session_Manager::get('paypal_order_id', false);
                $paypal_payer_id = Goopter_Session_Manager::get('paypal_payer_id', false);

                foreach ($errors->errors as $code => $messages) {
                    $data = $errors->get_error_data($code);
                    foreach ($messages as $message) {
                        wc_add_notice($message, 'error', $data);
                    }
                }
                if (empty($posted_data['woocommerce_checkout_update_totals']) && 0 === wc_notice_count('error')) {
                    $this->process_customer($posted_data);
                    $order_id = $this->create_order($posted_data);
                    $order = wc_get_order($order_id);
                    if (is_wp_error($order_id)) {
                        throw new Exception($order_id->get_error_message());
                    }
                    if (!$order) {
                        throw new Exception(__('Unable to create order.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'));
                    }
                    do_action('woocommerce_checkout_order_processed', $order_id, $posted_data, $order);
                    if (apply_filters('woocommerce_cart_needs_payment', $order->needs_payment(), WC()->cart)) {
                        $this->process_order_payment($order_id, ($posted_data['payment_method'] ?? 'goopter_ppcp'));
                    } else {
                        $this->process_order_without_payment($order_id);
                    }
                }
            } catch (Exception $e) {
                wc_add_notice($e->getMessage(), 'error');
            }
            if (wp_doing_ajax()) {
                $this->send_ajax_failure_response();
            } else {
                $return_arg_url = array();
                $return_arg_url['utm_nooverride'] = '1';
                if (!empty($paypal_order_id)) {
                    $return_arg_url['paypal_order_id'] = $paypal_order_id;
                }
                if (!empty($paypal_payer_id)) {
                    $return_arg_url['paypal_payer_id'] = $paypal_payer_id;
                }
                wp_safe_redirect(add_query_arg($return_arg_url, untrailingslashit(wc_get_checkout_url())));
                exit();
            }
        }

        /**
         * @throws Exception
         */
        public function goopter_ppcp_create_order() {
            wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);
            wc_set_time_limit(0);
            do_action('woocommerce_before_checkout_process');
            if (WC()->cart->is_empty()) {
                throw new Exception(wp_kses_post(sprintf(
                    // Translators: %s is the URL to the shop page.
                    __('Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'),
                    esc_url(wc_get_page_permalink('shop'))
                )));
            }

            $errors = new WP_Error();
            /**
             * @var Goopter_PayPal_PPCP_Smart_Button $smart_button
             */
            $smart_button = Goopter_PayPal_PPCP_Smart_Button::instance();
            $posted_data = $smart_button->goopter_ppcp_prepare_order_data($this->get_posted_data());
            $this->update_session($posted_data);

            foreach ($errors->errors as $code => $messages) {
                $data = $errors->get_error_data($code);
                foreach ($messages as $message) {
                    wc_add_notice($message, 'error', $data);
                }
            }

            if (empty($posted_data['woocommerce_checkout_update_totals']) && 0 === wc_notice_count('error')) {
                $this->process_customer($posted_data);
                $order_id = $this->create_order($posted_data);
                $order = wc_get_order($order_id);
                if (is_wp_error($order_id)) {
                    throw new Exception(esc_html($order_id->get_error_message()));
                }
                if (!$order) {
                    throw new Exception(esc_html__('Unable to create order.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'));
                }
                /**
                 * Store the order id in session so that in case of transaction failure user can start with same order
                 */
                WC()->session->set('order_awaiting_payment', $order_id);
                do_action('woocommerce_checkout_order_processed', $order_id, $posted_data, $order);
                return $order_id;
            } else {
                throw new Exception(esc_html__('Unable to create order due to following errors.', 'goopter-advanced-payment-for-woocommerce-and-paypal-complete-payment'));
            }
        }
    }

}
