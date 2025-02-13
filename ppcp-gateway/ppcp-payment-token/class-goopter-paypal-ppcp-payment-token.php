<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Goopter_WC_PayPal_PPCP_Payment_Token {

    protected static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function goopter_ppcp_add_paypal_generated_customer_id($customer_id, $is_sandbox) {
        try {
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $user_id = (int) $user->ID;
                $prefix_ppcp_paypal_customer_id = ($is_sandbox === true) ? 'sandbox_' : '';
                update_user_meta($user_id, $prefix_ppcp_paypal_customer_id . 'goopter_ppcp_paypal_customer_id', $customer_id);
            }
        } catch (Exception $ex) {

        }
    }

    public function goopter_ppcp_get_paypal_generated_customer_id_for_renewal($is_sandbox, $user_id) {
        try {
            $goopter_ppcp_paypal_customer_id = '';
            $prefix_ppcp_paypal_customer_id = ($is_sandbox === true) ? 'sandbox_' : '';
            if (!get_user_meta($user_id, $prefix_ppcp_paypal_customer_id . 'goopter_ppcp_paypal_customer_id')) {
                return false;
            } else {
                $goopter_ppcp_paypal_customer_id = get_user_meta($user_id, $prefix_ppcp_paypal_customer_id . 'goopter_ppcp_paypal_customer_id', true);
            }
            if (!empty($goopter_ppcp_paypal_customer_id)) {
                return $goopter_ppcp_paypal_customer_id;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    public function goopter_ppcp_get_paypal_generated_customer_id($is_sandbox) {
        try {
            if (is_user_logged_in()) {
                $goopter_ppcp_paypal_customer_id = '';
                $user = wp_get_current_user();
                $user_id = (int) $user->ID;
                $prefix_ppcp_paypal_customer_id = ($is_sandbox === true) ? 'sandbox_' : '';
                if (!get_user_meta($user_id, $prefix_ppcp_paypal_customer_id . 'goopter_ppcp_paypal_customer_id')) {
                    return false;
                } else {
                    $goopter_ppcp_paypal_customer_id = get_user_meta($user_id, $prefix_ppcp_paypal_customer_id . 'goopter_ppcp_paypal_customer_id', true);
                }
                if (!empty($goopter_ppcp_paypal_customer_id)) {
                    return $goopter_ppcp_paypal_customer_id;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }
    
    public function goopter_ppcp_get_paypal_generated_customer_id_by_user_id($is_sandbox, $user_id) {
        try {
            if (!empty($user_id)) {
                $goopter_ppcp_paypal_customer_id = '';
                $prefix_ppcp_paypal_customer_id = ($is_sandbox === true) ? 'sandbox_' : '';
                if (!get_user_meta($user_id, $prefix_ppcp_paypal_customer_id . 'goopter_ppcp_paypal_customer_id')) {
                    return false;
                } else {
                    $goopter_ppcp_paypal_customer_id = get_user_meta($user_id, $prefix_ppcp_paypal_customer_id . 'goopter_ppcp_paypal_customer_id', true);
                }
                if (!empty($goopter_ppcp_paypal_customer_id)) {
                    return $goopter_ppcp_paypal_customer_id;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

}
