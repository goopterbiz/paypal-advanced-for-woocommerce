<?php

if (!function_exists('goopter_get_session')) {

    function goopter_get_session($key) {
        try {
            if (!class_exists('WooCommerce') || !function_exists('WC')) {
                return false;
            }
            if (WC()->session) {
                $goopter_session = WC()->session->get($key);
                return $goopter_session;
            } else {
                return false;
            }
        } catch (Exception $ex) {

        }
    }

}

