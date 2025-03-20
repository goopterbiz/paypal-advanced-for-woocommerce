<?php
if (!function_exists('goopter_ppcp_remove_empty_key')) {

    function goopter_ppcp_remove_empty_key($data) {
        $original = $data;
        $data = array_filter($data);
        $data = array_map(function ($e) {
            return is_array($e) ? goopter_ppcp_remove_empty_key($e) : $e;
        }, $data);
        return $original === $data ? $data : goopter_ppcp_remove_empty_key($data);
    }

}

if (!function_exists('goopter_ppcp_has_active_session')) {

    function goopter_ppcp_has_active_session() {
        $checkout_details = Goopter_Session_Manager::get('paypal_transaction_details');
        $goopter_ppcp_paypal_order_id = Goopter_Session_Manager::get('paypal_order_id');
        if (is_ajax() && !empty($checkout_details) && !empty($goopter_ppcp_paypal_order_id)) {
            return true;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
        } elseif (!empty($checkout_details) && !empty($goopter_ppcp_paypal_order_id) && isset($_GET['paypal_order_id'])) {
            return true;
        }
        return false;
    }

}

if (!function_exists('goopter_ppcp_get_post_meta')) {

    function goopter_ppcp_get_post_meta($order, $key, $bool = true) {
        $order_meta_value = false;
        if (!is_object($order)) {
            if (did_action('woocommerce_after_register_post_type')) {
                $order = wc_get_order($order);
            }
        }
        if (!is_a($order, 'WC_Order') && !is_a($order, 'WC_Subscription')) {
            return;
        }
        $old_wc = version_compare(WC_VERSION, '3.0', '<');
        if ($old_wc) {
            $order_meta_value = get_post_meta($order->id, $key, $bool);
        } else {
            $order_meta_value = $order->get_meta($key, $bool);
        }
        if (empty($order_meta_value) && $key === '_paymentaction') {
            $order_meta_value = $order->get_meta('_payment_action', $bool);
        } elseif (empty($order_meta_value) && $key === '_payment_action') {
            $order_meta_value = $order->get_meta('_paymentaction', $bool);
        } elseif ($key === '_payment_method_title') {
            $goopter_ppcp_used_payment_method = $order->get_meta('_goopter_ppcp_used_payment_method', $bool);
            if (!empty($goopter_ppcp_used_payment_method)) {
                return goopter_ppcp_get_payment_method_title($goopter_ppcp_used_payment_method);
            }
        }
        return $order_meta_value;
    }

}

if (!function_exists('goopter_ppcp_is_local_server')) {

    function goopter_ppcp_is_local_server() {
        return false;
        if (!isset($_SERVER['HTTP_HOST'])) {
            return;
        }
        // if ($_SERVER['HTTP_HOST'] === 'localhost' || substr(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])), 0, 3) === '10.' || substr(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])), 0, 7) === '192.168') {
        //     return true;
        // }
        if (
            (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') ||
            (isset($_SERVER['REMOTE_ADDR']) && (
                substr(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])), 0, 3) === '10.' ||
                substr(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])), 0, 7) === '192.168'
            ))
        ) {
            return true;
        }
        $live_sites = [
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
        ];
        foreach ($live_sites as $ip) {
            if (!empty($_SERVER[$ip])) {
                return false;
            }
        }
        // if (in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) {
        //     return true;
        // }
        if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) {
            return true;
        }
    }

}

if (!function_exists('goopter_ppcp_get_raw_data')) {

    function goopter_ppcp_get_raw_data() {
        try {
            if (function_exists('phpversion') && version_compare(phpversion(), '5.6', '>=')) {
                return file_get_contents('php://input');
            }
            global $HTTP_RAW_POST_DATA;
            if (!isset($HTTP_RAW_POST_DATA)) {
                $HTTP_RAW_POST_DATA = file_get_contents('php://input');
            }
            return $HTTP_RAW_POST_DATA;
        } catch (Exception $ex) {

        }
    }

}

if (!function_exists('goopter_ppcp_remove_empty_key')) {

    function goopter_ppcp_remove_empty_key($data) {
        $original = $data;
        $data = array_filter($data);
        $data = array_map(function ($e) {
            return is_array($e) ? goopter_ppcp_remove_empty_key($e) : $e;
        }, $data);
        return $original === $data ? $data : goopter_ppcp_remove_empty_key($data);
    }

}

if (!function_exists('goopter_ppcp_readable')) {

    function goopter_ppcp_readable($tex) {
        if (!empty($tex)) {
            $tex = ucwords(strtolower(str_replace('_', ' ', $tex)));
        }
        return $tex;
    }

}

if (!function_exists('goopter_split_name')) {

    function goopter_split_name($fullName) {
        $parts = explode(' ', $fullName);
        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);
        return [$firstName, $lastName];
    }

}

