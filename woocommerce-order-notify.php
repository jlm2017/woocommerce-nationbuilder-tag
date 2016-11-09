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
        // When status got completed
        add_action('woocommerce_order_status_completed', [$this, 'on_completed'], 10, 2);

        // When any data are updated
        add_action('woocommerce_process_shop_order_meta', [$this, 'on_order_update'], 10, 2);

        // When initialized
        add_action('admin_init', [$this, 'woocommerce_order_notify_settings_init']);

        // When menu load
        add_action('admin_menu', [$this, 'woocommerce_order_notify_add_admin_menu']);
    }

    public function woocommerce_order_notify_add_admin_menu()
    {
        add_options_page('Woocommerce Order Notify', 'Woocommerce Order Notify',
          'manage_options', 'Woocommerce Order Notify',
          [$this, 'woocommerce_order_notify_options_page']);
    }

    public function woocommerce_order_notify_settings_init()
    {
        register_setting('plugin_page', 'woocommerce_order_notify_settings');

        add_settings_section(
            'woocommerce_order_notify_plugin_page_section',
            __('NationBuilder\'s settings', 'Woocommerce Order Notify'),
            [$this, 'woocommerce_order_notify_settings_section_callback'],
            'plugin_page'
        );

        add_settings_field(
            'woocommerce_order_notify_api_key',
            __('API Key', 'Woocommerce Order Notify'),
            [$this, 'woocommerce_order_notify_api_key_render'],
            'plugin_page',
            'woocommerce_order_notify_plugin_page_section'
        );

        add_settings_field(
            'woocommerce_order_notify_nation_slug',
            __('Nation slug', 'Woocommerce Order Notify'),
            [$this, 'woocommerce_order_notify_nation_slug_render'],
            'plugin_page',
            'woocommerce_order_notify_plugin_page_section'
        );

        add_settings_field(
            'woocommerce_order_notify_tag_name',
            __('Tag name', 'Woocommerce Order Notify'),
            [$this, 'woocommerce_order_notify_tag_name_render'],
            'plugin_page',
            'woocommerce_order_notify_plugin_page_section'
        );
    }

    public function woocommerce_order_notify_api_key_render()
    {
        $options = get_option('woocommerce_order_notify_settings'); ?>
      <input type='text'
      name='woocommerce_order_notify_settings[woocommerce_order_notify_api_key]'
      value='<?php echo $options['woocommerce_order_notify_api_key']; ?>'>
      <?php

    }

    public function woocommerce_order_notify_nation_slug_render()
    {
        $options = get_option('woocommerce_order_notify_settings'); ?>
      <input type='text'
      name='woocommerce_order_notify_settings[woocommerce_order_notify_nation_slug]'
      value='<?php echo $options['woocommerce_order_notify_nation_slug']; ?>'>
      <?php

    }

    public function woocommerce_order_notify_tag_name_render()
    {
        $options = get_option('woocommerce_order_notify_settings'); ?>
      <input type='text'
      name='woocommerce_order_notify_settings[woocommerce_order_notify_tag_name]'
      value='<?php echo $options['woocommerce_order_notify_tag_name']; ?>'>
      <?php

    }

    public function woocommerce_order_notify_settings_section_callback()
    {
        echo __('please register your NationBuilder\'s settings',
            'Woocommerce Order Notify');
    }

    public function woocommerce_order_notify_options_page()
    {
        ?>
      <form action='options.php' method='post'>

        <h2>Woocommerce Order Notify</h2>

        <?php
            settings_fields('plugin_page');
        do_settings_sections('plugin_page');
        submit_button(); ?>

      </form>
      <?php

    }
    /**
     * When order status change to completed.
     *
     * @param WC_Order $order
     */
    public function on_completed($order_id)
    {
        // @Todo call api
        $options = get_option('woocommerce_order_notify_settings');
        $order = wc_get_order($order_id);
        $url = 'https://'.$options['woocommerce_order_notify_nation_slug'].
            '.nationbuilder.com/api/v1/people/match?email='.
            urlencode($order->billing_email).'&access_token='.
            $options['woocommerce_order_notify_api_key'];
        $args = ['headers' => ['Accept' => 'application/json'],
            'httpversion' => '1.1', 'user-agent' => '', ];
        $response = wp_remote_get($url, $args);
        $body = wp_remote_retrieve_body($response);
        $id = json_decode($response['body'])->person->id;
        if ($id) {
            $url2 = 'https://'.$options['woocommerce_order_notify_nation_slug'].
              '.nationbuilder.com/api/v1/people/'.$id.'/taggings?access_token='.
              $options['woocommerce_order_notify_api_key'];
            $response2 = wp_remote_request($url2, array(
            'method' => 'PUT',
            'httpversion' => '1.1',
            'headers' => array(),
            'user-agent' => '',
            'headers' => ['content-type' => 'application/json',
                'Accept' => 'application/json', ],
            'body' => '
              {
              "tagging": {
                "tag": "'.$options['woocommerce_order_notify_tag_name'].'"
                }
              }',
            )
          );
        }
    }

    /**
     * This method call on_order_status_change if status have changed.
     *
     * This feature is necessary because WooCommerce doesn't provide
     * a smart hook from admin panel.
     *
     * @param int     $order_id Order ID
     * @param WP_Post $post     Posted data but not all
     */
    public function on_order_update($order_id, $post)
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
