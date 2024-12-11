<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_PPCP_Goopter_Apple_Pay_Subscriptions extends WC_Gateway_Apple_Pay_Goopter {
    use WC_Gateway_PPCP_Goopter_Subscriptions_Base;
}