if (!function_exists('goopter_ppcp_get_mapped_billing_address')) {

    function goopter_ppcp_get_mapped_billing_address($checkout_details, $is_name_only = false) {
        if (!is_null(WC()->customer)) {
            $customer = WC()->customer;
            $billing_address = [
                'first_name' => $customer->get_billing_first_name(),
                'last_name' => $customer->get_billing_last_name(),
                'email' => $customer->get_billing_email(),
                'country' => $customer->get_billing_country(),
                'address_1' => $customer->get_billing_address_1(),
                'address_2' => $customer->get_billing_address_2(),
                'city' => $customer->get_billing_city(),
                'state' => $customer->get_billing_state(),
                'postcode' => $customer->get_billing_postcode(),
                'phone' => $customer->get_billing_phone(),
                'company' => $customer->get_billing_company()
            ];
        } else {
            $billing_address = array();
        }
        $goopter_ppcp_checkout_post = Goopter_Session_Manager::get('checkout_post');
        if (!empty($goopter_ppcp_checkout_post)) {
            $billing_address['first_name'] = !empty($goopter_ppcp_checkout_post['billing_first_name']) ? $goopter_ppcp_checkout_post['billing_first_name'] : '';
            $billing_address['last_name'] = !empty($goopter_ppcp_checkout_post['billing_last_name']) ? $goopter_ppcp_checkout_post['billing_last_name'] : '';
            $billing_address['company'] = !empty($goopter_ppcp_checkout_post['billing_company']) ? $goopter_ppcp_checkout_post['billing_company'] : '';
            $billing_address['country'] = !empty($goopter_ppcp_checkout_post['billing_country']) ? $goopter_ppcp_checkout_post['billing_country'] : '';
            $billing_address['address_1'] = !empty($goopter_ppcp_checkout_post['billing_address_1']) ? $goopter_ppcp_checkout_post['billing_address_1'] : '';
            $billing_address['address_2'] = !empty($goopter_ppcp_checkout_post['billing_address_2']) ? $goopter_ppcp_checkout_post['billing_address_2'] : '';
            $billing_address['city'] = !empty($goopter_ppcp_checkout_post['billing_city']) ? $goopter_ppcp_checkout_post['billing_city'] : '';
            $billing_address['state'] = !empty($goopter_ppcp_checkout_post['billing_state']) ? goopter_ppcp_validate_checkout($goopter_ppcp_checkout_post['billing_country'], $goopter_ppcp_checkout_post['billing_state'], 'shipping') : '';
            $billing_address['postcode'] = !empty($goopter_ppcp_checkout_post['billing_postcode']) ? $goopter_ppcp_checkout_post['billing_postcode'] : '';
            $billing_address['phone'] = !empty($goopter_ppcp_checkout_post['billing_phone']) ? $goopter_ppcp_checkout_post['billing_phone'] : '';
            $billing_address['email'] = !empty($goopter_ppcp_checkout_post['billing_email']) ? $goopter_ppcp_checkout_post['billing_email'] : '';
        } elseif (!empty($checkout_details->payer)) {
            $phone = '';
            if (!empty($checkout_details->payer->phone->phone_number->national_number)) {
                $phone = $checkout_details->payer->phone->phone_number->national_number;
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- no security issue
            } elseif (!empty($_POST['billing_phone'])) {
                $phone = wc_clean(sanitize_text_field(wp_unslash($_POST['billing_phone'])));
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing -- no security issue
            $billing_address = array();
            $billing_address['first_name'] = !empty($checkout_details->payer->name->given_name) ? $checkout_details->payer->name->given_name : '';
            $billing_address['last_name'] = !empty($checkout_details->payer->name->surname) ? $checkout_details->payer->name->surname : '';
            $billing_address['company'] = !empty($checkout_details->payer->business_name) ? $checkout_details->payer->business_name : '';
            $billing_address['email'] = !empty($checkout_details->payer->email_address) ? $checkout_details->payer->email_address : '';
            if ($is_name_only === false || (wc_ship_to_billing_address_only() && WC()->cart->needs_shipping())) {
                if (!empty($checkout_details->payer->address->address_line_1) && !empty($checkout_details->payer->address->postal_code)) {
                    $billing_address['address_1'] = !empty($checkout_details->payer->address->address_line_1) ? $checkout_details->payer->address->address_line_1 : '';
                    $billing_address['address_2'] = !empty($checkout_details->payer->address->address_line_2) ? $checkout_details->payer->address->address_line_2 : '';
                    $billing_address['city'] = !empty($checkout_details->payer->address->admin_area_2) ? $checkout_details->payer->address->admin_area_2 : '';
                    $billing_address['state'] = !empty($checkout_details->payer->address->admin_area_1) ? goopter_ppcp_validate_checkout($checkout_details->payer->address->country_code, $checkout_details->payer->address->admin_area_1, 'shipping') : '';
                    $billing_address['postcode'] = !empty($checkout_details->payer->address->postal_code) ? $checkout_details->payer->address->postal_code : '';
                    $billing_address['country'] = !empty($checkout_details->payer->address->country_code) ? $checkout_details->payer->address->country_code : '';
                    $billing_address['phone'] = $phone;
                } else {
                    $billing_address['address_1'] = !empty($checkout_details->purchase_units[0]->shipping->address->address_line_1) ? $checkout_details->purchase_units[0]->shipping->address->address_line_1 : '';
                    $billing_address['address_2'] = !empty($checkout_details->purchase_units[0]->shipping->address->address_line_2) ? $checkout_details->purchase_units[0]->shipping->address->address_line_2 : '';
                    $billing_address['city'] = !empty($checkout_details->purchase_units[0]->shipping->address->admin_area_2) ? $checkout_details->purchase_units[0]->shipping->address->admin_area_2 : '';
                    $billing_address['state'] = !empty($checkout_details->purchase_units[0]->shipping->address->admin_area_1) ? goopter_ppcp_validate_checkout($checkout_details->purchase_units[0]->shipping->address->country_code, $checkout_details->purchase_units[0]->shipping->address->admin_area_1, 'shipping') : '';
                    $billing_address['postcode'] = !empty($checkout_details->purchase_units[0]->shipping->address->postal_code) ? $checkout_details->purchase_units[0]->shipping->address->postal_code : '';
                    $billing_address['country'] = !empty($checkout_details->purchase_units[0]->shipping->address->country_code) ? $checkout_details->purchase_units[0]->shipping->address->country_code : '';
                    $billing_address['phone'] = $phone;
                }
            }
        }
        if ($customer && empty($billing_address['phone'])) {
            $billing_address['phone'] = $customer->get_billing_phone();
        }

        return $billing_address;
    }

}

if (!function_exists('goopter_ppcp_get_mapped_shipping_address')) {

    function goopter_ppcp_get_mapped_shipping_address($checkout_details) {
        $initialData = [];
        $isOverridden = Goopter_Session_Manager::get('shipping_address_updated_from_callback');
        if ($isOverridden) {
            $initialData = goopter_ppcp_get_overridden_shipping_address();
        }
        if (empty($checkout_details->purchase_units[0]) || empty($checkout_details->purchase_units[0]->shipping)) {
            return $initialData;
        }
        if (!empty($checkout_details->purchase_units[0]->shipping->name->full_name)) {
            $name = goopter_split_name($checkout_details->purchase_units[0]->shipping->name->full_name);
            $first_name = $name[0];
            $last_name = $name[1];
        } else {
            $first_name = '';
            $last_name = '';
        }

        // Apple Pay payment sends the email address as part of shipping_address info
        $email_address = null;
        if (!empty($checkout_details->purchase_units[0]->shipping->email_address)) {
            $email_address = $checkout_details->purchase_units[0]->shipping->email_address;
        }
        $result = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email_address' => $email_address,
            'address_1' => !empty($checkout_details->purchase_units[0]->shipping->address->address_line_1) ? $checkout_details->purchase_units[0]->shipping->address->address_line_1 : '',
            'address_2' => !empty($checkout_details->purchase_units[0]->shipping->address->address_line_2) ? $checkout_details->purchase_units[0]->shipping->address->address_line_2 : '',
            'city' => !empty($checkout_details->purchase_units[0]->shipping->address->admin_area_2) ? $checkout_details->purchase_units[0]->shipping->address->admin_area_2 : '',
            'state' => !empty($checkout_details->purchase_units[0]->shipping->address->admin_area_1) ? goopter_ppcp_validate_checkout($checkout_details->purchase_units[0]->shipping->address->country_code, $checkout_details->purchase_units[0]->shipping->address->admin_area_1, 'shipping') : '',
            'postcode' => !empty($checkout_details->purchase_units[0]->shipping->address->postal_code) ? $checkout_details->purchase_units[0]->shipping->address->postal_code : '',
            'country' => !empty($checkout_details->purchase_units[0]->shipping->address->country_code) ? $checkout_details->purchase_units[0]->shipping->address->country_code : '',
        );
        if (!empty($checkout_details->payer->business_name)) {
            $result['company'] = $checkout_details->payer->business_name;
        }
        return array_merge($result, $initialData);
    }

}

