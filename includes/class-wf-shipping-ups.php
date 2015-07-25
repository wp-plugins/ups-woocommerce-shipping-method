<?php
/**
 * WF_Shipping_UPS class.
 *
 * @extends WC_Shipping_Method
 */
class WF_Shipping_UPS extends WC_Shipping_Method {

	private $endpoint = 'https://wwwcie.ups.com/ups.app/xml/Rate';

	private $pickup_code = array(
		'01' => "Daily Pickup",
		'03' => "Customer Counter",
		'06' => "One Time Pickup",
		'07' => "On Call Air",
		'19' => "Letter Center",
		'20' => "Air Service Center",
	);

	private $services = array(
		// Domestic
		"12" => "3 Day Select",
		"03" => "Ground",
		"02" => "2nd Day Air",
		"59" => "2nd Day Air AM",
		"01" => "Next Day Air",
		"13" => "Next Day Air Saver",
		"14" => "Next Day Air Early AM",

		// International
		"11" => "Standard",
		"07" => "Worldwide Express",
		"54" => "Worldwide Express Plus",
		"08" => "Worldwide Expedited",
		"65" => "Saver",

	);

	private $eu_array = array('BE','BG','CZ','DK','DE','EE','IE','GR','ES','FR','HR','IT','CY','LV','LT','LU','HU','MT','NL','AT','PT','RO','SI','SK','FI','GB');
	
	// Shipments Originating in the European Union
	private $euservices = array(
		"07" => "UPS Express",
		"08" => "UPS ExpeditedSM",
		"11" => "UPS Standard",
		"54" => "UPS Express PlusSM",
		"65" => "UPS Saver",
	);

	private $polandservices = array(
		"07" => "UPS Express",
		"08" => "UPS ExpeditedSM",
		"11" => "UPS Standard",
		"54" => "UPS Express PlusSM",
		"65" => "UPS Saver",
		"82" => "UPS Today Standard",
		"83" => "UPS Today Dedicated Courier",
		"84" => "UPS Today Intercity",
		"85" => "UPS Today Express",
		"86" => "UPS Today Express Saver",
	);

	// Packaging not offered at this time: 00 = UNKNOWN, 30 = Pallet, 04 = Pak
	// Code 21 = Express box is valid code, but doesn't have dimensions
	// References:
	// http://www.ups.com/content/us/en/resources/ship/packaging/supplies/envelopes.html
	// http://www.ups.com/content/us/en/resources/ship/packaging/supplies/paks.html
	// http://www.ups.com/content/us/en/resources/ship/packaging/supplies/boxes.html
	private $packaging = array(
		"01" => array(
					"name" 	 => "UPS Letter",
					"length" => "12.5",
					"width"  => "9.5",
					"height" => "0.25",
					"weight" => "0.5"
				),
		"03" => array(
					"name" 	 => "Tube",
					"length" => "38",
					"width"  => "6",
					"height" => "6",
					"weight" => "100"
				),
		"24" => array(
					"name" 	 => "25KG Box",
					"length" => "19.375",
					"width"  => "17.375",
					"height" => "14",
					"weight" => "25"
				),
		"25" => array(
					"name" 	 => "10KG Box",
					"length" => "16.5",
					"width"  => "13.25",
					"height" => "10.75",
					"weight" => "10"
				),
		"2a" => array(
					"name" 	 => "Small Express Box",
					"length" => "13",
					"width"  => "11",
					"height" => "2",
					"weight" => "100"
				),
		"2b" => array(
					"name" 	 => "Medium Express Box",
					"length" => "15",
					"width"  => "11",
					"height" => "3",
					"weight" => "100"
				),
		"2c" => array(
					"name" 	 => "Large Express Box",
					"length" => "18",
					"width"  => "13",
					"height" => "3",
					"weight" => "30"
				)
	);

	private $packaging_select = array(
		"01" => "UPS Letter",
		"03" => "Tube",
		"24" => "25KG Box",
		"25" => "10KG Box",
		"2a" => "Small Express Box",
		"2b" => "Medium Express Box",
		"2c" => "Large Express Box",
	);

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->id                 = WF_UPS_ID;
		$this->method_title       = __( 'UPS Basic', 'ups-woocommerce-shipping' );
		$this->method_description = __( 'The <strong>UPS Basic Version</strong> extension obtains rates dynamically from the UPS API during cart/checkout.Upgrade to Premium version for Print shipping labels and Track Shipment features.', 'ups-woocommerce-shipping' );
		
		// WF: Load UPS Settings.
		$ups_settings 		= get_option( 'woocommerce_'.WF_UPS_ID.'_settings', null ); 
		$api_mode      		= isset( $ups_settings['api_mode'] ) ? $ups_settings['api_mode'] : 'Test';
		if( "Live" == $api_mode ) {
			$this->endpoint = 'https://www.ups.com/ups.app/xml/Rate';
		}
		else {
			$this->endpoint = 'https://wwwcie.ups.com/ups.app/xml/Rate';
		}
		
