<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Goopter_WC_Gateway_PPCP_Google_Pay_Subscriptions extends Goopter_WC_Gateway_Google_Pay {
    use Goopter_WC_Gateway_PPCP_Subscriptions_Base;
}