if (!function_exists('goopter_ppcp_get_overridden_shipping_address')) {

    function goopter_ppcp_get_overridden_shipping_address() {
        global $woocommerce;
        return array(
            'first_name' => $woocommerce->customer->get_shipping_first_name(),
            'last_name' => $woocommerce->customer->get_shipping_last_name(),
            'address_1' => $woocommerce->customer->get_shipping_address_1(),
            'address_2' => $woocommerce->customer->get_shipping_address_2(),
            'city' => $woocommerce->customer->get_shipping_city(),
            'state' => $woocommerce->customer->get_shipping_state(),
            'postcode' => $woocommerce->customer->get_shipping_postcode(),
            'country' => $woocommerce->customer->get_shipping_country(),
        );
    }

}

if (!function_exists('goopter_ppcp_update_customer_addresses_from_paypal')) {

    function goopter_ppcp_update_customer_addresses_from_paypal($shipping_details, $billing_details) {
        if (!empty(WC()->customer)) {
            $customer = WC()->customer;
            if (!empty($billing_details['first_name'])) {
                $customer->set_billing_first_name($billing_details['first_name']);
            }
            if (!empty($billing_details['last_name'])) {
                $customer->set_billing_last_name($billing_details['last_name']);
            }
            if (!empty($billing_details['address_1'])) {
                $customer->set_billing_address_1($billing_details['address_1']);
                $customer->set_billing_address($billing_details['address_1']);
            }
            if (!empty($billing_details['address_2'])) {
                $customer->set_billing_address_2($billing_details['address_2']);
            }
            if (!empty($billing_details['city'])) {
                $customer->set_billing_city($billing_details['city']);
            }
            if (!empty($billing_details['email'])) {
                $email = $customer->get_email();
                if (empty($email)) {
                    $customer->set_email($billing_details['email']);
                }
                $billing_email = $customer->get_billing_email();
                if (empty($billing_email)) {
                    $customer->set_billing_email($billing_details['email']);
                }
            }
            if (!empty($billing_details['postcode'])) {
                $customer->set_billing_postcode($billing_details['postcode']);
            }
            if (!empty($billing_details['state'])) {
                $customer->set_billing_state($billing_details['state']);
            }
            if (!empty($billing_details['country'])) {
                $customer->set_billing_country($billing_details['country']);
            }
            if (!empty($billing_details['phone'])) {
                $customer->set_billing_phone($billing_details['phone']);
            }
            if (!empty($shipping_details['first_name'])) {
                $customer->set_shipping_first_name($shipping_details['first_name']);
            }
            if (!empty($shipping_details['last_name'])) {
                $customer->set_shipping_last_name($shipping_details['last_name']);
            }
            if (!empty($shipping_details['address_1'])) {
                $customer->set_shipping_address($shipping_details['address_1']);
                $customer->set_shipping_address_1($shipping_details['address_1']);
            }
            if (!empty($shipping_details['address_2'])) {
                $customer->set_shipping_address_2($shipping_details['address_2']);
            }
            if (!empty($shipping_details['city'])) {
                $customer->set_shipping_city($shipping_details['city']);
            }
            if (!empty($shipping_details['postcode'])) {
                $customer->set_shipping_postcode($shipping_details['postcode']);
            }
            if (!empty($shipping_details['state'])) {
                $customer->set_shipping_state($shipping_details['state']);
            }
            if (!empty($shipping_details['country'])) {
                $customer->set_shipping_country($shipping_details['country']);
            }
            $customer->save();
        }
    }

}

