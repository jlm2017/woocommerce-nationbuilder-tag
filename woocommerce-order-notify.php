<?php
/*
    Plugin Name: WooCommerce Order Notify
    Description: Notify custom app on event.
    Author: Sohel Zerdoumi
    License: GPL2
*/

/**
 * Class WC_Order_Notify_Plugin.
 *
 * @author    Sohel Zerdoumi <sohel.zerdoumi@gmail.com>
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
        register_setting('woocommerce_order_notify_settings_page', 'woocommerce_order_notify_settings');

        add_settings_section(
            'woocommerce_order_notify_plugin_page_section',
            __('NationBuilder\'s settings', 'Woocommerce Order Notify'),
            [$this, 'woocommerce_order_notify_settings_section_callback'],
            'woocommerce_order_notify_settings_page'
        );

        add_settings_field(
            'woocommerce_order_notify_api_key',
            __('API Key', 'Woocommerce Order Notify'),
            [$this, 'woocommerce_order_notify_api_key_render'],
            'woocommerce_order_notify_settings_page',
            'woocommerce_order_notify_plugin_page_section'
        );

        add_settings_field(
            'woocommerce_order_notify_nation_slug',
            __('Nation slug', 'Woocommerce Order Notify'),
            [$this, 'woocommerce_order_notify_nation_slug_render'],
            'woocommerce_order_notify_settings_page',
            'woocommerce_order_notify_plugin_page_section'
        );

        add_settings_field(
            'woocommerce_order_notify_tag_name',
            __('Tag name', 'Woocommerce Order Notify'),
            [$this, 'woocommerce_order_notify_tag_name_render'],
            'woocommerce_order_notify_settings_page',
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
          settings_fields('woocommerce_order_notify_settings_page');
          do_settings_sections('woocommerce_order_notify_settings_page');
          submit_button();
        ?>

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
        $options = get_option('woocommerce_order_notify_settings');

        $order = wc_get_order($order_id);

        $url = 'https://'.$options['woocommerce_order_notify_nation_slug'].
            '.nationbuilder.com/api/v1/people/match?email='.
            urlencode($order->billing_email).'&access_token='.
            $options['woocommerce_order_notify_api_key'];

        $response = wp_remote_get($url, [
            'headers' => ['Accept' => 'application/json'],
            'httpversion' => '1.1',
            'user-agent' => '',
        ]);
        if (is_wp_error($response)) {
          error_log($response->get_error_message());
          return ;
        }
        if (json_decode($response['body'])->code === 'no_matches') {
            $url = 'https://'.$options['woocommerce_order_notify_nation_slug'].
                '.nationbuilder.com/api/v1/people?access_token='.
                $options['woocommerce_order_notify_api_key'];

            $response = wp_remote_post( $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-type' => 'application/json'
                ],
                'httpversion' => '1.1',
                'user-agent' => '',
                'body' => '{"person":{"email":"'.$order->billing_email.'"}}',
            ]);
        }

        if (is_wp_error($response)) {
          error_log($response->get_error_message());
          return ;
        }
        $id = json_decode($response['body'])->person->id;

        if ($id) {
            $url = 'https://'.$options['woocommerce_order_notify_nation_slug'].
              '.nationbuilder.com/api/v1/people/'.$id.'/taggings?access_token='.
              $options['woocommerce_order_notify_api_key'];

            wp_remote_request($url, [
                'method' => 'PUT',
                'httpversion' => '1.1',
                'user-agent' => '',
                'headers' => [
                    'content-type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => '{"tagging":{"tag": "'.$options['woocommerce_order_notify_tag_name'].'"}}',
            ]);
        }
    }
}

new WC_Order_Notify_Plugin();
