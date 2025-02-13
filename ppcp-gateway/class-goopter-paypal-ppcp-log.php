<?php

defined('ABSPATH') || exit;

class Goopter_PayPal_PPCP_Log {

    public $log_option;
    public $logger = false;
    protected static $_instance = null;
    public $setting_obj;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->goopter_ppcp_load_class();
        $this->log_option = $this->setting_obj->get('debug', 'everything');
    }

    public function log($message, $level = 'info') {
        if ($this->log_option == 'everything' || ( $level == 'error' && $this->log_option == 'errors_warnings_only')) {
            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
            $this->logger->log($level, $message, array('source' => 'goopter_ppcp'));
        }
    }

    public function temp_log($message, $level = 'info') {
        if (empty($this->logger)) {
            $this->logger = wc_get_logger();
        }
        $this->logger->log($level, $message, array('source' => 'goopter_ppcp_temp'));
    }
    
    public function migration_log($message, $level = 'info') {
        if (empty($this->logger)) {
            $this->logger = wc_get_logger();
        }
        $this->logger->log($level, $message, array('source' => 'goopter_ppcp_migration'));
    }

    public function goopter_ppcp_load_class() {
        try {
            if (!class_exists('Goopter_WC_Gateway_PPCP_Settings')) {
                include_once PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . '/ppcp-gateway/class-wc-gateway-ppcp-goopter-settings.php';
            }
            $this->setting_obj = Goopter_WC_Gateway_PPCP_Settings::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' .$ex->getLine(), 'error');
            $this->log($ex->getMessage(), 'error');
        }
    }

}