		$this->init();
	}

	/**
	 * Output a message or error
	 * @param  string $message
	 * @param  string $type
	 */
    public function debug( $message, $type = 'notice' ) {
    	if ( $this->debug && !is_admin() ) { //WF: do not call wc_add_notice from admin.
    		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
    			wc_add_notice( $message, $type );
    		} else {
    			global $woocommerce;
    			$woocommerce->add_message( $message );
    		}
		}
    }

    /**
     * init function.
     *
     * @access public
     * @return void
     */
    private function init() {
		global $woocommerce;
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->enabled				= isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : $this->enabled;
		$this->title				= isset( $this->settings['title'] ) ? $this->settings['title'] : $this->method_title;
		$this->availability    		= isset( $this->settings['availability'] ) ? $this->settings['availability'] : 'all';
		$this->countries       		= isset( $this->settings['countries'] ) ? $this->settings['countries'] : array();

		// API Settings
		$this->user_id         		= isset( $this->settings['user_id'] ) ? $this->settings['user_id'] : '';
		
		// WF: Print Label - Start
		$this->disble_ups_print_label	= isset( $this->settings['disble_ups_print_label'] ) ? $this->settings['disble_ups_print_label'] : '';
		$this->print_label_size      	= isset( $this->settings['print_label_size'] ) ? $this->settings['print_label_size'] : 'Default';
		$this->manual_weight_dimensions	= isset( $this->settings['manual_weight_dimensions'] ) ? $this->settings['manual_weight_dimensions'] : 'no';
		$this->disble_shipment_tracking	= isset( $this->settings['disble_shipment_tracking'] ) ? $this->settings['disble_shipment_tracking'] : 'TrueForCustomer';
		$this->api_mode      			= isset( $this->settings['api_mode'] ) ? $this->settings['api_mode'] : 'Test';
		$this->ups_user_name        	= isset( $this->settings['ups_user_name'] ) ? $this->settings['ups_user_name'] : '';
		$this->ups_display_name        	= isset( $this->settings['ups_display_name'] ) ? $this->settings['ups_display_name'] : '';
		$this->phone_number 			= isset( $this->settings['phone_number'] ) ? $this->settings['phone_number'] : '';
		// WF: Print Label - End
		
		$this->user_id         		= isset( $this->settings['user_id'] ) ? $this->settings['user_id'] : '';
		$this->password        		= isset( $this->settings['password'] ) ? $this->settings['password'] : '';
		$this->access_key      		= isset( $this->settings['access_key'] ) ? $this->settings['access_key'] : '';
		$this->shipper_number  		= isset( $this->settings['shipper_number'] ) ? $this->settings['shipper_number'] : '';
		$this->negotiated      		= isset( $this->settings['negotiated'] ) && $this->settings['negotiated'] == 'yes' ? true : false;
		$this->origin_addressline 	= isset( $this->settings['origin_addressline'] ) ? $this->settings['origin_addressline'] : '';
		$this->origin_city 			= isset( $this->settings['origin_city'] ) ? $this->settings['origin_city'] : '';
		$this->origin_postcode 		= isset( $this->settings['origin_postcode'] ) ? $this->settings['origin_postcode'] : '';
		$this->origin_country_state = isset( $this->settings['origin_country_state'] ) ? $this->settings['origin_country_state'] : '';
		$this->debug      			= isset( $this->settings['debug'] ) && $this->settings['debug'] == 'yes' ? true : false;

		// Pickup and Destination
		$this->pickup			= isset( $this->settings['pickup'] ) ? $this->settings['pickup'] : '01';
		$this->residential		= isset( $this->settings['residential'] ) && $this->settings['residential'] == 'yes' ? true : false;

		// Services and Packaging
		$this->offer_rates     	= isset( $this->settings['offer_rates'] ) ? $this->settings['offer_rates'] : 'all';
		$this->fallback		   	= ! empty( $this->settings['fallback'] ) ? $this->settings['fallback'] : '';
		$this->packing_method  	= isset( $this->settings['packing_method'] ) ? $this->settings['packing_method'] : 'per_item';
		$this->ups_packaging	= isset( $this->settings['ups_packaging'] ) ? $this->settings['ups_packaging'] : array();
		$this->custom_services  = isset( $this->settings['services'] ) ? $this->settings['services'] : array();
		$this->boxes           	= isset( $this->settings['boxes'] ) ? $this->settings['boxes'] : array();
		$this->insuredvalue 	= isset( $this->settings['insuredvalue'] ) && $this->settings['insuredvalue'] == 'yes' ? true : false;

		// Units
		$this->units			= isset( $this->settings['units'] ) ? $this->settings['units'] : 'imperial';

		if ( $this->units == 'metric' ) {
			$this->weight_unit = 'KGS';
			$this->dim_unit    = 'CM';
		} else {
			$this->weight_unit = 'LBS';
			$this->dim_unit    = 'IN';
		}

		if (strstr($this->origin_country_state, ':')) :
			// WF: Following strict php standards.
			$origin_country_state_array = explode(':',$this->origin_country_state);
    		$this->origin_country = current($origin_country_state_array);
			$origin_country_state_array = explode(':',$this->origin_country_state);
    		$this->origin_state   = end($origin_country_state_array);
    	else :
    		$this->origin_country = $this->origin_country_state;
    		$this->origin_state   = '';
    	endif;
		
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'clear_transients' ) );

	}

	/**
	 * environment_check function.
	 *
	 * @access public
	 * @return void
	 */
	private function environment_check() {
		global $woocommerce;

		$error_message = '';

		// WF: Print Label - Start
		// Check for UPS User Name
		if ( ! $this->ups_user_name && $this->enabled == 'yes' ) {
			$error_message .= '<p>' . __( 'UPS is enabled, but Your Name has not been set.', 'ups-woocommerce-shipping' ) . '</p>';
		}
		// WF: Print Label - End
		
		// Check for UPS User ID
		if ( ! $this->user_id && $this->enabled == 'yes' ) {
			$error_message .= '<p>' . __( 'UPS is enabled, but the UPS User ID has not been set.', 'ups-woocommerce-shipping' ) . '</p>';
		}

		// Check for UPS Password
		if ( ! $this->password && $this->enabled == 'yes' ) {
			$error_message .= '<p>' . __( 'UPS is enabled, but the UPS Password has not been set.', 'ups-woocommerce-shipping' ) . '</p>';
		}

		// Check for UPS Access Key
		if ( ! $this->access_key && $this->enabled == 'yes' ) {
			$error_message .= '<p>' . __( 'UPS is enabled, but the UPS Access Key has not been set.', 'ups-woocommerce-shipping' ) . '</p>';
		}

		// Check for UPS Shipper Number
		if ( ! $this->shipper_number && $this->enabled == 'yes' ) {
			$error_message .= '<p>' . __( 'UPS is enabled, but the UPS Shipper Number has not been set.', 'ups-woocommerce-shipping' ) . '</p>';
		}

		// Check for Origin Postcode
		if ( ! $this->origin_postcode && $this->enabled == 'yes' ) {
			$error_message .= '<p>' . __( 'UPS is enabled, but the origin postcode has not been set.', 'ups-woocommerce-shipping' ) . '</p>';
		}

		// Check for Origin country
		if ( ! $this->origin_country_state && $this->enabled == 'yes' ) {
			$error_message .= '<p>' . __( 'UPS is enabled, but the origin country/state has not been set.', 'ups-woocommerce-shipping' ) . '</p>';
		}

		// Check for at least one service enabled
		$ctr=0;
		if ( isset($this->custom_services ) && is_array( $this->custom_services ) ){
			foreach ( $this->custom_services as $key => $values ){
				if ( $values['enabled'] == 1)
					$ctr++;
			}
		}
		if ( ( $ctr == 0 ) && $this->enabled == 'yes' ) {
			$error_message .= '<p>' . __( 'UPS is enabled, but there are no services enabled.', 'ups-woocommerce-shipping' ) . '</p>';
		}


		if ( ! $error_message == '' ) {
			echo '<div class="error">';
			echo $error_message;
			echo '</div>';
		}
	}

	/**
	 * admin_options function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options() {
		// Check users environment supports this method
		$this->environment_check();?>
		<div class="wf-banner updated below-h2">
			<img class="scale-with-grid" src="http://www.wooforce.com/wp-content/uploads/2015/07/WooForce-Logo-Admin-Banner-Basic.png" alt="Wordpress / WooCommerce USPS, Canada Post Shipping | WooForce">
  			<p class="main"><strong>UPS Premium version streamlines your complete shipping process and saves time</strong></p>
			<p>&nbsp;-&nbsp;Print shipping label with postage.<br>
			&nbsp;-&nbsp;Auto Shipment Tracking: It happens automatically while generating the label.<br>
			&nbsp;-&nbsp;Box packing.<br>
			&nbsp;-&nbsp;Enable/disable, edit the names of, and add handling costs to shipping services.<br>
			&nbsp;-&nbsp;Excellent Support for setting it up!</p>
			<p><a href="http://www.wooforce.com/product/ups-woocommerce-shipping-with-print-label-plugin/" target="_blank" class="button button-primary">Upgrade to Premium Version</a> <a href="http://ups.wooforce.com/wp-admin/admin.php?page=wc-settings&tab=shipping&section=wf_shipping_ups" target="_blank" class="button">Live Demo</a></p>
		</div>
		<style>
		.wf-banner img {
			float: right;
			margin-left: 1em;
			padding: 15px 0
		}
		</style>
		<?php 
		// Show settings
		parent::admin_options();
	}

	/**
	 *
	 * generate_single_select_country_html function
	 *
	 * @access public
	 * @return void
	 */
	function generate_single_select_country_html() {
		global $woocommerce;

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="origin_country"><?php _e( 'Origin Country', 'ups-woocommerce-shipping' ); ?></label>
			</th>
            <td class="forminp"><select name="woocommerce_ups_origin_country_state" id="woocommerce_ups_origin_country_state" style="width: 250px;" data-placeholder="<?php _e('Choose a country&hellip;', 'woocommerce'); ?>" title="Country" class="chosen_select">
	        	<?php echo $woocommerce->countries->country_dropdown_options( $this->origin_country, $this->origin_state ? $this->origin_state : '*' ); ?>
	        </select> <span class="description"><?php _e( 'Country for the <strong>sender</strong>.', 'ups-woocommerce-shipping' ) ?></span>
       		</td>
       	</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * generate_services_html function.
	 *
	 * @access public
	 * @return void
	 */
	function generate_services_html() {
		return '';
	}


	/**
	 * validate_single_select_country_field function.
	 *
	 * @access public
	 * @param mixed $key
	 * @return void
	 */
	public function validate_single_select_country_field( $key ) {

		if ( isset( $_POST['woocommerce_ups_origin_country_state'] ) )
			return $_POST['woocommerce_ups_origin_country_state'];
		return '';
	}
	/**
	 
	/**
	 * validate_services_field function.
	 *
	 * @access public
	 * @param mixed $key
	 * @return void
	 */
	public function validate_services_field( $key ) {
		$services         = array();
		if ( $this->origin_country == 'PL' ) {
			$use_services = $this->polandservices;
		} elseif ( in_array( $this->origin_country, $this->eu_array ) ) {
			$use_services = $this->euservices;
		} else {
			$use_services = $this->services;
		}

		foreach ( $use_services as $code => $name ) {

			$services[ $code ] = array(
				'name'               => $name,
				'order'              => '',
				'enabled'            => true,
				'adjustment'         => '',
				'adjustment_percent' => ''
			);

		}

		return $services;
	}

	/**
	 * clear_transients function.
	 *
	 * @access public
	 * @return void
	 */
	public function clear_transients() {
		global $wpdb;

		$wpdb->query( "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_ups_quote_%') OR `option_name` LIKE ('_transient_timeout_ups_quote_%')" );
	}

    /**
     * init_form_fields function.
     *
     * @access public
     * @return void
     */
    public function init_form_fields() {
	    global $woocommerce;

    	$this->form_fields  = array(
			'enabled'          => array(
				'title'           => __( 'Enable/Disable', 'ups-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'label'           => __( 'Enable this shipping method', 'ups-woocommerce-shipping' ),
				'default'         => 'no'
			),
			'title'            => array(
				'title'           => __( 'UPS Method Title', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'This controls the title which the user sees during checkout.', 'ups-woocommerce-shipping' ),
				'default'         => __( 'UPS Basic Version', 'ups-woocommerce-shipping' )
			),
		    'availability'  => array(
				'title'           => __( 'Method Availability', 'ups-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'all',
				'class'           => 'availability',
				'options'         => array(
					'all'            => __( 'All Countries', 'ups-woocommerce-shipping' ),
					'specific'       => __( 'Specific Countries', 'ups-woocommerce-shipping' ),
				),
			),
			'countries'        => array(
				'title'           => __( 'Specific Countries', 'ups-woocommerce-shipping' ),
				'type'            => 'multiselect',
				'class'           => 'chosen_select',
				'css'             => 'width: 450px;',
				'default'         => '',
				'options'         => $woocommerce->countries->get_allowed_countries(),
			),
		    'debug'  => array(
				'title'           => __( 'Debug', 'ups-woocommerce-shipping' ),
				'label'           => __( 'Enable debug mode', 'ups-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'default'         => 'no',
				'description'     => __( 'Enable debug mode to show debugging information on your cart/checkout.', 'ups-woocommerce-shipping' )
			),
		    'api'           => array(
				'title'           => __( 'API Settings', 'ups-woocommerce-shipping' ),
				'type'            => 'title',
				'description'     => __( 'You need to obtain UPS account credentials by registering on via their website.', 'ups-woocommerce-shipping' ),
		    ),
			// WF: Print Label - Start.
			'disble_ups_print_label'  => array(
				'title'           => __( 'Enable/Disable Shipping Label', 'ups-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'label'           => __( 'Disable this functionality', 'ups-woocommerce-shipping' ),
				'default'         => 'no',
				'description'     => __( 'Upgrade to Premium version for Print Label feature', 'ups-woocommerce-shipping' ),
			),
			'print_label_size'  => array(
				'title'           => __( 'Print Label Type', 'ups-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'yes',
				'options'         => array(
					'Default'         => __( 'GIF', 'ups-woocommerce-shipping' ),
					'Compact'         => __( 'PNG', 'ups-woocommerce-shipping' ),
				),
				'description'     => __( 'Selecting PNG will enable ~4x6 dimension label. Note that an external api labelary is used.', 'ups-woocommerce-shipping' )
			),
			'manual_weight_dimensions' => array(
				'title'           => __( 'Manual Label Dimensions', 'ups-woocommerce-shipping' ),
				'label'           => __( 'Manually enter weight and dimensions while label printing.', 'ups-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'default'         => 'no',
				'description'     => __( 'Enabling it will give the provision to enter package dimensions and weight manually while printing label. Keeping it unchecked will enable automatic capturing of weights and dimensions for each of the order items. In this case, make sure dimensions and weight is set for each of your products.', 'ups-woocommerce-shipping' )
			),
			'disble_shipment_tracking'    => array(
				'title'           => __( 'Shipment Tracking', 'ups-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'yes',
				'options'         => array(
					'TrueForCustomer'  => __( 'Disable for Customer', 'ups-woocommerce-shipping' ),
					'False'         => __( 'Enable', 'ups-woocommerce-shipping' ),
					'True'         => __( 'Disable', 'ups-woocommerce-shipping' ),
				),
				'description'     => __( 'Selecting Disable for customer will hide shipment tracking info from customer side order details page.', 'ups-woocommerce-shipping' )
			),
			'api_mode' 			=> array(
				'title'         => __( 'API Mode', 'ups-woocommerce-shipping' ),
				'type'          => 'select',
				'default'       => 'yes',
				'options'       => array(
					'Live'      => __( 'Live', 'ups-woocommerce-shipping' ),
					'Test'      => __( 'Test', 'ups-woocommerce-shipping' ),
				),
				'description'   => __( 'Set as Test to switch to UPS api test servers. Transaction will be treated as sample transactions by UPS.', 'ups-woocommerce-shipping' )
			),
			'ups_user_name'           => array(
				'title'           => __( 'Your Name', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Enter your name', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
			'ups_display_name'    => array(
				'title'           => __( 'Attention Name', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Your business/attention name.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
			// WF: Print Label - End.
		    'user_id'           => array(
				'title'           => __( 'UPS User ID', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Obtained from UPS after getting an account.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
		    'password'            => array(
				'title'           => __( 'UPS Password', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Obtained from UPS after getting an account.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
		    'access_key'          => array(
				'title'           => __( 'UPS Access Key', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Obtained from UPS after getting an account.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
		    'shipper_number'      => array(
				'title'           => __( 'UPS Account Number', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Obtained from UPS after getting an account.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
		    'origin_addressline'  => array(
				'title'           => __( 'Origin Address', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Address for the <strong>sender</strong>.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
		    'origin_city'      	  => array(
				'title'           => __( 'Origin City', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'City for the <strong>sender</strong>.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
		    'origin_country_state'      => array(
				'type'            => 'single_select_country',
			),
		    'origin_postcode'     => array(
				'title'           => __( 'Origin Postcode', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Zip/postcode for the <strong>sender</strong>.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
			'phone_number'     => array(
				'title'           => __( 'Your Phone Number', 'ups-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Your contact phone number.', 'ups-woocommerce-shipping' ),
				'default'         => '',
		    ),
			'units'      => array(
				'title'           => __( 'Weight/Dimension Units', 'ups-woocommerce-shipping' ),
				'type'            => 'select',
				'description'     => __( 'Switch this to metric units, if you see "This measurement system is not valid for the selected country" errors.', 'ups-woocommerce-shipping' ),
				'default'         => 'imperial',
				'options'         => array(
				    'imperial'    => __( 'LB / IN', 'ups-woocommerce-shipping' ),
				    'metric'      => __( 'KG / CM', 'ups-woocommerce-shipping' ),
				),
		    ),
		    'negotiated'  => array(
				'title'           => __( 'Negotiated Rates', 'ups-woocommerce-shipping' ),
				'label'           => __( 'Enable negotiated rates', 'ups-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'default'         => 'no',
				'description'     => __( 'Enable this if this shipping account has negotiated rates available.', 'ups-woocommerce-shipping' )
			),
		    'insuredvalue'  => array(
				'title'           => __( 'Insured Value', 'ups-woocommerce-shipping' ),
				'label'           => __( 'Request Insurance to be included in UPS rates', 'ups-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'default'         => 'no',
				'description'     => __( 'Enabling this will include insurance in UPS rates', 'ups-woocommerce-shipping' )
			),
		    'pickup_destination'  => array(
				'title'           => __( 'Pickup and Destination', 'ups-woocommerce-shipping' ),
				'type'            => 'title',
				'description'     => '',
		    ),
		    'pickup'  => array(
				'title'           => __( 'Pickup', 'ups-woocommerce-shipping' ),
				'type'            => 'select',
				'css'			  => 'width: 250px;',
				'class'			  => 'chosen_select',
				'default'         => '01',
				'options'         => $this->pickup_code,
			),
		    'residential'  => array(
				'title'           => __( 'Residential', 'ups-woocommerce-shipping' ),
				'label'           => __( 'Enable residential address flag', 'ups-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'default'         => 'no',
				'description'     => __( 'This will indicate to UPS that the receiver is a residential address.', 'ups-woocommerce-shipping' )
			),
		    'services_packaging'  => array(
				'title'           => __( 'Services and Packaging', 'ups-woocommerce-shipping' ),
				'type'            => 'title',
				'description'     => '',
		    ),
			'services'  => array(
				'type'            => 'services'
			),
			'offer_rates'   => array(
				'title'           => __( 'Offer Rates', 'ups-woocommerce-shipping' ),
				'type'            => 'select',
				'description'     => '',
				'default'         => 'all',
				'options'         => array(
				    'all'         => __( 'Offer the customer all returned rates', 'ups-woocommerce-shipping' ),
				    'cheapest'    => __( 'Offer the customer the cheapest rate only', 'ups-woocommerce-shipping' ),
				),
		    ),
		    'fallback' => array(
				'title'       => __( 'Fallback', 'ups-woocommerce-shipping' ),
				'type'        => 'text',
				'description' => __( 'If UPS returns no matching rates, offer this amount for shipping so that the user can still checkout. Leave blank to disable.', 'ups-woocommerce-shipping' ),
				'default'     => ''
			),
			'packing_method'  => array(
				'title'           => __( 'Parcel Packing', 'ups-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => '',
				'class'           => 'packing_method',
				'options'         => array(
					'per_item'       => __( 'Default: Pack items individually', 'ups-woocommerce-shipping' )
				),
				'description' => __( 'Upgrade to Premium version for Box packing feature.', 'ups-woocommerce-shipping' ),
				
			),
			'ups_packaging'  => array(
				'title'           => __( 'UPS Packaging', 'ups-woocommerce-shipping' ),
				'type'            => 'multiselect',
				'description'	  => __( 'UPS standard packaging options', 'ups-woocommerce-shipping' ),
				'default'         => array(),
				'css'			  => 'width: 450px;',
				'class'           => 'ups_packaging chosen_select',
				'options'         => $this->packaging_select
			)			
		);
    }

    /**
     * calculate_shipping function.
     *
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping( $package ) {
    	global $woocommerce;

    	$rates            = array();
    	$ups_responses	  = array();
    	libxml_use_internal_errors( true );

		// Only return rates if the package has a destination including country, postcode
		if ( ( '' ==$package['destination']['country'] ) || ( ''==$package['destination']['postcode'] ) ) {
			$this->debug( __('UPS: Country, or Zip not yet supplied. Rates not requested.', 'ups-woocommerce-shipping') );
			return; 
		}

    	$package_requests = $this->get_package_requests( $package );

    	if ( $package_requests ) {

			$rate_requests = $this->get_rate_requests( $package_requests, $package );

			if ( ! $rate_requests ) {
				$this->debug( __('UPS: No Services are enabled in admin panel.', 'ups-woocommerce-shipping') );
			}

			// get live or cached result for each rate
			foreach ( $rate_requests as $code => $request ) {

				$send_request           = str_replace( array( "\n", "\r" ), '', $request );
				$transient              = 'ups_quote_' . md5( $request );
				$cached_response        = get_transient( $transient );
				$ups_responses[ $code ] = false;

				if ( $cached_response === false ) {
					$response = wp_remote_post( $this->endpoint,
			    		array(
							'timeout'   => 70,
							'sslverify' => 0,
							'body'      => $send_request
					    )
					);

					if ( ! empty( $response['body'] ) ) {
						$ups_responses[ $code ] = $response['body'];
						set_transient( $transient, $response['body'], YEAR_IN_SECONDS );
					}

				} else {
					$ups_responses[ $code ] = $cached_response;
					$this->debug( __( 'UPS: Using cached response.', 'ups-woocommerce-shipping' ) );
				}

				$this->debug( 'UPS REQUEST: <pre>' . print_r( htmlspecialchars( $request ), true ) . '</pre>' );
				$this->debug( 'UPS RESPONSE: <pre>' . print_r( htmlspecialchars( $ups_responses[ $code ] ), true ) . '</pre>' );

			} // foreach ( $rate_requests )

			// parse the results
			foreach ( $ups_responses as $code => $response ) {

				$xml = simplexml_load_string( preg_replace('/<\?xml.*\?>/','', $response ) );

				if ( $this->debug ) {
					if ( ! $xml ) {
						$this->debug( __( 'Failed loading XML', 'ups-woocommerce-shipping' ), 'error' );
					}
				}

				if ( $xml->Response->ResponseStatusCode == 1 ) {

					$service_name = $this->services[ $code ];

					if ( $this->negotiated && isset( $xml->RatedShipment->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue ) )
						$rate_cost = (float) $xml->RatedShipment->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue;
					else
						$rate_cost = (float) $xml->RatedShipment->TotalCharges->MonetaryValue;

					$rate_id     = $this->id . ':' . $code;
					$rate_name   = $service_name . ' (' . $this->title . ')';

					// Name adjustment
					if ( ! empty( $this->custom_services[ $code ]['name'] ) )
						$rate_name = $this->custom_services[ $code ]['name'];

					// Cost adjustment %
					if ( ! empty( $this->custom_services[ $code ]['adjustment_percent'] ) )
						$rate_cost = $rate_cost + ( $rate_cost * ( floatval( $this->custom_services[ $code ]['adjustment_percent'] ) / 100 ) );
					// Cost adjustment
					if ( ! empty( $this->custom_services[ $code ]['adjustment'] ) )
						$rate_cost = $rate_cost + floatval( $this->custom_services[ $code ]['adjustment'] );

					// Sort
					if ( isset( $this->custom_services[ $code ]['order'] ) ) {
						$sort = $this->custom_services[ $code ]['order'];
					} else {
						$sort = 999;
					}

					$rates[ $rate_id ] = array(
						'id' 	=> $rate_id,
						'label' => $rate_name,
						'cost' 	=> $rate_cost,
						'sort'  => $sort
					);

				} else {
					// Either there was an error on this rate, or the rate is not valid (i.e. it is a domestic rate, but shipping international)
					$this->debug( sprintf( __( '[UPS] No rate returned for service code %s, %s (UPS code: %s)', 'ups-woocommerce-shipping' ),
											$code,
											$xml->Response->Error->ErrorDescription,
											$xml->Response->Error->ErrorCode ), 'error' );
				}

			} // foreach ( $ups_responses )

		} // foreach ( $package_requests )

		// Add rates
		if ( $rates ) {

			if ( $this->offer_rates == 'all' ) {

				uasort( $rates, array( $this, 'sort_rates' ) );
				foreach ( $rates as $key => $rate ) {
					$this->add_rate( $rate );
				}

			} else {

				$cheapest_rate = '';

				foreach ( $rates as $key => $rate ) {
					if ( ! $cheapest_rate || $cheapest_rate['cost'] > $rate['cost'] )
						$cheapest_rate = $rate;
				}

				$cheapest_rate['label'] = $this->title;

				$this->add_rate( $cheapest_rate );

			}
		// Fallback
		} elseif ( $this->fallback ) {
			$this->add_rate( array(
				'id' 	=> $this->id . '_fallback',
				'label' => $this->title,
				'cost' 	=> $this->fallback,
				'sort'  => 0
			) );
			$this->debug( __('UPS: Using Fallback setting.', 'ups-woocommerce-shipping') );
		}
    }

    /**
     * sort_rates function.
     *
     * @access public
     * @param mixed $a
     * @param mixed $b
     * @return void
     */
    public function sort_rates( $a, $b ) {
		if ( $a['sort'] == $b['sort'] ) return 0;
		return ( $a['sort'] < $b['sort'] ) ? -1 : 1;
    }

    /**
     * get_package_requests
	 *
	 *
     *
     * @access private
     * @return void
     */
    private function get_package_requests( $package ) {

	    // Choose selected packing
    	switch ( $this->packing_method ) {
	    	case 'per_item' :
	    	default :
	    		$requests = $this->per_item_shipping( $package );
	    	break;
    	}

    	return $requests;
    }

	/**
	 * get_rate_requests
	 *
	 * Get rate requests for all
	 * @access private
	 * @return array of strings - XML
	 *
	 */
	private function get_rate_requests( $package_requests, $package ) {
		global $woocommerce;

		$customer = $woocommerce->customer;

		$rate_requests = array();

		foreach ( $this->custom_services as $code => $params ) {
			if ( 1 == $params['enabled'] ) {

			// Security Header
			$request  = "<?xml version=\"1.0\" ?>" . "\n";
			$request .= "<AccessRequest xml:lang='en-US'>" . "\n";
			$request .= "	<AccessLicenseNumber>" . $this->access_key . "</AccessLicenseNumber>" . "\n";
			$request .= "	<UserId>" . $this->user_id . "</UserId>" . "\n";
			// Ampersand will break XML doc, so replace with encoded version.
			$valid_pass = str_replace( '&', '&amp;', $this->password );
			$request .= "	<Password>" . $valid_pass . "</Password>" . "\n";
			$request .= "</AccessRequest>" . "\n";
	    		$request .= "<?xml version=\"1.0\" ?>" . "\n";
	    		$request .= "<RatingServiceSelectionRequest>" . "\n";
	    		$request .= "	<Request>" . "\n";
	    		$request .= "	<TransactionReference>" . "\n";
	    		$request .= "		<CustomerContext>Rating and Service</CustomerContext>" . "\n";
	    		$request .= "		<XpciVersion>1.0</XpciVersion>" . "\n";
	    		$request .= "	</TransactionReference>" . "\n";
	    		$request .= "	<RequestAction>Rate</RequestAction>" . "\n";
	    		$request .= "	<RequestOption>Rate</RequestOption>" . "\n";
	    		$request .= "	</Request>" . "\n";
	    		$request .= "	<PickupType>" . "\n";
	    		$request .= "		<Code>" . $this->pickup . "</Code>" . "\n";
	    		$request .= "		<Description>" . $this->pickup_code[$this->pickup] . "</Description>" . "\n";
	    		$request .= "	</PickupType>" . "\n";
				// Shipment information
	    		$request .= "	<Shipment>" . "\n";
	    		$request .= "		<Description>WooCommerce Rate Request</Description>" . "\n";
	    		$request .= "		<Shipper>" . "\n";
	    		$request .= "			<ShipperNumber>" . $this->shipper_number . "</ShipperNumber>" . "\n";
	    		$request .= "			<Address>" . "\n";
	    		$request .= "				<AddressLine>" . $this->origin_addressline . "</AddressLine>" . "\n";
	    		$request .= "				<City>" . $this->origin_city . "</City>" . "\n";
	    		$request .= "				<PostalCode>" . $this->origin_postcode . "</PostalCode>" . "\n";
	    		$request .= "				<CountryCode>" . $this->origin_country . "</CountryCode>" . "\n";
	    		$request .= "			</Address>" . "\n";
	    		$request .= "		</Shipper>" . "\n";
	    		$request .= "		<ShipTo>" . "\n";
	    		$request .= "			<Address>" . "\n";
	    		$request .= "				<StateProvinceCode>" . $package['destination']['state'] . "</StateProvinceCode>" . "\n";
	    		$request .= "				<PostalCode>" . $package['destination']['postcode'] . "</PostalCode>" . "\n";
			if ( ( "PR" == $package['destination']['state'] ) && ( "US" == $package['destination']['country'] ) ) {		
	    			$request .= "				<CountryCode>PR</CountryCode>" . "\n";
			} else {
	    			$request .= "				<CountryCode>" . $package['destination']['country'] . "</CountryCode>" . "\n";
			}
	    		if ( $this->residential ) {
	    		$request .= "				<ResidentialAddressIndicator></ResidentialAddressIndicator>" . "\n";
	    		}
	    		$request .= "			</Address>" . "\n";
	    		$request .= "		</ShipTo>" . "\n";
	    		$request .= "		<ShipFrom>" . "\n";
	    		$request .= "			<Address>" . "\n";
	    		$request .= "				<AddressLine>" . $this->origin_addressline . "</AddressLine>" . "\n";
	    		$request .= "				<City>" . $this->origin_city . "</City>" . "\n";
	    		$request .= "				<PostalCode>" . $this->origin_postcode . "</PostalCode>" . "\n";
	    		$request .= "				<CountryCode>" . $this->origin_country . "</CountryCode>" . "\n";
	    		if ( $this->negotiated && $this->origin_state ) {
	    		$request .= "				<StateProvinceCode>" . $this->origin_state . "</StateProvinceCode>" . "\n";
	    		}
	    		$request .= "			</Address>" . "\n";
	    		$request .= "		</ShipFrom>" . "\n";
	    		$request .= "		<Service>" . "\n";
	    		$request .= "			<Code>" . $code . "</Code>" . "\n";
	    		$request .= "		</Service>" . "\n";
				// packages
	    		foreach ( $package_requests as $key => $package_request ) {
	    			$request .= $package_request;
	    		}
				// negotiated rates flag
	    		if ( $this->negotiated ) {
	    		$request .= "		<RateInformation>" . "\n";
	    		$request .= "			<NegotiatedRatesIndicator />" . "\n";
	    		$request .= "		</RateInformation>" . "\n";
				}
	    		$request .= "	</Shipment>" . "\n";
	    		$request .= "</RatingServiceSelectionRequest>" . "\n";

				$rate_requests[$code] = $request;

			} // if (enabled)
		} // foreach()

		return $rate_requests;
	}

    /**
     * per_item_shipping function.
     *
     * @access private
     * @param mixed $package
     * @return mixed $requests - an array of XML strings
     */
    private function per_item_shipping( $package ) {
	    global $woocommerce;

	    $requests = array();

		$ctr=0;
    	foreach ( $package['contents'] as $item_id => $values ) {
    		$ctr++;

    		if ( ! $values['data']->needs_shipping() ) {
    			$this->debug( sprintf( __( 'Product #%d is virtual. Skipping.', 'ups-woocommerce-shipping' ), $ctr ) );
    			continue;
    		}

    		if ( ! $values['data']->get_weight() ) {
	    		$this->debug( sprintf( __( 'Product #%d is missing weight. Aborting.', 'ups-woocommerce-shipping' ), $ctr ), 'error' );
	    		return;
    		}

			// get package weight
    		$weight = woocommerce_get_weight( $values['data']->get_weight(), $this->weight_unit );

			// get package dimensions
    		if ( $values['data']->length && $values['data']->height && $values['data']->width ) {

				$dimensions = array( number_format( woocommerce_get_dimension( $values['data']->length, $this->dim_unit ), 2, '.', ''),
									 number_format( woocommerce_get_dimension( $values['data']->height, $this->dim_unit ), 2, '.', ''),
									 number_format( woocommerce_get_dimension( $values['data']->width, $this->dim_unit ), 2, '.', '') );
				sort( $dimensions );

			} 

			// get quantity in cart
			$cart_item_qty = $values['quantity'];
			// get weight, or 1 if less than 1 lbs.
			// $_weight = ( floor( $weight ) < 1 ) ? 1 : $weight;

			$request  = '<Package>' . "\n";
			$request .= '	<PackagingType>' . "\n";
			$request .= '		<Code>02</Code>' . "\n";
			$request .= '		<Description>Package/customer supplied</Description>' . "\n";
			$request .= '	</PackagingType>' . "\n";
			$request .= '	<Description>Rate</Description>' . "\n";

			if ( $values['data']->length && $values['data']->height && $values['data']->width ) {
				$request .= '	<Dimensions>' . "\n";
				$request .= '		<UnitOfMeasurement>' . "\n";
				$request .= '	 		<Code>' . $this->dim_unit . '</Code>' . "\n";
				$request .= '		</UnitOfMeasurement>' . "\n";
				$request .= '		<Length>' . $dimensions[2] . '</Length>' . "\n";
				$request .= '		<Width>' . $dimensions[1] . '</Width>' . "\n";
				$request .= '		<Height>' . $dimensions[0] . '</Height>' . "\n";
				$request .= '	</Dimensions>' . "\n";
			}

			$request .= '	<PackageWeight>' . "\n";
			$request .= '		<UnitOfMeasurement>' . "\n";
			$request .= '			<Code>' . $this->weight_unit . '</Code>' . "\n";
			$request .= '		</UnitOfMeasurement>' . "\n";
			$request .= '		<Weight>' . $weight . '</Weight>' . "\n";
			$request .= '	</PackageWeight>' . "\n";
			if( $this->insuredvalue ) {
				$request .= '	<PackageServiceOptions>' . "\n";
				// InsuredValue
				if( $this->insuredvalue ) {
			
					$request .= '		<InsuredValue>' . "\n";
					$request .= '			<CurrencyCode>' . get_woocommerce_currency() . '</CurrencyCode>' . "\n";
					// WF: Calculating monetary value of cart item for insurance.
					$request .= '			<MonetaryValue>' . (string) ( $values['data']->get_price() * $cart_item_qty ). '</MonetaryValue>' . "\n";
					$request .= '		</InsuredValue>' . "\n";
				}

				$request .= '	</PackageServiceOptions>' . "\n";
			}
			$request .= '</Package>' . "\n";

			for ( $i=0; $i < $cart_item_qty ; $i++)
				$requests[] = $request;
    	}

		return $requests;
    }

    /**
     * wf_get_package_requests function.
     *
     * @access public
     * @return requests
     */
    public function wf_get_api_rate_box_data( $package, $packing_method ) {
	    $this->packing_method	= $packing_method;
		$requests 				= $this->get_package_requests($package);

		return $requests;
    }

}
