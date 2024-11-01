<?php
/**
 * @wordpress-plugin
 * Plugin Name: ShipInsure for WooCommerce
 * Plugin URI: https://shipinsure.io
 * Description: Shipment Protection for Merchants and Customers
 * Version: 1.6
 * Author: shipinsure
 * Author URI: https://www.shipinsure.io
 * License: GPL-2.0+
 * License URI:
 * Text Domain: shipinsure-for-woocommerce
 * WC tested up to: 7.6.0
*/

if ( ! defined( 'ABSPATH' ) ) exit; 

class ShipInsure_for_Woocommerce {

	/**
	 * WooCommerce ShipInsure version.
	 *
	 * @var string
	 */
	public $version = '1.6';

	/**
	 * Initialize the main plugin function
	*/
	public function __construct() {

		$this->plugin_file = __FILE__;

		// Add your templates to this array.
		if (!defined('SHIPINSURE_PATH')) {
			define( 'SHIPINSURE_PATH', $this->shipinsure_get_plugin_path());
		}

		register_activation_hook( __FILE__, array( $this, 'shipinsure_on_activation'));
		register_deactivation_hook( __FILE__, array( $this, 'shipinsure_on_deactivation') );
		// register_uninstall_hook( __FILE__, array( $this, 'shipinsure_on_uninstall' ) );

        if ( $this->shipinsure_is_woocommerce_active() ) {
            // Include required files.
            $this->shipinsure_includes();

            //start adding hooks
            $this->shipinsure_init();

            //admin class init
            $this->admin->shipinsure_init();

			add_action( 'wp_enqueue_scripts', array( $this, 'shipinsure_get_scripts' ) );

        }

		// ajax action to query wc product ids by title
		add_action('wp_ajax_shipinsure_get_product_id_by_sku', array($this, 'shipinsure_get_product_id_by_sku'));
		add_action('wp_ajax_nopriv_shipinsure_get_product_id_by_sku', array($this, 'shipinsure_get_product_id_by_sku'));
		add_action('wp_ajax_shipinsure_get_product_variations_by_id', array($this, 'shipinsure_get_product_variations_by_id'));
		add_action('wp_ajax_nopriv_shipinsure_get_product_variations_by_id', array($this, 'shipinsure_get_product_variations_by_id'));
		add_action('wp_ajax_shipinsure_check_if_staging_site', array($this, 'shipinsure_check_if_staging_site'));
		add_action('wp_ajax_nopriv_shipinsure_check_if_staging_site', array($this, 'shipinsure_check_if_staging_site'));
		add_action('wp_ajax_get_cart_contents', array($this, 'shipinsure_get_cart_contents_callback'));
		add_action('wp_ajax_nopriv_get_cart_contents', array($this, 'shipinsure_get_cart_contents_callback'));
		add_action('rest_api_init', array($this, 'shipinsure_register_endpoint'));
		add_filter('woocommerce_is_sold_individually', array($this, 'shipinsure_disable_quantity_on_si_product'), 10, 2);
		add_filter('woocommerce_product_variation_title_include_attributes', array($this, 'shipinsure_hide_si_variant_titles_in_cart'), 10, 2);
		add_filter('woocommerce_get_item_data', array($this, 'shipinsure_remove_si_sku_from_cart_variation_data'), 10, 2);
		add_filter('woocommerce_coupon_is_valid_for_product', array($this, 'shipinsure_exclude_product_from_promotions'), 9999, 4);


	}

