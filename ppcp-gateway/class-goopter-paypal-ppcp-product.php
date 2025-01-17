<?php

defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Product extends WC_Form_Handler {

    protected static $_instance = null;
    private static ?Goopter_PayPal_PPCP_Log $api_log;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        if (!class_exists('Goopter_PayPal_PPCP_Log')) {
            include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-log.php';
        }
        self::$api_log = Goopter_PayPal_PPCP_Log::instance();
    }

    public static function goopter_ppcp_add_to_cart_action($url = null) {
        try {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- no security issue
            if (!isset($_REQUEST['goopter_ppcp-add-to-cart']) || !is_numeric(sanitize_text_field(wp_unslash($_REQUEST['goopter_ppcp-add-to-cart'])))) {
                return;
            }
            wc_nocache_headers();
            $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint(wp_unslash($_REQUEST['goopter_ppcp-add-to-cart'])));
            $was_added_to_cart = false;
            $adding_to_cart = wc_get_product($product_id);
            if (!$adding_to_cart) {
                return;
            }
            // Empty the cart before purchasing the product through Smart button
            // WC()->cart->empty_cart();
            $add_to_cart_handler = apply_filters('woocommerce_add_to_cart_handler', $adding_to_cart->get_type(), $adding_to_cart);
            if ('variable' === $add_to_cart_handler || 'variation' === $add_to_cart_handler) {
                $was_added_to_cart = self::goopter_ppcp_add_to_cart_handler_variable($product_id);
            } elseif ('grouped' === $add_to_cart_handler) {
                $was_added_to_cart = self::goopter_ppcp_add_to_cart_handler_grouped($product_id);
            } elseif (has_action('woocommerce_add_to_cart_handler_' . $add_to_cart_handler)) {
                do_action('woocommerce_add_to_cart_handler_' . $add_to_cart_handler, $url);
            } else {
                $was_added_to_cart = self::goopter_ppcp_add_to_cart_handler_simple($product_id);
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended -- no security issue
        } catch (Exception $ex) {
            self::$api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            self::$api_log->log($ex->getMessage(), 'error');
        }
    }

    private static function goopter_ppcp_add_to_cart_handler_simple($product_id) {
        try {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no security issue
            $quantity = empty($_REQUEST['quantity']) ? 1 : wc_stock_amount(sanitize_text_field(wp_unslash($_REQUEST['quantity'])));
            $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);

            if ($passed_validation && false !== WC()->cart->add_to_cart($product_id, $quantity)) {
                wc_add_to_cart_message(array($product_id => $quantity), true);
                return true;
            }
            return false;
        } catch (Exception $ex) {
            self::$api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            self::$api_log->log($ex->getMessage(), 'error');
        }
    }

    private static function goopter_ppcp_add_to_cart_handler_grouped($product_id) {
        try {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- no security issue
            $was_added_to_cart = false;
            $added_to_cart = array();
            $items = isset($_REQUEST['quantity']) && is_array($_REQUEST['quantity']) ? sanitize_text_field(wp_unslash($_REQUEST['quantity'])) : array();
            if (!empty($items)) {
                $quantity_set = false;
                foreach ($items as $item => $quantity) {
                    if ($quantity <= 0) {
                        continue;
                    }
                    $quantity_set = true;
                    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $item, $quantity);
                    remove_action('woocommerce_add_to_cart', array(WC()->cart, 'calculate_totals'), 20, 0);
                    if ($passed_validation && false !== WC()->cart->add_to_cart($item, $quantity)) {
                        $was_added_to_cart = true;
                        $added_to_cart[$item] = $quantity;
                    }
                    add_action('woocommerce_add_to_cart', array(WC()->cart, 'calculate_totals'), 20, 0);
                }
                if (!$was_added_to_cart && !$quantity_set) {
                    wc_add_notice(__('Please choose the quantity of items you wish to add to your cart&hellip;', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), 'error');
                } elseif ($was_added_to_cart) {
                    wc_add_to_cart_message($added_to_cart);
                    WC()->cart->calculate_totals();
                    return true;
                }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended -- no security issue
            } elseif ($product_id) {
                wc_add_notice(__('Please choose a product to add to your cart&hellip;', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), 'error');
            }
            return false;
        } catch (Exception $ex) {
            self::$api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            self::$api_log->log($ex->getMessage(), 'error');
        }
    }

    private static function goopter_ppcp_add_to_cart_handler_variable($product_id) {
        try {
            if (!isset($_POST['woocommerce-process-checkout-nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
                // Nonce is invalid
                $logger = wc_get_logger();  // Get the logger instance
                $logger->error('goopter ppcp add to cart handler variable nonce verification failed. Nonce not valid.', array('source' => 'ppcp-gateway/class-goopter-paypal-ppcp-product.php'));
            }

            $variation_id = empty($_REQUEST['variation_id']) ? '' : absint(wp_unslash($_REQUEST['variation_id']));
            $quantity = empty($_REQUEST['quantity']) ? 1 : wc_stock_amount(sanitize_text_field(wp_unslash($_REQUEST['quantity'])));
            $missing_attributes = array();
            $variations = array();
            $adding_to_cart = wc_get_product($product_id);
            if (!$adding_to_cart) {
                return false;
            }
            if ($adding_to_cart->is_type('variation')) {
                $variation_id = $product_id;
                $product_id = $adding_to_cart->get_parent_id();
                $adding_to_cart = wc_get_product($product_id);
                if (!$adding_to_cart) {
                    return false;
                }
            }
            $posted_attributes = array();
            foreach ($adding_to_cart->get_attributes() as $attribute) {
                if (!$attribute['is_variation']) {
                    continue;
                }
                $attribute_key = 'attribute_' . sanitize_title($attribute['name']);
                if (isset($_REQUEST[$attribute_key])) {
                    if ($attribute['is_taxonomy']) {
                        $value = sanitize_title(wp_unslash($_REQUEST[$attribute_key]));
                    } else {
                        $value = html_entity_decode(wc_clean(sanitize_text_field(wp_unslash($_REQUEST[$attribute_key]))), ENT_QUOTES, get_bloginfo('charset'));
                    }
                    $posted_attributes[$attribute_key] = $value;
                }
            }
            if (empty($variation_id)) {
                $data_store = WC_Data_Store::load('product');
                $variation_id = $data_store->find_matching_product_variation($adding_to_cart, $posted_attributes);
            }
            if (empty($variation_id)) {
                throw new Exception(__('Please choose product options&hellip;', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
            }
            $variation_data = wc_get_product_variation_attributes($variation_id);
            foreach ($adding_to_cart->get_attributes() as $attribute) {
                if (!$attribute['is_variation']) {
                    continue;
                }
                $attribute_key = 'attribute_' . sanitize_title($attribute['name']);
                $valid_value = isset($variation_data[$attribute_key]) ? $variation_data[$attribute_key] : '';
                if (isset($posted_attributes[$attribute_key])) {
                    $value = $posted_attributes[$attribute_key];
                    if ($valid_value === $value) {
                        $variations[$attribute_key] = $value;
                    } elseif ('' === $valid_value && in_array($value, $attribute->get_slugs(), true)) {
                        $variations[$attribute_key] = $value;
                    } else {
                        // Translators: %s is the attribute label.
                        throw new Exception(sprintf(__('Invalid value posted for %s', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), wc_attribute_label($attribute['name'])));
                    }
                } elseif ('' === $valid_value) {
                    $missing_attributes[] = wc_attribute_label($attribute['name']);
                }
            }
            if (!empty($missing_attributes)) {
                // Translators: %s is the list of missing required fields.
                throw new Exception(sprintf(_n('%s is a required field', '%s are required fields', count($missing_attributes), 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), wc_format_list_of_items($missing_attributes)));
            }
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return false;
        }
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations);
        if ($passed_validation && false !== WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations)) {
            wc_add_to_cart_message(array($product_id => $quantity), true);
            return true;
        }
        return false;
    }

}
