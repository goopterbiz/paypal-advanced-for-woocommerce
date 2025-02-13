<?php

class Goopter_Admin_Order_Payment_Process {

    public $payment_method;
    public $payment_request;

    public function __construct() {
        if (is_admin() && !defined('DOING_AJAX')) {
            add_action('add_meta_boxes', array($this, 'goopter_add_meta_box'), 10, 2);
            add_action('woocommerce_process_shop_order_meta', array($this, 'goopter_admin_create_reference_order'), 10, 2);
            add_action('woocommerce_process_shop_order_meta', array($this, 'goopter_admin_order_process_payment'), 10, 2);
            add_action('goopter_admin_create_reference_order_action_hook', array($this, 'goopter_admin_create_reference_order_action'), 10, 1);
            add_action('goopter_admin_order_process_payment_action_hook', array($this, 'goopter_admin_order_process_payment_action'), 10, 1);
        }
    }

    public function goopter_add_meta_box($post_type, $post_or_order_object) {
        $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
        if (!is_a($order, 'WC_Order')) {
            return;
        }
        $screen = goopter_get_shop_order_screen_id();
        if (goopter_is_active_screen($screen)) {
            add_meta_box('goopter_admin_order_payment_process', __('Reference Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), array($this, 'admin_order_payment_process'), $screen, 'side', 'default');
            add_meta_box('goopter_admin_order_reference_order', __('Reference Transaction', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), array($this, 'admin_order_reference_order'), $screen, 'side', 'default');
        }
    }

    public function goopter_hide_reference_order_metabox() {
        wp_register_style( 'goopter-admin-styles', false );
        wp_enqueue_style( 'goopter-admin-styles' );

        $css = '
            #goopter_admin_order_reference_order {
                display: none !important;
            }
            label[for="goopter_admin_order_reference_order-hide"] {
                display: none !important;
            }
        ';

        wp_add_inline_style( 'goopter-admin-styles', $css );
    }

    public function goopter_show_reference_order_metabox() {
        wp_register_style( 'goopter-admin-styles', false );
        wp_enqueue_style( 'goopter-admin-styles' );

        $css = '
            #goopter_admin_order_reference_order {
                display: block !important;
            }
            label[for="goopter_admin_order_reference_order-hide"] {
                display: inline !important;
            }
        ';

        wp_add_inline_style( 'goopter-admin-styles', $css );
    }

    public function goopter_hide_order_payment_metabox() {
        wp_register_style( 'goopter-admin-styles', false );
        wp_enqueue_style( 'goopter-admin-styles' );

        $css = '
            #goopter_admin_order_payment_process {
                display: none !important;
            }
            label[for="goopter_admin_order_payment_process-hide"] {
                display: none !important;
            }
        ';

