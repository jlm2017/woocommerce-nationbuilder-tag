<?php
/*
    Plugin Name: WooCommerce Order Notify
    Description: Notify custom app on event.
    Author: S.Z.
    License: GPL2
*/

/**
 * Class WC_Order_Notify_Plugin.
 *
 * @author    S.Z. <s****.z****@gmail.com>
 */
class WC_Order_Notify_Plugin
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // When status changed
        add_action('woocommerce_order_edit_status', [$this, 'on_order_status_change'], 10, 2);

        // When any data are updated
        add_action('woocommerce_process_shop_order_meta', [$this, 'on_order_update'], 10, 2);
    }

    /**
     * When order status change to completed
     *
     * @param WC_Order $order
     *
     * @return void
     */
    function on_completed($order)
    {
        // @Todo call api
    }

    /**
     * When order status is changeed
     *
     * @param integer $id     Order ID
     * @param string  $status New status
     *
     * @return void
     */
    function on_order_status_change($id, $status)
    {
        switch ($status) {
            case 'wc-completed':
                $order = wc_get_order($id);
                $this->on_completed($order);
                break;
        }
    }

    /**
     * This method call on_order_status_change if status have changed.
     *
     * This feature is necessary because WooCommerce doesn't provide
     * a smart hook from admin panel.
     *
     * @param integer $order_id Order ID
     * @param WP_Post $post     Posted data but not all
     *
     * @return void
     */
    function on_order_update($order_id, $post)
    {
        // The new value is inside this parameter and not in WP_Post
        if (!isset($_POST['order_status'])) {
            return;
        }

        // Don't need to continue if it's the same status
        if (0 === strcmp($post->post_status, $_POST['order_status'])) {
            return;
        }

        $this->on_order_status_change($order_id, $_POST['order_status']);
    }
}

new WC_Order_Notify_Plugin();
