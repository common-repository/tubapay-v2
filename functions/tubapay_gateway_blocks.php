<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if(!class_exists('TubaPay2_Gateway_Blocks')) {
    final class TubaPay2_Gateway_Blocks extends AbstractPaymentMethodType
    {
        private $gateway;
        protected $name = 'tubapay2';

        public function initialize()
        {
            $this->settings = get_option('woocommerce_tubapay2_settings', []);
            $this->gateway = new WC_Gateway_TubaPay2();
        }

        public function is_active()
        {
            return $this->gateway->is_available();
        }

        public function get_payment_method_script_handles()
        {
            wp_register_script(
                'tubapay2-blocks-integration',
                plugins_url('assets/js/blocks.js', dirname(__FILE__)),
                [
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ],
                null,
                true
            );
            if (function_exists('wp_set_script_translations')) {
                wp_set_script_translations('tubapay2-blocks-integration');

            }
            return ['tubapay2-blocks-integration'];
        }

        public function get_payment_method_data()
        {
            return [
                'title' => $this->gateway->title,
                'description' => $this->gateway->description,
            ];
        }
    }
}