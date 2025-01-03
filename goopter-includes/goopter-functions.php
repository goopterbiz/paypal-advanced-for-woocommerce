<?php
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
/**
 * Functions used by plugins
 */

if (!function_exists('gt_get_shop_order_screen_id')) {
    function gt_get_shop_order_screen_id()
    {
        return wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order') : 'shop_order';
    }
}

if (!function_exists('gt_is_active_screen')) {
    /**
     * Returns True if the current active screen matches to one of the array elements
     * @param string $screen
     * @return bool
     */
    function gt_is_active_screen(string $screen): bool
    {
        $current_screen = get_current_screen();
        $screen_id = $current_screen ? $current_screen->id : '';
        return $screen_id == $screen;
    }
}