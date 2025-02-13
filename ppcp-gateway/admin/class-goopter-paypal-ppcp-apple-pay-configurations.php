<?php

class Goopter_PayPal_PPCP_Apple_Pay_Configurations
{
    public static $_instance;
    private ?Goopter_PayPal_PPCP_Request $api_request;
    private ?Goopter_PayPal_PPCP_Payment $payment_request;
    private string $host;
    private Goopter_PayPal_PPCP_Apple_Domain_Validation $apple_pay_domain_validation;
    private string $payPalDomainValidationFile = 'https://www.paypalobjects.com/.well-known/apple-developer-domain-association';

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        if (!class_exists('Goopter_PayPal_PPCP_Request')) {
            include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-request.php';
        }
        if (!class_exists('Goopter_PayPal_PPCP_Payment')) {
            include_once ( PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-goopter-paypal-ppcp-payment.php');
        }
        $this->payment_request = Goopter_PayPal_PPCP_Payment::instance();
        $this->api_request = Goopter_PayPal_PPCP_Request::instance();
        $this->apple_pay_domain_validation = Goopter_PayPal_PPCP_Apple_Domain_Validation::instance();
        if ($this->payment_request->is_sandbox) {
            $this->host = 'api.sandbox.paypal.com';
        } else {
            $this->host = 'api.paypal.com';
        }

        add_action('wp_ajax_goopter_list_apple_pay_domain', [$this, 'listApplePayDomain']);
        add_action('wp_ajax_goopter_register_apple_pay_domain', [$this, 'registerApplePayDomain']);
        add_action('wp_ajax_goopter_remove_apple_pay_domain', [$this, 'removeApplePayDomain']);
    }

