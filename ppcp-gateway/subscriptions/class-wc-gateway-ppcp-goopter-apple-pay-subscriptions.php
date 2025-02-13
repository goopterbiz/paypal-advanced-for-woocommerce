<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Goopter_WC_Gateway_PPCP_Apple_Pay_Subscriptions extends Goopter_WC_Gateway_Apple_Pay {
    use Goopter_WC_Gateway_PPCP_Subscriptions_Base;
}
