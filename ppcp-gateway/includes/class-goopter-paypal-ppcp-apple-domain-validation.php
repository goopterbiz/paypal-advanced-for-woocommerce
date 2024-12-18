<?php

class Goopter_PayPal_PPCP_Apple_Domain_Validation {
    use Goopter_PPCP_Core;

    private Goopter_WordPress_Custom_Route_Handler $route_handler;

    public function __construct()
    {
        $this->goopter_ppcp_load_class();
        $this->route_handler = Goopter_WordPress_Custom_Route_Handler::instance();
        add_action('plugins_loaded', [$this, 'addDomainValidationRoute'], 100);
    }

    public function addDomainValidationRoute()
    {
        $file_name = $this->getDomainAssociationFilePath();
        if (!empty($file_name)) {
            $this->route_handler->addRoute($file_name, [$this, 'domainAssociationFile']);
        }
    }

    public function domainAssociationFile(): void
    {
        header('Content-type: text/plain');
        ob_start();
        include $this->getDomainAssociationLibFilePath();
        $contents = ob_get_clean();
        echo esc_html($contents);
        exit();
    }

    public function getDomainAssociationLibFilePath(): string {
        $file_path = '/ppcp-gateway/lib/apple-validation/' . ($this->isSandbox() ? 'domain-association-file-sandbox.txt' : 'apple-developer-merchantid-domain-association.txt');
        return PAYPAL_FOR_WOOCOMMERCE_PLUGIN_DIR . $file_path;
    }

    public function getDomainAssociationFilePath($include_site_url = false): string
    {
        $file_name = $this->isSandbox() ? 'domain-association-file-sandbox' : 'apple-developer-merchantid-domain-association';
        return (!empty($include_site_url) ? get_bloginfo('url') . '/' : '')  . '.well-known/' . $file_name;
    }
}