	/**
	 * Helper function to retu
	 */
	public function shipinsure_get_scripts() {
		$script_tag_url = get_option('shipinsure_script_tag');
		$script_tag_version = get_option('shipinsure_script_version');

		$maybe_script_enabled = get_option('shipinsure_script_enabled');
		if($script_tag_url && 'true' == $maybe_script_enabled){
			wp_enqueue_script('shipinsure', $script_tag_url, array('jquery'), $script_tag_version, true);
			wp_localize_script('shipinsure', 'shipInsureData', array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('shipinsure_nonce_frontend'),
			));
		}
	}

	/**
	 * Callback on activation and allow to activate if pro deactivated
	 *
	 * @since  1.0.0
	*/
	public function shipinsure_on_activation() {

		if (!is_ssl()) {
			die('ShipInsure for WooCommerce could not be activated because SSL is not enabled.');
        }

		$shipinsure_user = get_user_by('login', 'shipinsure-api');

		if (!$shipinsure_user) {
			$random_password = wp_generate_password(12);
			$user_id = wp_create_user('shipinsure-api', $random_password, 'dev@shipinsure.io');

			if (!is_wp_error($user_id)) {
				$user = new WP_User($user_id);
				$user->set_role('shop_manager');

				$store_data = [];
				$store_data['account_status'] = 'installed';
				$store_data['country'] = get_option( 'woocommerce_default_country' );;
				$store_data['currency'] = get_option( 'woocommerce_currency' );;
				$store_data['name'] = get_bloginfo('name');
				$store_data['website_url'] = get_site_url();

				$args = array(
					'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
					'method' => 'POST',
					'body'   => wp_json_encode($store_data)
				);

				wp_remote_post('https://api.shipinsure.io/v1/woocommerce/install', $args);

			}
			
		} else {

			// assume we're already authorized so lets send our keys to SI
			$this->shipinsure_send_woocommerce_keys_to_si(null, 'reactivation');

		}

	}
	
	/**
	 * Callback on deactivation
	 *
	 * @since  1.0.0
	*/
	public function shipinsure_on_deactivation() {

		// global $wpdb;
	
		// $shipinsure_user = get_user_by('login', 'shipinsure-api');
	
		// if ($shipinsure_user) {
			
		// 	$user_id = $shipinsure_user->ID;
	
		// 	$table_name = $wpdb->prefix . 'woocommerce_api_keys';
			
		// 	// Properly prepare the SQL statement
		// 	$query = $wpdb->prepare("DELETE FROM $table_name WHERE user_id = %d", $user_id);
		// 	$result = $wpdb->query($query);
			
		// 	wp_delete_user( $shipinsure_user->ID );
	
		// }
	
		// delete_option('shipinsure_woocommerce_is_authorized');
		// delete_option('shipinsure_script_tag');
		// delete_option('shipinsure_script_version');
		// delete_option('shipinsure_woocommerce_keys');
	
	}

	/**
	 * Callback on activation and allow to activate if pro deactivated
	 *
	 * @since  1.0.0
	*/
	public function shipinsure_on_uninstall() {

		global $wpdb;

		$shipinsure_user = get_user_by('login', 'shipinsure-api');

		if ($shipinsure_user) {
			
			$user_id = $shipinsure_user->ID;

			$table_name = $wpdb->prefix . 'woocommerce_api_keys';
			$result = $wpdb->delete( $table_name, array( 'user_id' => $user_id ), array( '%d' ) );
			
			wp_delete_user( $shipinsure_user->ID );

		}

		delete_option('shipinsure_woocommerce_is_authorized');
		delete_option('shipinsure_script_tag');
		delete_option('shipinsure_script_version');
		delete_option('shipinsure_woocommerce_keys');

	}

	/**
	 * Include admin functionality.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	private function shipinsure_includes() {

		require_once $this->shipinsure_get_plugin_path() . '/includes/shipinsure-for-woocommerce-admin.php';

		$this->admin = ShipInsure_for_Woocommerce_Admin::shipinsure_get_instance();

	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @since 1.0.0
	 * @return bool
	*/
	private function shipinsure_is_woocommerce_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$is_active = true;
		} else {
			$is_active = false;
		}

		// Woocommerce active check
		if ( false === $is_active ) {
			add_action( 'admin_notices', array( $this, 'notice_activate_wc' ) );
		}
		return $is_active;
	}

	/**
	 * Display WC active notice
	*/
	public function shipinsure_notice_activate_wc() {
		?>
		<div class="error">
			<p>
			<?php
			/* translators: %s: search WooCommerce plugin link */
			printf( esc_html__( 'Please install and activate %1$sWooCommerce%2$s for ShipInsure for WooCommerce!', 'shipinsure-for-woocommerce' ), '<a href="' . esc_url( admin_url( 'plugin-install.php?tab=search&s=WooCommerce&plugin-search-input=Search+Plugins' ) ) . '">', '</a>' );
			?>
			</p>
		</div>
		<?php
	}

	/*
	* init when class loaded
	*/
	public function shipinsure_init() {
		add_filter( 'http_request_args', function( $args ) {
		    $args['reject_unsafe_urls'] = false;

		    return $args;
		});
	
		//Custom Woocomerce menu
		add_action( 'admin_menu', array( $this->admin, 'shipinsure_register_menu' ), 99 );
	}

	/**
	 * Gets the absolute plugin path without a trailing slash, e.g.
	 * /path/to/wp-content/plugins/plugin-directory.
	 *
	 * @return string plugin path
	 */
	public function shipinsure_get_plugin_path() {
		if ( isset( $this->plugin_path ) ) {
			return $this->plugin_path;
		}

		$this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );

		return $this->plugin_path;
	}

	/*
	* return plugin directory URL
	*/
	public function shipinsure_plugin_dir_url() {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * Grab WC product ID by SKU.
	 */
	public function shipinsure_get_product_id_by_sku() {
		$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	
		if (!wp_verify_nonce($nonce, 'shipinsure_nonce_frontend')) {
			wp_die('Nonce verification failed', 'Security Error', ['response' => 403]);
		}

		if (isset($_POST['product_sku']) && !empty($_POST['product_sku'])) {
			$product_sku = sanitize_text_field($_POST['product_sku']);
			if (!empty($product_sku)) {
				$product_id = wc_get_product_id_by_sku($product_sku);
				wp_send_json_success(array('product_id' => $product_id));
			} else {
				wp_send_json_error('Product SKU is invalid.');
			}
		} else {
			wp_send_json_error('Product SKU missing.');
		}
	}

	/**
	 * Get product variations by product ID.
	 *
	 * This function checks if a valid product ID is provided via POST, validates that the product exists and is a variable product,
	 * and then retrieves and returns its variations.
	 */
	public function shipinsure_get_product_variations_by_id() {
		$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	
		if (!wp_verify_nonce($nonce, 'shipinsure_nonce_frontend')) {
			wp_die('Nonce verification failed', 'Security Error', ['response' => 403]);
		}

		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
		if (empty($product_id) || !is_numeric($product_id)) {
			wp_send_json_error(['message' => 'Invalid product ID.']);
			return;
		}
		$product = wc_get_product($product_id);
		if (!$product || $product->get_type() !== 'variable') {
			wp_send_json_error(['message' => 'Product is not a variable product or does not exist.']);
			return;
		}
		$variations = $product->get_available_variations();
		wp_send_json_success($variations);
	}

	/**
	 * Register endpoint for SI plugin
	 */
	public function shipinsure_register_endpoint() {
		register_rest_route('shipinsure/v1', '/script_tags', array(
			'methods' => 'POST',
			'callback' => array($this, 'shipinsure_script_endpoint_handler'),
			'args' => array(),
			'permission_callback' => function($request) {
				$api_key_header = $request->get_header('X-API-KEY');
				$api_key_header_hash = $this->shipinsure_check_wc_api_hash($api_key_header);
				$si_api_keys = $this->shipinsure_get_woo_api_key();

				if(in_array($api_key_header, $si_api_keys) || in_array($api_key_header_hash, $si_api_keys)) return true;

			},
		));
		register_rest_route('shipinsure/v1', '/script_tags', array(
			'methods' => 'DELETE',
			'callback' => array($this, 'shipinsure_delete_script_endpoint_handler'),
			'args' => array(),
			'permission_callback' => function($request) {
				$api_key_header = $request->get_header('X-API-KEY');
				$api_key_header_hash = $this->shipinsure_check_wc_api_hash($api_key_header);
				$si_api_keys = $this->shipinsure_get_woo_api_key();

				if(in_array($api_key_header, $si_api_keys) || in_array($api_key_header_hash, $si_api_keys)) return true;

			},
		));
		register_rest_route('shipinsure/v1', '/script_tags', array(
			'methods' => 'GET',
			'callback' => array($this, 'shipinsure_get_script_endpoint_handler'),
			'args' => array(),
			'permission_callback' => function($request) {
				$api_key_header = $request->get_header('X-API-KEY');
				$api_key_header_hash = $this->shipinsure_check_wc_api_hash($api_key_header);
				$si_api_keys = $this->shipinsure_get_woo_api_key();

				if(in_array($api_key_header, $si_api_keys) || in_array($api_key_header_hash, $si_api_keys)) return true;

			},
		));
		register_rest_route('shipinsure/v1', '/save_api_keys', array(
			'methods' => 'POST',
			'callback' => array($this, 'shipinsure_save_api_keys'),
			'args' => array(),
			'permission_callback' => function($request) {
				return true;
			},
		));
	}

	/**
	 * Handler function for script endpoint to save data as options.
	 */
	public function shipinsure_script_endpoint_handler(WP_REST_Request $request) {
		$script_url = $request->get_param('script_url');
		$script_version = $request->get_param('script_version');
		
		update_option('shipinsure_script_tag', $script_url);
		update_option('shipinsure_script_version', $script_version);
		
		return new WP_REST_Response(array(
			'status' => 'success',
			'src' => $script_url,
			'version' => $script_version,
			
			'shipinsure_script_tag_option_id' => $this->shipinsure_get_option_id_by_name( 'shipinsure_script_tag' ),
			'shipinsure_script_version_option_id' => $this->shipinsure_get_option_id_by_name( 'shipinsure_script_version' ),
		), 200);
	}

	/**
	 * Handler function to get saved script tag url and version.
	 */
	public function shipinsure_get_script_endpoint_handler(WP_REST_Request $request) {
		
		$script_url = get_option('shipinsure_script_tag');
		$script_version = get_option('shipinsure_script_version');
		
		return new WP_REST_Response(array(
			'status' => 'success',
			'src' => $script_url,
			'version' => $script_version,
			
			'shipinsure_script_tag_option_id' => $this->shipinsure_get_option_id_by_name( 'shipinsure_script_tag' ),
			'shipinsure_script_version_option_id' => $this->shipinsure_get_option_id_by_name( 'shipinsure_script_version' ),
		), 200);
	}

	/**
	 * Saves API keys for the ShipInsure integration.
	 *
	 * This method is designed to be an endpoint for the WP REST API.
	 * When called, it sets the 'shipinsure_woocommerce_is_authorized' option to true,
	 * then retrieves the user with the login 'shipinsure-api'. If the user exists,
	 * it updates the WooCommerce API keys to set the user ID for keys where the
	 * description contains 'shipinsure'.
	 *
	 * @param WP_REST_Request $request The request object from the WP REST API.
	 * 
	 * @return WP_REST_Response Returns a response object indicating the success status
	 *                          and the original request source.
	 */
	public function shipinsure_save_api_keys(WP_REST_Request $request) {

		global $wpdb;

		update_option('shipinsure_woocommerce_is_authorized', true);

		$user_id = get_user_by('login', 'shipinsure-api');

		$consumer_key = $request->get_param('consumer_key');
		$consumer_secret = $request->get_param('consumer_secret');
	
		$keys_array = array(
			'consumer_key' => $consumer_key,
			'consumer_secret' => $consumer_secret,
		);

		update_option('shipinsure_woocommerce_keys', $keys_array);
	
		if($user_id){
			$user_id = $user_id->ID;
		
			$query = $wpdb->prepare("
				UPDATE {$wpdb->prefix}woocommerce_api_keys
				SET user_id = %d
				WHERE description LIKE %s
			", $user_id, '%shipinsure - API%');

			$wpdb->query($query);
		}

		$this->shipinsure_send_woocommerce_keys_to_si($keys_array);
		
		return;
	}

	/** Handle sending Woocomm API keys to SI API */
	public function shipinsure_send_woocommerce_keys_to_si($keys_array = null, $scope = null) {

		if($keys_array == null){
			$keys_array = get_option('shipinsure_woocommerce_keys');
		}

		if($keys_array) {
			$consumer_key = isset($keys_array['consumer_key']) ? $keys_array['consumer_key'] : null;
			$consumer_secret = isset($keys_array['consumer_secret']) ? $keys_array['consumer_secret'] : null;
		} else {
			return;
		}

		$application_password = '';
		$user = get_user_by('login', 'shipinsure-api');
		if($user){
			$user_id = $user->ID;
			$application_exists = WP_Application_Passwords::application_name_exists_for_user( $user_id, 'ShipInsure' );
			$application_password = '';
			if ( ! $application_exists ) {
				$application_password = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => 'ShipInsure' ) );
			}
		}

		// send store info to SI API
		$store_data = [];
		$store_data['consumer_key'] = $consumer_key;
		$store_data['consumer_secret'] = $consumer_secret;
		$store_data['application_password'] = $application_password;
		$store_data['website_url'] = get_site_url();

		$args = array(
			'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
			'method' => 'POST',
			'body'   => wp_json_encode($store_data)
		);

		wp_remote_post('https://api.shipinsure.io/v1/woocommerce/install', $args);

		if($scope == 'reactivation'){
			return;
		} else {
			$response = new WP_REST_Response(array(
				'status' => 'success',
				'src' => $request,
			), 200);
			return $response;
		}

	}

	/**
	 * Handler function for script endpoint to delete script data options.
	 */
	public function shipinsure_delete_script_endpoint_handler(WP_REST_Request $request) {
		
		delete_option('shipinsure_script_tag');
		delete_option('shipinsure_script_version');
		
		return new WP_REST_Response(array(
			'status' => 'success',
		), 200);
	}

	/**
	 * Helper function to find Woo API key from our SI user
	 */
	public function shipinsure_get_woo_api_key(){
		global $wpdb;

		$user_id = get_user_by('login', 'shipinsure-api');

		if($user_id){
			$user_id = $user_id->ID;
		} else {
			return 'No SI user found.';
		}

		$keys = $wpdb->get_col($wpdb->prepare("SELECT consumer_key FROM {$wpdb->prefix}woocommerce_api_keys WHERE user_id = %s", $user_id));

		$si_keys_option = get_option('shipinsure_woocommerce_keys');
		if ($si_keys_option && isset($si_keys_option['consumer_key'])) {
			$keys[] = $si_keys_option['consumer_key'];
		}
	
		$modified_keys = [];
		foreach ($keys as $key) {
			if (strpos($key, 'ck_') === 0) {
				$modified_keys[] = substr($key, 3);
			} else {
				$modified_keys[] = 'ck_' . $key;
			}
		}
	
		$all_keys = array_merge($keys, $modified_keys);
	
		return $all_keys;
	}

	/**
	 * Hashes the given data using HMAC SHA256 algorithm with a 'wc-api' secret key.
	 * 
	 * This function provides a consistent hashing mechanism for various parts of the WooCommerce API 
	 * and can be used to ensure data integrity and authentication.
	 *
	 * @param string $data The input string to be hashed.
	 * 
	 * @return string The resulting HMAC hash in hex format.
	 */
	public function shipinsure_check_wc_api_hash($data) {
		return hash_hmac('sha256', $data, 'wc-api');
	}

	/**
	 * Disable quantity on SI product.
	 *
	 * @param bool $return Whether to disable quantity or not.
	 * @param \WC_Product $product The WooCommerce product object.
	 *
	 * @return bool True if quantity should be disabled, false otherwise.
	 */
	public function shipinsure_disable_quantity_on_si_product($return, $product) {

		if ($product !== null && $product->get_sku() && strpos($product->get_sku(), 'SI_LEVEL') !== false) {
			return true;
		}
		
		return false;

	}

	/**
	 * Retrieves the option_id for a given option name from the WordPress options table.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $option_name The name of the option to retrieve its ID.
	 * @return int|bool Returns the option_id as an integer if found, or false if not found.
	 */
	public function shipinsure_get_option_id_by_name( $option_name ) {
		global $wpdb;
	
		$query = $wpdb->prepare( "SELECT option_id FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option_name );
		$result = $wpdb->get_var( $query );
	
		return $result ? intval( $result ) : false;
	}

	/**
	 * Determines whether or not to hide the SI variant titles in the cart based on the parent product's title.
	 *
	 * @param bool $should_include_attributes Flag indicating if attributes should be included.
	 * @param mixed $product The product object or data.
	 * 
	 * @return bool Returns false if the parent product has a title, otherwise returns the original value of $should_include_attributes.
	 */
	public function shipinsure_hide_si_variant_titles_in_cart($should_include_attributes, $product){
		if (is_object($product) && method_exists($product, 'get_parent_data') && isset($product->get_parent_data()['title'])) {
			$should_include_attributes = false;
		}
		return $should_include_attributes;
	}

	/**
	 * Conditionally remove SKU from cart variation data based on product parent title.
	 * 
	 * @param array $item_data An array of meta data for cart item.
	 * @param array $cart_item Cart item object.
	 * 
	 * @return array Modified item data.
	 */
	public function shipinsure_remove_si_sku_from_cart_variation_data($item_data, $cart_item) {
		$product = $cart_item['data'];
		
		if (is_object($product) && method_exists($product, 'get_parent_data') && isset($product->get_parent_data()['title'])) {
			foreach ($item_data as $key => $data) {
				if ('SKU' === $data['key']) {
					unset($item_data[$key]);
				}
			}
		}
		
		return $item_data;
	}

	/**
	 * Fetches the contents of the WooCommerce cart.
	 * 
	 * Returns a list of items currently in the cart. Each item includes:
	 * - key: The unique cart item key
	 * - name: The product name
	 * - quantity: The quantity of the product in the cart
	 * 
	 * @return void Outputs a JSON encoded array of cart items and terminates script execution.
	 */
	public function shipinsure_get_cart_contents_callback() {
		$cart_items = WC()->cart->get_cart();
	
		$items = [];
		foreach ($cart_items as $cart_item_key => $cart_item) {
			$product = $cart_item['data'];
			$items[] = [
				'key' => $cart_item_key,
				'name' => $product->get_name(),
				'quantity' => $cart_item['quantity'],
			];
		}
	
		echo wp_json_encode($items);
		wp_die();
	}

	/**
	 * Checks if the current installation is a staging site.
	 */
	public function shipinsure_check_if_staging_site() {
		$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	
		if (!wp_verify_nonce($nonce, 'shipinsure_nonce_frontend')) {
			wp_die('Nonce verification failed', 'Security Error', ['response' => 403]);
		}

		$is_staging_site = get_option('shipinsure_is_staging_site');

		if($is_staging_site && $is_staging_site !== 'false'){
			$production_url = get_option('shipinsure_production_site_url');
			if($production_url !== ''){
				wp_send_json_success(array('production_url' => $production_url));
			}
		} else {
			wp_send_json_success(array('production_url' => ''));
		}
	}

	/**
	 * Exclude ShipInsure products from promotions/coupons.
	 *
	 * @param bool $valid Determines if the coupon is valid for the product.
	 * @param WC_Product $product The product being checked.
	 * @param WC_Coupon $coupon The coupon being applied.
	 * @param array $values Additional values.
	 * 
	 * @return bool Whether the coupon is valid for the product.
	 */
	public function shipinsure_exclude_product_from_promotions( $valid, $product, $coupon, $values ) {

		if ( strpos( $product->get_sku(), 'SI_LEVEL' ) !== false || stripos( $product->get_name(), 'shipinsure' ) !== false ) {
			$valid = false;
		}
	
		return $valid;
	}
	
}

/**
 * Returns an instance of ShipInsure_for_Woocommerce.
 *
 * @since 1.6.5
 * @version 1.6.5
 *
 * @return ShipInsure_for_Woocommerce
*/
function shipinsure() {
	static $xinstance;

	if ( ! isset( $xinstance ) ) {
		$xinstance = new ShipInsure_for_Woocommerce();
	}

	return $xinstance;
}

/**
 * Register this class globally.
 *
 * Backward compatibility.
*/
$shipinsure_instance = shipinsure();
/*
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
*/