if (!function_exists('goopter_ppcp_currency_has_decimals')) {

    function goopter_ppcp_currency_has_decimals($currency) {
        if (in_array($currency, array('HUF', 'JPY', 'TWD'), true)) {
            return false;
        }

        return true;
    }

}

if (!function_exists('goopter_ppcp_round')) {

    function goopter_ppcp_round($price, $precision) {
        try {
            $price = (float) $price;
            $round_price = round($price, $precision);
            $price = number_format($round_price, $precision, '.', '');
        } catch (Exception $ex) {

        }

        return $price;
    }

}

if (!function_exists('goopter_ppcp_number_format')) {

    function goopter_ppcp_number_format($price, $order = null) {
        $decimals = 2;

        if (!empty($order) && !goopter_ppcp_currency_has_decimals($order->get_currency())) {
            $decimals = 0;
        }

        return number_format($price, $decimals, '.', '');
    }

}

if (!function_exists('goopter_ppcp_is_valid_order')) {

    function goopter_ppcp_is_valid_order($order_id) {
        $order = $order_id ? wc_get_order($order_id) : null;
        if ($order) {
            return true;
        }
        return false;
    }

}

if (!function_exists('goopter_ppcp_get_currency')) {

    function goopter_ppcp_get_currency($woo_order_id = null) {
        $currency_code = '';

        if ($woo_order_id != null) {
            $order = wc_get_order($woo_order_id);
            $currency_code = $order->get_currency();
        } else {
            $currency_code = get_woocommerce_currency();
        }

        return $currency_code;
    }

}

if (!function_exists('goopter_key_generator')) {

    function goopter_key_generator() {
        $timestamp = round(microtime(true) * 1000);
        // To ensure a 9-digit integer, truncate the milliseconds or use the last 9 digits and reverse
        $new_key = (int)strrev(substr($timestamp, -9));
        return $new_key;
    }

}

