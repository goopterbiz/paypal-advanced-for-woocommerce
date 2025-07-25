<?php
if (!defined('ABSPATH')) {
    exit;
}

class Goopter_WC_Gateway_Direct_Pay extends WC_Payment_Gateway {

    use Goopter_WC_Gateway_Base;

    public $enable_paypal_checkout_page;
    public $sandbox;
    public $merchant_id;
    public $soft_descriptor;
    public $is_enabled;

    public function __construct() {
        try {
            $this->id = 'goopter_direct_pay';
            $this->icon = false;
            $this->has_fields = true;
            $this->goopter_ppcp_load_class();
            $this->setGatewaySupports();

            $payment_title = $this->setting_obj->get('goopter_direct_pay_title', 'Clover');
            $this->title = esc_html($payment_title);

            $this->method_title = apply_filters('goopter_direct_pay_title', $this->title);
            $this->method_description = $this->setting_obj->get('goopter_direct_pay_description', 'Accept payments using Credit Card / Apple Pay / Google Pay.');
            $this->enable_paypal_checkout_page = 'yes' === $this->setting_obj->get('enable_paypal_checkout_page', 'yes');
            
            $this->sandbox = 'yes' === $this->setting_obj->get('goopter_direct_pay_testmode', 'no');
            $this->is_enabled = 'yes' === $this->setting_obj->get('enable_goopter_direct_pay', 'no');
            $this->merchant_id = $this->setting_obj->get('goopter_direct_pay_merchant_id', '');
            $this->soft_descriptor = $this->setting_obj->get('goopter_direct_pay_soft_descriptior', '');
        } catch (Exception $ex) {
            
        }
    }

    public function process_payment($woo_order_id) {
        try {
            $order = wc_get_order($woo_order_id);
            $url = $this->get_direct_pay_token_url($order);
            if (is_null($url)) {
                wc_add_notice(__('Failed to get payment token. Please try again later.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'), 'error');
                throw new Exception(__('Failed to get payment token.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
            }
            $order->update_status('pending');
            $return_data = [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];

            return $return_data;
        } catch (Exception $ex) {
            return array(
				'result' => 'failed',
				'exceptionMessage' => $ex->getMessage(),
				'message' => __('An error has occurred; Please try again later.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
				'error_code' => 'Unexpected',
			);
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        if ($amount <= 0) {
            return new WP_Error('error', __('Invalid refund amount', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
        }
        
        $order = wc_get_order($order_id);
        $order_status = $order->get_status();
        $order_transaction_id = $order->get_transaction_id();
        // order status check
        if($order && $this->can_refund_order($order) && $order_transaction_id && ($order_status === 'completed' || $order_status === 'processing')) {
            $is_success = $this->refund_request($order, $amount);
            if($is_success) {
                return true;
            } else {
                return new WP_Error('error', __('Refund failed.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
            }
        } else {
            return new WP_Error('error', __('Refund failed.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
        }
    }

    public function is_available() {
        return $this->is_enabled === true;
    }

    public function payment_fields() {
        echo esc_html($this->method_description);
    }

    public function refund_request($order, $amount) {
        // payload for the request
        $body = wp_json_encode([
            'testmode' => $this->sandbox,
            'action_name' => 'direct_payment_refund_transaction',
            // this is for clover but the endpoint was orginally designed for paypal
            'paypal_url' => '/v1/refunds',
            'paypal_method' => 'POST',
            'paypal_header' => [
                'Content-Type' => 'application/json',
            ],
            'paypal_body' => [
                'charge' => $order->get_transaction_id(),
                'amount' => round($amount * 100), // Clover expects amount in cents
            ],
            'payment_platform' => 'clover',
        ]);

        // Arguments for the request
        $args = [
            'method'      => 'POST',
            'timeout'     => 70,
            'headers'     => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Merchant-Id' => $this->merchant_id,
                'Soft-Descriptor' => $this->soft_descriptor,
            ],
            'body'        => $body,
        ];

        $response = wp_remote_post( PAYPAL_FOR_WOOCOMMERCE_PPCP_GOOPTER_WEB_SERVICE, $args );
        if ( is_wp_error( $response ) ) {
            // Something went wrong—show or log the error
            error_log( 'HTTP POST failed: ' . $response->get_error_message() );
            return false;
        }
        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset($result['id']);
    }

    public function get_direct_pay_token_url($order) {
        // URL you want to POST to
        $url = 'https://pay.goopter.com/token/v1';
        
        // payload for the request
        $body = wp_json_encode(['data' => [
            'order_id' => $order->get_id(),
            'currency' => $order->get_currency(),
            'price' => round($order->get_total(), 2),
            'webhook' => home_url('/?wc-api=goopter_direct_pay_webhook'),
        ]]);

        // Arguments for the request
        $args = [
            'method'      => 'POST',
            'timeout'     => 70,
            'headers'     => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Merchant-Id' => $this->merchant_id,
                'Soft-Descriptor' => $this->soft_descriptor,
                'Is-Paypal-Live' => !$this->sandbox,
            ],
            'body'        => $body,
        ];

        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            // Something went wrong—show or log the error
            error_log( 'HTTP POST failed: ' . $response->get_error_message() );
        } else {
            $data    = wp_remote_retrieve_body( $response );
            $result = json_decode( $data, true );
            if ( isset($result['href']) ) {
                $order->update_meta_data('_goopter_direct_pay_href', $result['href']);
                return $result['href'];
            }
        }
        return null;
    }
}