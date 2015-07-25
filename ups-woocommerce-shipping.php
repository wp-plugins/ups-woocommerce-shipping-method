<?php
/*
	Plugin Name: UPS WooCommerce Shipping Basic 
	Plugin URI: http://www.wooforce.com
	Description: Obtain Real time shipping rates via the UPS Shipping API. Upgrade to Premium version for Print shipping labels and Track Shipment features.
	Version: 1.0.0
	Author: WooForce
	Author URI: http://www.wooforce.com
*/

define("WF_UPS_ID", "wf_shipping_ups");

/**
 * Check if WooCommerce is active
 */
if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {
	/**
	 * WC_UPS class
	 */
	class UPS_WooCommerce_Shipping {

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'init' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'wf_plugin_action_links' ) );
			add_action( 'woocommerce_shipping_init', array( $this, 'wf_shipping_init') );
			add_filter( 'woocommerce_shipping_methods', array( $this, 'wf_ups_add_method') );
			add_action( 'admin_enqueue_scripts', array( $this, 'wf_ups_scripts') );
		}

		public function init() {
			
			// Localisation
			//load_plugin_textdomain( 'ups-woocommerce-shipping', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Plugin page links
		 */
		public function wf_plugin_action_links( $links ) {
			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=wf_shipping_ups' ) . '">' . __( 'Settings', 'ups-woocommerce-shipping' ) . '</a>',
				'<a href="http://www.wooforce.com/pages/contact/">' . __( 'Support', 'ups-woocommerce-shipping' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}
		
		/**
		 * wc_ups_init function.
		 *
		 * @access public
		 * @return void
		 */
		function wf_shipping_init() {
			include_once( 'includes/class-wf-shipping-ups.php' );
		}

		/**
		 * wc_ups_add_method function.
		 *
		 * @access public
		 * @param mixed $methods
		 * @return void
		 */
		function wf_ups_add_method( $methods ) {
			$methods[] = 'WF_Shipping_UPS';
			return $methods;
		}

		/**
		 * wc_ups_scripts function.
		 *
		 * @access public
		 * @return void
		 */
		function wf_ups_scripts() {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	}
	new UPS_WooCommerce_Shipping();
}