if (!function_exists('goopter_ppcp_get_payment_method_title')) {

    function goopter_ppcp_get_payment_method_title($payment_name = '') {
        $final_payment_method_name = '';
        $list_payment_method = array(
            'card' => __('Credit or Debit Card', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'credit' => __('PayPal Credit', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'bancontact' => __('Bancontact', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'blik' => __('BLIK', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'eps' => __('eps', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'giropay' => __('giropay', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'ideal' => __('iDEAL', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'mercadopago' => __('Mercado Pago', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'mybank' => __('MyBank', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'p24' => __('Przelewy24', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'sepa' => __('SEPA-Lastschrift', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'sofort' => __('Sofort', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'venmo' => __('Venmo', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'paylater' => __('PayPal Pay Later', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'paypal' => __('PayPal Checkout', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'apple_pay' => __('Apple Pay', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
            'google_pay' => __('Google Pay', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
        );
        if (!empty($payment_name)) {
            $final_payment_method_name = $list_payment_method[$payment_name] ?? $payment_name;
        }
        return apply_filters('goopter_ppcp_get_payment_method_title', $final_payment_method_name, $payment_name, $list_payment_method);
    }

}

if (!function_exists('goopter_ppcp_is_product_purchasable')) {

    function goopter_ppcp_is_product_purchasable($product, $enable_tokenized_payments) {
        if ($enable_tokenized_payments === false && $product->is_type('subscription')) {
            return apply_filters('goopter_ppcp_is_product_purchasable', false, $product);
        }
        if (!$product->is_in_stock() || $product->is_type('external') || ($product->get_price() == '' || $product->get_price() == 0)) {
            return apply_filters('goopter_ppcp_is_product_purchasable', false, $product);
        }
        return apply_filters('goopter_ppcp_is_product_purchasable', true, $product);
    }

}

if (!function_exists('goopter_ppcp_validate_checkout')) {

    function goopter_ppcp_validate_checkout($country, $state, $sec) {
        $state_value = '';
        $valid_states = WC()->countries->get_states(isset($country) ? $country : ( 'billing' === $sec ? WC()->customer->get_country() : WC()->customer->get_shipping_country() ));
        if (!empty($valid_states) && is_array($valid_states)) {
            $valid_state_values = array_flip(array_map('strtolower', $valid_states));
            if (isset($valid_state_values[strtolower($state)])) {
                $state_value = $valid_state_values[strtolower($state)];
                return $state_value;
            }
        } else {
            return $state;
        }
        if (!empty($valid_states) && is_array($valid_states) && sizeof($valid_states) > 0) {
            if (!in_array($state, array_keys($valid_states))) {
                return false;
            } else {
                return $state;
            }
        }
        return $state_value;
    }
}

if (!function_exists('goopter_ppcp_add_css_js')) {

    function goopter_ppcp_add_css_js() {
        if (!wp_doing_ajax()) {
            wp_enqueue_script('jquery-blockui');
            wp_enqueue_script('goopter_ppcp-common-functions');
            wp_enqueue_script('goopter_ppcp-apple-pay');
            wp_enqueue_script('goopter_ppcp-google-pay');
            wp_enqueue_script('goopter-paypal-checkout-sdk');
            wp_enqueue_script('goopter_ppcp');
            wp_enqueue_script('goopter-pay-later-messaging');
            wp_enqueue_style('goopter_ppcp');
        }
    }

}

if (!function_exists('goopter_ppcp_get_value')) {

    function goopter_ppcp_get_value($key, $value) {
        switch ($key) {
            case 'soft_descriptor':
                if (!empty($value)) {
                    return substr($value, 0, 22);
                }
                break;
            default:
                break;
        }
        return $value;
    }

}

if (!function_exists('goopter_is_acdc_payments_enable')) {

    function goopter_is_acdc_payments_enable($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products']) && !empty($result['products'])) {
            foreach ($result['products'] as $key => $product) {
                if (isset($product['vetting_status']) && ('SUBSCRIBED' === $product['vetting_status'] || 'APPROVED' === $product['vetting_status'] ) && isset($product['capabilities']) && is_array($product['capabilities']) && in_array('CUSTOM_CARD_PROCESSING', $product['capabilities'])) {
                    foreach ($result['capabilities'] as $key => $capabilities) {
                        if (isset($capabilities['name']) && 'CUSTOM_CARD_PROCESSING' === $capabilities['name'] && 'ACTIVE' === $capabilities['status']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

}

if (!function_exists('goopter_ppcp_is_cart_contains_subscription')) {

    function goopter_ppcp_is_cart_contains_subscription() {
        $cart_contains_subscription = false;
        if (class_exists('WC_Subscriptions_Order') && class_exists('WC_Subscriptions_Cart')) {
            $cart_contains_subscription = WC_Subscriptions_Cart::cart_contains_subscription();
        }
        return apply_filters('goopter_ppcp_sdk_parameter_vault', $cart_contains_subscription);
    }

}

if (!function_exists('goopter_ppcp_is_subs_change_payment')) {

    function goopter_ppcp_is_subs_change_payment() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
        return ( isset($_GET['pay_for_order']) && ( isset($_GET['change_payment_method']) || isset($_GET['change_gateway_flag'])) );
    }

}

if (!function_exists('goopter_ppcp_get_order_total')) {

    function goopter_ppcp_get_order_total($order_id = null) {
        try {
            global $product;
            $total = 0;
            if (is_null($order_id)) {
                $order_id = absint(get_query_var('order-pay'));
            }
            if (is_product()) {

                if ($product->is_type('variable')) {
                    $variation_id = $product->get_id();
                    $is_default_variation = false;

                    $available_variations = $product->get_available_variations();

                    if (!empty($available_variations) && is_array($available_variations)) {

                        foreach ($available_variations as $variation_values) {

                            $attributes = !empty($variation_values['attributes']) ? $variation_values['attributes'] : '';

                            if (!empty($attributes) && is_array($attributes)) {

                                foreach ($attributes as $key => $attribute_value) {

                                    $attribute_name = str_replace('attribute_', '', $key);
                                    $default_value = $product->get_variation_default_attribute($attribute_name);
                                    if ($default_value == $attribute_value) {
                                        $is_default_variation = true;
                                    } else {
                                        $is_default_variation = false;
                                        break;
                                    }
                                }
                            }

                            if ($is_default_variation) {
                                $variation_id = !empty($variation_values['variation_id']) ? $variation_values['variation_id'] : 0;
                                break;
                            }
                        }
                    }

                    $variable_product = wc_get_product($variation_id);
                    $total = ( is_a($product, \WC_Product::class) ) ? wc_get_price_including_tax($variable_product) : 1;
                } else {
                    $total = ( is_a($product, \WC_Product::class) ) ? wc_get_price_including_tax($product) : 1;
                }
            } elseif (0 < $order_id) {
                $order = wc_get_order($order_id);
                if ($order === false) {
                    if (isset(WC()->cart) && 0 < WC()->cart->total) {
                        $total = (float) WC()->cart->total;
                    } else {
                        return 0;
                    }
                } else {
                    $total = (float) $order->get_total();
                }
            } elseif (isset(WC()->cart) && 0 < WC()->cart->total) {
                $total = (float) WC()->cart->total;
            }
            return $total;
        } catch (Exception $ex) {
            return 0;
        }
    }

}

if (!function_exists('goopter_ppcp_get_view_sub_order_url')) {

    function goopter_ppcp_get_view_sub_order_url($order_id) {
        $view_subscription_url = wc_get_endpoint_url('view-subscription', $order_id, wc_get_page_permalink('myaccount'));
        return apply_filters('wcs_get_view_subscription_url', $view_subscription_url, $order_id);
    }

}

if (!function_exists('goopter_ppcp_is_vault_required')) {

    function goopter_ppcp_is_vault_required($enable_tokenized_payments) {
        global $post, $product;
        $is_enable = false;
        if ($enable_tokenized_payments === false) {
            $is_enable = false;
        } elseif (goopter_ppcp_is_cart_subscription()) {
            $is_enable = true;
        } elseif ((is_checkout() || is_checkout_pay_page()) && $enable_tokenized_payments === true) {
            $is_enable = true;
        } elseif (is_product()) {
            $product_id = $post->ID;
            $product = wc_get_product($product_id);
            if ($product->is_type('subscription')) {
                $is_enable = true;
            }
        }
        return apply_filters('goopter_ppcp_vault_attribute', $is_enable);
    }

}

if (!function_exists('goopter_ppcp_is_cart_subscription')) {

    function goopter_ppcp_is_cart_subscription() {
        $is_enable = false;
        if (goopter_ppcp_is_cart_contains_subscription() || goopter_ppcp_is_subs_change_payment()) {
            $is_enable = true;
        }
        return apply_filters('goopter_ppcp_is_cart_subscription', $is_enable);
    }

}

if (!function_exists('goopter_ppcp_is_save_payment_method')) {

    function goopter_ppcp_is_save_payment_method($enable_tokenized_payments) {
        $is_enable = false;
        $new_payment_methods_to_check = [
            'wc-goopter_ppcp-new-payment-method',
            'wc-goopter_ppcp_cc-new-payment-method',
            'wc-goopter_ppcp_apple_pay-new-payment-method'
        ];
        if (goopter_ppcp_is_cart_subscription() && $enable_tokenized_payments === true) {
            $is_enable = true;
        }
        foreach ($new_payment_methods_to_check as $item) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
            if (isset($_POST[$item]) && 'true' === $_POST[$item]) {
                $is_enable = true;
                break;
            }
        }

        return apply_filters('goopter_ppcp_is_save_payment_method', $is_enable);
    }

}

if (!function_exists('goopter_ppcp_get_token_id_by_token')) {

    // function goopter_ppcp_get_token_id_by_token($token_id) {
    //     try {
    //         global $wpdb;
    //         $tokens = $wpdb->get_row(
    //                 $wpdb->prepare(
    //                         "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = %s",
    //                         $token_id
    //                 )
    //         );
    //         if (isset($tokens->token_id)) {
    //             return $tokens->token_id;
    //         }
    //         return '';
    //     } catch (Exception $ex) {

    //     }
    // }

    function goopter_ppcp_get_token_id_by_token($token_id) {
        try {
            // Get the WooCommerce payment token data store
            $data_store = WC_Data_Store::load('payment-token');
    
            // Search for tokens matching the provided token string
            $tokens = $data_store->search_tokens($token_id, 'token', 'payment_tokens', true);
    
            // If tokens are found, return the first token ID
            if (!empty($tokens) && is_array($tokens)) {
                return reset($tokens); // Return the first token ID
            }
    
            return '';
        } catch (Exception $ex) {
            // Handle exceptions if necessary
            return '';
        }
    }

}


if (!function_exists('goopter_ppcp_add_used_payment_method_name_to_subscription')) {

    function goopter_ppcp_add_used_payment_method_name_to_subscription($order_id) {
        try {
            if (function_exists('wcs_get_subscriptions_for_order')) {
                $subscriptions = wcs_get_subscriptions_for_order($order_id);
                if (!empty($subscriptions)) {
                    foreach ($subscriptions as $subscription) {
                        $order = wc_get_order($order_id);
                        $goopter_ppcp_used_payment_method = $order->get_meta('_goopter_ppcp_used_payment_method', true);
                        if (!empty($goopter_ppcp_used_payment_method)) {
                            $subscription->update_meta_data('_goopter_ppcp_used_payment_method', $goopter_ppcp_used_payment_method);
                            $subscription->save_meta_data();
                        }
                    }
                }
            }
        } catch (Exception $ex) {

        }
    }

}

if (!function_exists('goopter_is_vaulting_enable')) {

    function goopter_is_vaulting_enable($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products']) && !empty($result['products'])) {
            foreach ($result['products'] as $product) {
                if ($product['name'] === 'ADVANCED_VAULTING' &&
                        isset($product['vetting_status']) && $product['vetting_status'] === 'SUBSCRIBED' &&
                        isset($product['capabilities']) && in_array('PAYPAL_WALLET_VAULTING_ADVANCED', $product['capabilities'])) {
                    return true;
                }
            }
        }
        return false;
    }

}

if (!function_exists('goopter_ppcp_display_upgrade_notice_type')) {

    function goopter_ppcp_display_upgrade_notice_type($result = '') {
        try {
            $paypal_vault_supported_country = goopter_ppcp_apple_google_vault_supported_country();
            $notice_type = array();
            $notice_type['vault_upgrade'] = false;
            $notice_type['outside_us'] = false;
            $is_us = false;
            if (isset($result['country']) && !empty($result['country']) && in_array($result['country'], $paypal_vault_supported_country)) {
                $is_us = true;
            } elseif (function_exists('wc_get_base_location')) {
                $default = wc_get_base_location();
                $country = apply_filters('woocommerce_countries_base_country', $default['country']);
                if (in_array($country, $paypal_vault_supported_country)) {
                    $is_us = true;
                }
            }
            if (defined('PPCP_PAYPAL_COUNTRY')) {
                if (in_array(PPCP_PAYPAL_COUNTRY, $paypal_vault_supported_country)) {
                    $is_us = true;
                } else {
                    $is_us = false;
                }
            }
            $ppcp_gateway_list = ['goopter_ppcp', 'goopter_ppcp_apple_pay', 'goopter_ppcp_google_pay'];
            $active_ppcp_gateways = [];
            foreach (WC()->payment_gateways->get_available_payment_gateways() as $gateway) {
                if ('yes' === $gateway->enabled && $gateway->is_available() === true) {
                    if (in_array($gateway->id, $ppcp_gateway_list)) {
                        $active_ppcp_gateways[$gateway->id] = $gateway->id;
                    }
                }
            }
            $notice_type['active_ppcp_gateways'] = $active_ppcp_gateways;
            foreach (WC()->payment_gateways->get_available_payment_gateways() as $gateway) {
                if (in_array($gateway->id, array('goopter_ppcp')) && 'yes' === $gateway->enabled && $gateway->is_available() === true) {
                    if (empty($result)) {
                        $notice_type['vault_upgrade'] = false;
                    } elseif (goopter_is_vaulting_enable($result)) {
                        $notice_type['vault_upgrade'] = false;
                    } elseif ($is_us === true && goopter_is_vaulting_enable($result) === false) {
                        $notice_type['vault_upgrade'] = true;
                    }
                }
                if (in_array($gateway->id, array('goopter_ppcp')) && 'yes' === $gateway->enabled && $gateway->is_available() === true) {
                    if (empty($result)) {
                        $notice_type['enable_apple_pay'] = false;
                    } elseif (goopter_is_apple_pay_enable($result)) {
                        $notice_type['enable_apple_pay'] = false;
                    } elseif ($is_us === true && goopter_is_apple_pay_enable($result) === false) {
                        $notice_type['enable_apple_pay'] = true;
                    }
                }
            }
            return $notice_type;
        } catch (Exception $ex) {
            return $notice_type;
        }
    }

}


if (!function_exists('goopter_ppcp_display_notice')) {

    function goopter_ppcp_display_notice($response_data) {
        global $current_user;
        $user_id = $current_user->ID;
        if (get_user_meta($user_id, $response_data->id)) {
            return;
        }
        $message = '<div class="notice notice-warning goopter-notice" style="display:none;" id="' . $response_data->id . '">'
                . '<div class="goopter-notice-logo-push"><span> <img width="60px"src="' . $response_data->ans_company_logo . '"> </span></div>'
                . '<div class="goopter-notice-message">';
        if (!empty($response_data->ans_message_title)) {
            $message .= '<h2>' . $response_data->ans_message_title . '</h2>';
        }
        $message .= '<div class="goopter-notice-message-inner">'
                . '<p style="margin-top: 15px !important;line-height: 20px;">' . $response_data->ans_message_description . '</p><div class="goopter-notice-action">';
        if (!empty($response_data->ans_button_url)) {
            $message .= '<a href="' . $response_data->ans_button_url . '" class="button button-primary">' . $response_data->ans_button_label . '</a>';
        }

        if (isset($response_data->is_button_secondary) && $response_data->is_button_secondary === true) {
            $message .= '&nbsp&nbsp&nbsp<a target="_blank" href="' . $response_data->ans_secondary_button_url . '" class="button button-secondary">' . $response_data->ans_secondary_button_label . '</a>';
        }
        $message .= '</div></div>'
                . '</div>';
        if ($response_data->is_dismiss) {
            $message .= '<div class="goopter-notice-cta">'
                    . '<button class="goopter-notice-dismiss goopter-dismiss-welcome" data-msg="' . $response_data->id . '">Dismiss</button>'
                    . '</div>'
                    . '</div>';
        } else {
            $message .= '</div>';
        }
        echo wp_kses_post($message);
    }

}

if (!function_exists('goopter_ppcp_is_subscription_support_enabled')) {

    function goopter_ppcp_is_subscription_support_enabled() {
        try {
            if (class_exists('WC_Subscriptions') && function_exists('wcs_create_renewal_order')) {
                return true;
            }
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

}

if (!function_exists('goopter_is_apple_pay_enable')) {

    function goopter_is_apple_pay_enable($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products']) && !empty($result['products'])) {
            foreach ($result['products'] as $key => $product) {
                if (isset($product['vetting_status']) && ('SUBSCRIBED' === $product['vetting_status'] || 'APPROVED' === $product['vetting_status'] ) && isset($product['capabilities']) && is_array($product['capabilities']) && in_array('APPLE_PAY', $product['capabilities'])) {
                    foreach ($result['capabilities'] as $key => $capabilities) {
                        if (isset($capabilities['name']) && 'APPLE_PAY' === $capabilities['name'] && 'ACTIVE' === $capabilities['status']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

}

if (!function_exists('goopter_session_expired_exception')) {

    /**
     * Throws session not found exception message
     * @throws Exception
     */
    function goopter_session_expired_exception($error = '') {
        throw new Exception(esc_html($error), 302);
    }

}

if (!function_exists('goopter_ppcp_short_payment_method')) {

    function goopter_ppcp_short_payment_method(&$array, $keyX, $keyY, $position = 'before') {
        if (array_key_exists($keyX, $array) && array_key_exists($keyY, $array)) {
            $valueY = $array[$keyY];
            unset($array[$keyY]);

            $keys = array_keys($array);
            $indexX = array_search($keyX, $keys, true);

            if ($position === 'before') {
                $array = array_slice($array, 0, $indexX, true) +
                        array($keyY => $valueY) +
                        $array;
            } elseif ($position === 'after') {
                $array = array_slice($array, 0, $indexX + 1, true) +
                        array($keyY => $valueY) +
                        $array;
            }
        }
        return $array;
    }

}

if (!function_exists('goopter_has_saved_payment_token_been_used')) {

    function goopter_has_saved_payment_token_been_used() {
        $saved_tokens = ['wc-goopter_ppcp_apple_pay-payment-token', 'wc-goopter_ppcp-payment-token', 'wc-goopter_ppcp_cc-payment-token'];
        $goopter_has_saved_payment_token_been_used = false;
        foreach ($saved_tokens as $saved_token) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- no security issue
            if (!empty($_POST[$saved_token]) && $_POST[$saved_token] !== 'new') {
                return $goopter_has_saved_payment_token_been_used;
            }
        }
        return $goopter_has_saved_payment_token_been_used;
    }

}

if (!function_exists('goopter_get_checkout_url')) {

    function goopter_get_checkout_url(): string {
        $checkout_page_url = wc_get_checkout_url();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- from third party plugin
        if (isset($_REQUEST['wfacp_id'])) {
            $checkout_page_url = get_permalink(sanitize_text_field(wp_unslash($_REQUEST['wfacp_id'])));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended -- from third party plugin
        return $checkout_page_url;
    }

}

if (!function_exists('goopter_ppcp_order_item_meta_key_exists')) {

    function goopter_ppcp_order_item_meta_key_exists($order, $key) {
        foreach ($order->get_items(array('line_item', 'tax', 'shipping', 'fee', 'coupon')) as $item) {
            if ($item->meta_exists($key)) {
                return true;
            }
        }
        return false;
    }

}

if (!function_exists('goopter_ppcp_binary_search')) {

    function goopter_ppcp_binary_search($array, $target) {
        $low = 0;
        $high = count($array) - 1;
        $closest = null;
        while ($low <= $high) {
            $mid = (int) (($low + $high) / 2);
            $amount = (float) $array[$mid];

            if ($amount >= $target) {
                $closest = $array[$mid];
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }
        if ($closest === null) {
            $closest = max($array);
        }
        return $closest;
    }

}

if (!function_exists('goopter_get_matched_shortcode_attributes')) {

    function goopter_get_matched_shortcode_attributes($tag, $text) {
        preg_match_all('/' . get_shortcode_regex() . '/s', $text, $matches);
        $out = array();
        if (isset($matches[2])) {
            foreach ((array) $matches[2] as $key => $value) {
                if ($tag === $value)
                    $out[] = shortcode_parse_atts($matches[3][$key]);
            }
        }
        return $out;
    }

}

if (!function_exists('goopter_ppcp_get_awaiting_payment_order_id')) {

    function goopter_ppcp_get_awaiting_payment_order_id() {
        try {
            $order_id = absint(WC()->session->get('order_awaiting_payment'));
            if (!$order_id) {
                $order_id = absint(wc()->session->get('store_api_draft_order', 0));
            }
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order && in_array($order->get_status(), array('pending', 'failed', 'checkout-draft'))) {
                    return $order_id;
                }
            }
            return 0;
        } catch (Exception $ex) {

        }
    }

}

if (!function_exists('goopter_ppcp_is_cart_contains_free_trial')) {

    function goopter_ppcp_is_cart_contains_free_trial() {
        global $product;
        if (!class_exists('WC_Subscriptions_Product')) {
            return false;
        }
        if (is_product()) {
            if (WC_Subscriptions_Product::get_trial_length($product) > 0) {
                return true;
            }
        }
        $cart_contains_free_trial = false;
        if (goopter_ppcp_is_cart_contains_subscription()) {
            foreach (WC()->cart->cart_contents as $cart_item) {
                if (WC_Subscriptions_Product::get_trial_length($cart_item['data']) > 0) {
                    $cart_contains_free_trial = true;
                    break;
                }
            }
        }
        return $cart_contains_free_trial;
    }

}

if (!function_exists('goopter_ppcp_apple_google_vault_supported_country')) {

    function goopter_ppcp_apple_google_vault_supported_country() {
        return array(
            'AU', 'AT', 'BE', 'BG', 'CA', 'CY', 'CZ', 'DK', 'EE', 'FI',
            'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU',
            'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
            'GB', 'US'
        );
    }

}

if (!function_exists('goopter_ppcp_pay_later_messaging')) {

    function goopter_ppcp_pay_later_messaging() {
        $page = '';
        if ( (did_action('wp') && is_front_page()) || (did_action('wp') && is_home())) {
            $page = 'home';
        } elseif (is_product_category() || (did_action('wp') && is_category())) {
            $page = 'category';
        } elseif (is_product()) {
            $page = 'product';
        } elseif (is_cart()) {
            $page = 'cart';
        } elseif (is_checkout() || is_checkout_pay_page()) {
            $page = 'payment';
        }
    }

}