    private function getApiCallHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => '',
            'prefer' => 'return=representation',
            'Paypal-Auth-Assertion' => $this->payment_request->goopter_ppcp_paypalauthassertion(),
            'X-PAYPAL-SECURITY-CONTEXT' => ''
        ];
    }

    public function listApplePayDomain($returnRawResponse = false)
    {
        $args = [
            'method' => 'GET',
            'timeout' => 60,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'body' => [],
            'headers' => $this->getApiCallHeaders(),
            'cookies' => []
        ];

        $domainQueryParams = [
            'provider_type' => 'APPLE_PAY', 'page_size' => 10, 'page' => 1
        ];
        $domainGetUrl = add_query_arg($domainQueryParams, 'https://' . $this->host . '/v1/customer/wallet-domains');
        $response = $this->api_request->request($domainGetUrl, $args, 'apple_pay_domain_list');
        $jsonResponse = ['status' => false];
        if (isset($response['total_items'])) {
            $jsonResponse['status'] = true;
            $allDomains = [];
            if ($response['total_items'] > 0) {
                foreach ($response['wallet_domains'] as $domains) {
                    $allDomains[] = ['domain' => $domains['domain']['name'], 'provider_type' => $domains['provider_type']];
                }
            }
            $jsonResponse['domains'] = $allDomains;
            $jsonResponse['message'] = __('Domain listing retrieved successfully.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
        } else {
            $this->payment_request->error_email_notification = false;
            $jsonResponse['message'] = $this->payment_request->goopter_ppcp_get_readable_message($response);
        }
        if ($returnRawResponse) {
            return $jsonResponse;
        }

        /**
         * Add the file in physical path so that If due to some reasons server handles the path request then that should
         * find the file in path
         */
        try {
            $this->addDomainValidationFiles();
        } catch (Exception $exception) {
            echo '<div class="error">' . esc_html( $exception->getMessage() ) . '</div>';
        }
        try {
            $checkIsDomainAdded = self::isApplePayDomainAdded($jsonResponse);
            if ($checkIsDomainAdded) {
                $successMessage = __('Your domain has been registered successfully, Close the popup and refresh the page to update the status.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
                $applePayGateway = Goopter_WC_Gateway_Apple_Pay::instance();
                $applePayGateway->update_option('apple_pay_domain_added', 'yes');
            }
        } catch (Exception $exception) {

        }
        require_once (PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/admin/templates/apple-pay-domain-list.php');
        die;
    }

    public static function isApplePayDomainAdded($response = null): bool
    {
        if (empty($response)) {
            $addedDomains = get_transient("goopter_apple_pay_domain_list_cache");
            if (!is_array($addedDomains)) {
                $instance = Goopter_PayPal_PPCP_Apple_Pay_Configurations::instance();
                $addedDomains = $instance->listApplePayDomain(true);
                set_transient("goopter_apple_pay_domain_list_cache", $addedDomains, 24 * HOUR_IN_SECONDS);
            }
        } else {
            $addedDomains = $response;
        }

        if ($addedDomains['status'] && count($addedDomains['domains'])) {
            $domainName = wp_parse_url( get_site_url(), PHP_URL_HOST );
            foreach ($addedDomains['domains'] as $addedDomain) {
                if ($addedDomain['domain'] == $domainName) {
                    return true;
                }
            }
            return false;
        }
        throw new Exception('Unable to retrieve apple pay domain list.');
    }

    public static function autoRegisterDomain($is_domain_added = false): bool
    {
        try {
            if (!self::isApplePayDomainAdded()) {
                /**
                 * Try to register the domain max 1 time, this reduces the register attempt in case add domain fails
                 * If domain registration fails its expected user will register manually.
                 */
                $auto_register_status = get_option('goopter_apple_pay_domain_reg_retries', 0);
                if ($auto_register_status > 0) {
                    return false;
                }
                update_option('goopter_apple_pay_domain_reg_retries', 1);
                $instance = Goopter_PayPal_PPCP_Apple_Pay_Configurations::instance();

                /**
                 * Add the file in physical path so that If due to some reasons server handles the path request then that should
                 * find the file in path
                 */
                try {
                    $instance->addDomainValidationFiles();
                } catch (Exception $exception) {}

                $domainNameToRegister = wp_parse_url( get_site_url(), PHP_URL_HOST );
                $result = $instance->registerDomain($domainNameToRegister);
                return $result['status'];
            } else {
                return true;
            }
        } catch (Exception $ex) {

        }
        return $is_domain_added;
    }

    /**
     * @throws Exception
     */
    public static function autoUnRegisterDomain(): bool
    {
        if (self::isApplePayDomainAdded()) {
            $instance = Goopter_PayPal_PPCP_Apple_Pay_Configurations::instance();
            $domainNameToRemove = wp_parse_url(get_site_url(), PHP_URL_HOST);
            $result = $instance->removeDomain($domainNameToRemove);
            return $result['status'];
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function registerDomain($domainNameToRegister)
    {
        if (!filter_var($domainNameToRegister, FILTER_VALIDATE_DOMAIN)) {
            throw new Exception(esc_html__('Please enter a valid domain name to register.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'));
        }
        $domainParams = [
            "provider_type" => "APPLE_PAY",
            "domain" => [
                "name" => $domainNameToRegister
            ]
        ];
        $args = [
            'method' => 'POST',
            'timeout' => 60,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'body' => $domainParams,
            'headers' => $this->getApiCallHeaders(),
            'cookies' => array()
        ];
        $domainGetUrl = 'https://' . $this->host . '/v1/customer/wallet-domains';
        $response = $this->api_request->request($domainGetUrl, $args, 'apple_pay_domain_add');
        if (isset($response['domain'])) {
            delete_transient('goopter_apple_pay_domain_list_cache');
            return [
                'status' => true,
                'domain' => $domainNameToRegister,
                'message' => __('Domain has been added successfully.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
                'remove_url' => add_query_arg(['domain' => $domainNameToRegister, 'action' => 'goopter_remove_apple_pay_domain'], admin_url('admin-ajax.php'))
            ];
        } else {
            $this->payment_request->error_email_notification = false;
            $message = $this->payment_request->goopter_ppcp_get_readable_message($response);
            if (str_contains($message, 'DOMAIN_ALREADY_REGISTERED')) {
                $message = __('Domain is already registered.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
            } elseif (str_contains($message, 'DOMAIN_REGISTERED_WITH_ANOTHER_MERCHANT')) {
                $message = __('Domain is registered with another merchant.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce');
            }
            return ['status' => false, 'message' => __('An error occurred.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce') . "\n\n" . $message];
        }
    }

    public function removeDomain($domainNameToRemove): array
    {
        $domainParams = [
            "provider_type" => "APPLE_PAY",
            "domain" => [
                "name" => $domainNameToRemove
            ],
            "reason" => "Requested by site administrator"
        ];
        $args = [
            'method' => 'POST',
            'timeout' => 60,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'body' => $domainParams,
            'headers' => $this->getApiCallHeaders(),
            'cookies' => array()
        ];
        $domainGetUrl = 'https://' . $this->host . '/v1/customer/unregister-wallet-domain';
        $response = $this->api_request->request($domainGetUrl, $args, 'apple_pay_domain_remove');
        if (isset($response['domain'])) {
            delete_transient('goopter_apple_pay_domain_list_cache');
            return [
                'status' => true,
                'message' => __('Domain has been removed successfully.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')
            ];
        } else {
            $this->payment_request->error_email_notification = false;
            $message = $this->payment_request->goopter_ppcp_get_readable_message($response);
            return ['status' => false, 'message' => 'An error occurred.' . "\n\n" .$message];
        }
    }

    public function registerApplePayDomain()
    {
        if ( !isset($_POST['goopter_register_apple_pay_domain_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['goopter_register_apple_pay_domain_nonce'])), 'goopter_register_apple_pay_domain_nonce') ) {
            $logger = wc_get_logger();  // Get the logger instance
            $logger->error('register apple pay domain Nonce verification failed. Nonce not valid.', array('source' => 'ppcp-gateway/admin/class-goopter-paypal-ppcp-apple-pay-configurations.php'));
        }

        // $domainNameToRegister = sanitize_text_field(wp_unslash($_POST['apple_pay_domain'])) ?? wp_parse_url( get_site_url(), PHP_URL_HOST );
        $domainNameToRegister = isset($_POST['apple_pay_domain']) && !empty($_POST['apple_pay_domain']) 
            ? sanitize_text_field(wp_unslash($_POST['apple_pay_domain'])) 
            : wp_parse_url(get_site_url(), PHP_URL_HOST);

        try {
            $result = $this->registerDomain($domainNameToRegister);
            wp_send_json($result);
        } catch (Exception $ex) {
            wp_send_json(['status' => false, 'message' => $ex->getMessage()]);
        }
        die;
    }

    public function removeApplePayDomain()
    {
        if ( !isset($_POST['goopter_remove_apple_pay_domain_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['goopter_remove_apple_pay_domain_nonce'])), 'goopter_remove_apple_pay_domain_nonce') ) {
            $logger = wc_get_logger();  // Get the logger instance
            $logger->error('remove apple pay domain Nonce verification failed. Nonce not valid.', array('source' => 'ppcp-gateway/admin/class-goopter-paypal-ppcp-apple-pay-configurations.php'));
        }

        // $domainNameToRemove = sanitize_text_field(wp_unslash($_REQUEST['domain'])) ?? wp_parse_url( get_site_url(), PHP_URL_HOST );
        $domainNameToRemove = isset($_REQUEST['domain']) && !empty($_REQUEST['domain']) 
            ? sanitize_text_field(wp_unslash($_REQUEST['domain'])) 
            : wp_parse_url(get_site_url(), PHP_URL_HOST);
        $result = $this->removeDomain($domainNameToRemove);
        wp_send_json($result);
        die;
    }

    private function addDomainValidationFiles()
    {
        // $fileDir = ABSPATH.'.well-known';
        // if (!is_dir($fileDir)) {
        //     mkdir($fileDir);
        // }
        global $wp_filesystem;
        // Include the WP_Filesystem functionality if it’s not already loaded.
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        // Initialize WP_Filesystem.
        WP_Filesystem();
        $fileDir = ABSPATH . '.well-known';
        // Check if the directory exists, and create it if it doesn't.
        if ( ! $wp_filesystem->is_dir( $fileDir ) ) {
            $wp_filesystem->mkdir( $fileDir );
        }

        $localFileLoc = $this->apple_pay_domain_validation->getDomainAssociationLibFilePath();
        $domainValidationFile = $this->apple_pay_domain_validation->getDomainAssociationFilePath();

        $targetLocation = get_home_path() . $domainValidationFile;

        // PFW-1554 - Handles the INCORRECT_DOMAIN_VERIFICATION_FILE error
        try {
            if (!$this->apple_pay_domain_validation->isSandbox()) {
                $this->updateDomainVerificationFileContent($localFileLoc);
            }
        } catch (Exception $exception) {
            throw new Exception(esc_html__("Unable to update the verification file content. Error: ", 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce') . esc_html($exception->getMessage()));
        }
        if (file_exists($targetLocation)) {
            wp_delete_file($targetLocation);
            wp_delete_file($targetLocation . '.txt');
        }
        if (!copy($localFileLoc, $targetLocation)) {
            throw new Exception(esc_html(sprintf(
                'Unable to copy the files from %s to location %s',
                esc_html($localFileLoc),
                esc_html($targetLocation)
            )));
        }
        // Add the .txt version to make sure it works.
        copy($localFileLoc, $targetLocation . '.txt');
        return true;
    }

    // private function updateDomainVerificationFileContent($localFileLocation)
    // {
    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, $this->payPalDomainValidationFile);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     curl_setopt($ch, CURLOPT_HEADER, false);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //     $response = curl_exec($ch);
    //     $resultStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //     curl_close($ch);

    //     if (in_array($resultStatus, [200, 304])) {
    //         $fp = fopen($localFileLocation, "w");
    //         fwrite($fp, $response);
    //         fclose($fp);
    //     }
    // }

    private function updateDomainVerificationFileContent($localFileLocation)
    {
        $logger = wc_get_logger(); // WooCommerce logger
        $context = ['source' => 'paypal-domain-verification'];
    
        // Perform the GET request using wp_remote_get
        $response = wp_remote_get($this->payPalDomainValidationFile, [
            'sslverify' => false, // Disable SSL verification if necessary
        ]);
    
        // Check for errors in the request
        if (is_wp_error($response)) {
            $logger->error('Error fetching PayPal domain validation file: ' . $response->get_error_message(), $context);
            return;
        }
    
        // Get the HTTP status code
        $resultStatus = wp_remote_retrieve_response_code($response);
    
        // // Check if the response status is 200 or 304
        // if (in_array($resultStatus, [200, 304])) {
        //     // Retrieve the response body
        //     $body = wp_remote_retrieve_body($response);
    
        //     // Write the response body to the specified file location
        //     if ($fp = fopen($localFileLocation, "w")) {
        //         fwrite($fp, $body);
        //         fclose($fp);
        //     } else {
        //         $logger->error("Unable to write to file: $localFileLocation", $context);
        //     }
        // } else {
        //     $logger->warning("Unexpected HTTP status code: $resultStatus", $context);
        // }

        global $wp_filesystem;
        // Load the WP_Filesystem if it’s not already loaded
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        // Initialize WP_Filesystem
        WP_Filesystem();
        // Check if the response status is 200 or 304
        if ( in_array( $resultStatus, [200, 304] ) ) {
            // Retrieve the response body
            $body = wp_remote_retrieve_body( $response );
            // Write the response body to the specified file location
            if ( $wp_filesystem->put_contents( $localFileLocation, $body, FS_CHMOD_FILE ) ) {
                // File successfully written
            } else {
                $logger->error( "Unable to write to file: $localFileLocation", $context );
            }
        } else {
            $logger->warning( "Unexpected HTTP status code: $resultStatus", $context );
        }

    }
    
}