        wp_add_inline_style( 'goopter-admin-styles', $css );
    }

    public function goopter_show_order_payment_metabox() {
        wp_register_style( 'goopter-admin-styles', false );
        wp_enqueue_style( 'goopter-admin-styles' );
        
        $css = '
            #goopter_admin_order_payment_process {
                display: block !important;
            }
            label[for="goopter_admin_order_payment_process-hide"] {
                display: inline !important;
            }
        ';
        
        wp_add_inline_style( 'goopter-admin-styles', $css );
    }

    public function admin_order_reference_order($post_or_order_object) {
        $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
        if (!is_a($order, 'WC_Order')) {
            return;
        }
        if (goopter_is_active_screen(goopter_get_shop_order_screen_id())) {
            if ($this->goopter_is_order_need_payment($order) && $this->goopter_is_admin_order_payment_method_available($order) == true && $this->goopter_is_order_created_by_create_new_reference_order($order) == false) {
                $reason_array = $this->goopter_get_reason_why_create_reference_transaction_order_button_not_available($order);
                $reason_message = $this->goopter_reason_array_to_nice_message($reason_array);
                $this->goopter_create_order_button($reason_message, count($reason_array) > 0);
                $this->goopter_show_reference_order_metabox();
            } else {
                $this->goopter_hide_reference_order_metabox();
            }
        }
    }

    public function admin_order_payment_process($post_or_order_object) {
        $is_disable_button = false;
        $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
        if ($this->goopter_is_order_created_by_create_new_reference_order($order) && $this->goopter_is_order_status_pending($order) == true) {
            $reason_array = $this->goopter_get_reason_why_process_reference_transaction_button_not_available($order);
            if (count($reason_array) > 1) {
                $is_disable_button = true;
            }
            $reason_message = $this->goopter_reason_array_to_nice_message($reason_array);
            $this->goopter_place_order_button($reason_message, $is_disable_button);
            $this->goopter_show_order_payment_metabox();
        } else {
            $this->goopter_hide_order_payment_metabox();
        }
    }

    public function goopter_place_order_button($reason_message, $is_disable_button) {
        $is_disable = '';
        if ($is_disable_button == true) {
            $is_disable = 'disabled';
        }
        // echo '<div class="wrap goopter_admin_payment_process">' . $reason_message . '<input type="hidden" name="goopter_admin_order_payment_process_sec" value="' . wp_create_nonce('goopter_admin_order_payment_process_sec') . '" /><input type="submit" ' . $is_disable . ' id="goopter_admin_order_payment_process_submit_button" value="Process Reference Transaction" name="goopter_admin_order_payment_process_submit_button" class="button button-primary"></div>';
        echo '<div class="wrap goopter_admin_payment_process">' .
            wp_kses_post( $reason_message ) .
            '<input type="hidden" name="goopter_admin_order_payment_process_sec" value="' . esc_attr( wp_create_nonce( 'goopter_admin_order_payment_process_sec' ) ) . '" />' .
            '<input type="submit" ' . esc_attr( $is_disable ) . ' id="goopter_admin_order_payment_process_submit_button" value="' . esc_attr( 'Process Reference Transaction' ) . '" name="goopter_admin_order_payment_process_submit_button" class="button button-primary">' .
            '</div>';
    }

    public function goopter_create_order_button($reason_message, $is_disable_button) {
        $is_disable = '';
        if ($is_disable_button == true) {
            $is_disable = 'disabled';
        }
        $checkbox = '<br><label><input type="checkbox" name="copy_items_to_new_invoice">Copy items to new order?</label><br>';
        // echo '<div class="wrap goopter_create_reference_order_section">' . $reason_message . '<input type="hidden" name="goopter_create_reference_order_sec" value="' . wp_create_nonce('goopter_create_reference_order_sec') . '" /><input type="submit" ' . $is_disable . ' id="goopter_create_reference_order_submit_button" value="Create Reference Transaction Order" name="goopter_create_reference_order_submit_button" class="button button-primary">' . $checkbox . '</div>';
        echo '<div class="wrap goopter_create_reference_order_section">' .
            wp_kses_post( $reason_message ) .
            '<input type="hidden" name="goopter_create_reference_order_sec" value="' . esc_attr( wp_create_nonce( 'goopter_create_reference_order_sec' ) ) . '" />' .
            '<input type="submit" ' . esc_attr( $is_disable ) . ' id="goopter_create_reference_order_submit_button" value="' . esc_attr( 'Create Reference Transaction Order' ) . '" name="goopter_create_reference_order_submit_button" class="button button-primary">' .
            wp_kses_post( $checkbox ) .
            '</div>';
    }

    public function goopter_is_order_status_pending($order) {
        return ($order->get_status() == 'pending') ? true : false;
    }

    public function goopter_admin_create_reference_order($post_id, $post_or_order_object) {
        if (!empty($_POST['goopter_create_reference_order_submit_button']) && $_POST['goopter_create_reference_order_submit_button'] == 'Create Reference Transaction Order') {
            if (
                isset($_POST['goopter_create_reference_order_sec']) &&
                wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['goopter_create_reference_order_sec'])),
                    'goopter_create_reference_order_sec'
                )
            ) {
                $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
                if (goopter_is_active_screen(goopter_get_shop_order_screen_id())) {
                    do_action('goopter_admin_create_reference_order_action_hook', $order);
                }

            }
        }
    }

    public function goopter_admin_order_process_payment($post_id, $post_or_order_object) {
        if (!empty($_POST['goopter_admin_order_payment_process_submit_button']) && $_POST['goopter_admin_order_payment_process_submit_button'] == 'Process Reference Transaction') {
            if (
                isset($_POST['goopter_admin_order_payment_process_sec']) &&
                wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['goopter_admin_order_payment_process_sec'])),
                    'goopter_admin_order_payment_process_sec'
                )
            ) {
                $order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
                if (goopter_is_active_screen(goopter_get_shop_order_screen_id())) {
                    do_action('goopter_admin_order_process_payment_action_hook', $order);
                }
            }
        }
    }

    public function goopter_admin_create_reference_order_action($order) {
        $this->payment_method = $order->get_payment_method();
        if (in_array($this->payment_method, [
            'goopter_ppcp', 'goopter_ppcp_cc', 'goopter_ppcp_apple_pay'
        ])) {
            $this->goopter_admin_create_new_order($order);
        }
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
    }

    public function goopter_admin_order_process_payment_action($order) {
        $order_id = $order->get_id();
        $this->payment_method = $order->get_payment_method();
        switch ($this->payment_method) {
            case ($this->payment_method == "goopter_ppcp" || $this->payment_method == "goopter_ppcp_cc" || $this->payment_method == 'goopter_ppcp_apple_pay'): {
                    if (!class_exists('Goopter_PayPal_PPCP_Payment')) {
                        include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-payment.php');
                    }
                    $this->payment_request = Goopter_PayPal_PPCP_Payment::instance();
                    $this->payment_request->goopter_ppcp_capture_order_using_payment_method_token($order_id);
                }
                break;
        }
        $order->set_created_via('admin_order_process_payment');
        $order->save();
        remove_action('woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2);
    }

    public function goopter_admin_create_new_order($order) {
        if (
            !isset($_POST['goopter_create_reference_order_sec']) ||
            wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['goopter_create_reference_order_sec'])),
                'goopter_create_reference_order_sec'
            )
        ) {
            $logger = wc_get_logger();  // Get the logger instance
            $logger->error('goopter admin create new order nonce verification failed. Nonce not valid.', array('source' => 'goopter-includes/goopter-admin-order-payment-process.php'));
        }

        $args = array(
            'customer_id' => $order->get_user_id(),
            'customer_note' => wptexturize($order->get_customer_note()),
            'order_id' => 0,
        );
        $shipping_details = array(
            'first_name' => $order->get_shipping_first_name(),
            'last_name' => $order->get_shipping_last_name(),
            'company' => $order->get_shipping_company(),
            'address_1' => $order->get_shipping_address_1(),
            'address_2' => $order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'state' => $order->get_shipping_state(),
            'postcode' => $order->get_shipping_postcode(),
            'country' => $order->get_shipping_country(),
        );
        $billing_details = array(
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'company' => $order->get_billing_company(),
            'address_1' => $order->get_billing_address_1(),
            'address_2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
        );
        $environment = $order->get_meta('_enviorment');

        // TODO verify this line as add_item is supposed to receive the single item object,
        // while we are passing an array of Item objects
        $new_order = wc_create_order($args);
        $old_get_items = $order->get_items();
        $new_order->add_item($old_get_items);
        Goopter_Utility::goopter_set_address($new_order, $shipping_details, 'shipping');
        Goopter_Utility::goopter_set_address($new_order, $billing_details, 'billing');
        $this->payment_method = $order->get_payment_method();
        $new_order->set_payment_method($this->payment_method);
        $new_order->set_created_via('create_new_reference_order');
        $new_order->update_meta_data('_enviorment', $environment);
        $token_id = $this->get_usable_reference_transaction($order);
        if (!empty($token_id)) {
            $new_order->update_meta_data('_first_transaction_id', $token_id);
        }
        if (!empty($_POST['copy_items_to_new_invoice']) && $_POST['copy_items_to_new_invoice'] == 'on') {
            $this->goopter_update_order_meta($order, $new_order);
        }
        $new_order->add_order_note('Order Created: Create Reference Transaction Order', 0, false);
        $new_order->calculate_totals();
        $new_order->save();
        wp_redirect($new_order->get_edit_order_url());
        exit();
    }

    public function goopter_update_order_meta($order, $new_order) {
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $new_order->add_product($product, $item['qty']);
        }
    }

    public function goopter_is_order_created_by_create_new_reference_order($order) {
        return ($this->goopter_get_created_via($order) == 'create_new_reference_order' ) ? true : false;
    }

    public function goopter_is_order_need_payment($order) {
        return ($order->get_total() > 0) ? true : false;
    }

    public function goopter_is_order_payment_method_selected($order) {
        $this->payment_method = $order->get_payment_method();
        return ($this->payment_method != '') ? true : false;
    }

    public function goopter_is_admin_order_payment_method_available($order) {
        $this->payment_method = $order->get_payment_method();
        if (in_array($this->payment_method, array('goopter_ppcp', 'goopter_ppcp_cc', 'goopter_ppcp_apple_pay'))) {
            return true;
        } else {
            return false;
        }
    }

    public function goopter_get_reason_why_process_reference_transaction_button_not_available($order) {
        $reason_array = array();
        $token_id = $this->goopter_is_usable_reference_transaction_avilable($order);
        if ($this->goopter_is_order_payment_method_selected($order) == false) {
            $reason_array[] = __('Payment method is not available for payment process, Please select Payment method from Billing details section.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        } else {
            if (empty($token_id) && $this->goopter_is_order_user_selected($order) == true) {
                $reason_array[] = __('Payment Token Or Reference transaction ID is not available for payment process.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
            }
        }
        if ($this->goopter_is_order_need_payment($order) == false) {
            $reason_array[] = __('Order total must be greater than zero to process a reference transaction.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        }
        $reason_array[] = __("Make any necessary adjustments to the item(s) on the order and calculate totals.  Remember to click Update if any adjustments were made, and then click Process Reference Transaction.", 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        return $reason_array;
    }

    public function goopter_get_reason_why_create_reference_transaction_order_button_not_available($order) {
        $reason_array = array();
        $token_list = $this->goopter_is_usable_reference_transaction_avilable($order);
        if ($this->goopter_is_order_user_selected($order) == false) {
            $reason_array[] = __('This order is not associated with a registered user account, hence a reference transaction can not be done.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        }
        if ($this->goopter_is_order_payment_method_selected($order) == false) {
            $reason_array[] = __('Payment method is not available for payment process, Please select Payment method from Billing details section.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        } else {
            if (empty($token_list) && $this->goopter_is_order_user_selected($order) == true) {
                $reason_array[] = __('Payment Token Or Reference transaction ID is not available for payment process.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
            }
        }
        if ($this->goopter_is_order_need_payment($order) == false) {
            $reason_array[] = __('Order total must be greater than zero to process a reference transaction.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        }
        return $reason_array;
    }

    public function goopter_reason_array_to_nice_message($reason_array) {
        $reason_message = '';
        if (!empty($reason_array)) {
            $reason_message .= '<ul>';
            foreach ($reason_array as $key => $value) {
                $reason_message .= '<li>' . $value . '</li>';
            }
            $reason_message .= '</ul>';
        }
        return $reason_message;
    }

    public function goopter_is_usable_reference_transaction_avilable($order) {
        $payment_token = $this->get_usable_reference_transaction($order);
        return (!empty($payment_token)) ? $payment_token : false;
    }

    public function get_usable_reference_transaction($order) {
        $this->payment_method = $order->get_payment_method();
        $user_id = $order->get_user_id();
        if (in_array($this->payment_method, array('goopter_ppcp', 'goopter_ppcp_cc', 'goopter_ppcp_apple_pay'))) {
            return $this->goopter_get_payment_token($user_id, $order);
        }
    }

    public function goopter_get_payment_token($user_id, $order) {
        $this->payment_method = $order->get_payment_method();
        if (in_array($this->payment_method, ['goopter_ppcp', 'goopter_ppcp_cc', 'goopter_ppcp_apple_pay'])) {
            if (!class_exists('Goopter_PayPal_PPCP_Payment')) {
                include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-payment.php');
            }
            $this->payment_request = Goopter_PayPal_PPCP_Payment::instance();
            $payment_token = $this->payment_request->goopter_ppcp_get_all_payment_tokens_by_user_id($user_id);
            if (!empty($payment_token)) {
                return $payment_token;
            }
        }
    }

    public function goopter_get_created_via($order) {
        return $order->get_created_via();
    }

    public function goopter_is_order_user_selected($order) {
        return ($order->get_user_id() != '0') ? true : false;
    }
}
