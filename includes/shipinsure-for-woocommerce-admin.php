<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShipInsure_for_Woocommerce_Admin {

	/**
	 * Initialize the main plugin function
	*/
	public function __construct() {

		global $wpdb;
       
	}

	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	 */
	private static $instance;

	/**
	 * Get the class instance
	 *
	 * @return ShipInsure_for_Woocommerce_Admin
	*/
	public static function shipinsure_get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/*
	* init from parent mail class
	*/
	public function shipinsure_init() {

		add_action('admin_init', array($this, 'shipinsure_authorization'));
		add_action('admin_enqueue_scripts', array($this, 'shipinsure_admin_styles'));
		add_action('wp_ajax_get_si_script_status', array($this, 'shipinsure_get_si_script_status'));
		add_action('wp_ajax_update_si_script_status', array($this, 'shipinsure_update_si_script_status'));
		add_action('wp_ajax_update_si_script_tag', array($this, 'shipinsure_update_si_script_tag'));
		add_action('wp_ajax_update_si_site_settings', array($this, 'shipinsure_update_si_site_settings'));

	}

	/**
	 * Handles the WooCommerce SI authorization process.
	 * 
	 * 1. Checks if WooCommerce SI is already authorized.
	 * 2. Retrieves the user ID for 'shipinsure-api'.
	 * 3. Redirects to the WooCommerce authorization endpoint with necessary parameters.
	 * 
	 * @return void|string Redirects to the WooCommerce auth endpoint or returns a message if no SI user found.
	 */
	public function shipinsure_authorization() {

		$maybe_already_authorized = get_option('shipinsure_woocommerce_is_authorized');
		if ($maybe_already_authorized) {
			return;
		}

		$user = get_user_by('login', 'shipinsure-api');
		if ($user) {
			$user_id = $user->ID;
		} else {
			return 'No SI user found.';
		}

		// Construct the redirect URL for WooCommerce authorization
		$redirect_url = get_site_url() . '/wc-auth/v1/authorize?' . http_build_query([
			'app_name'     => 'ShipInsure',
			'scope'        => 'read_write',
			'user_id'      => $user_id,
			'return_url'   => get_site_url() . '/wp-admin/admin.php?page=shipinsure-for-woocommerce',
			'callback_url' => get_site_url() . '/wp-json/shipinsure/v1/save_api_keys'
		]);
		
		wp_redirect($redirect_url);
	}

	/**
	* Load admin styles.
	*/
	public function shipinsure_admin_styles( $hook ) {

		if ( !isset( $_GET['page'] ) ) {
			return;
		}
	
		if( 'shipinsure-for-woocommerce' == $_GET['page'] ) {
			wp_register_style( 'shipinsure-admin-styles',  plugin_dir_url(__FILE__).'styles.css' );
			wp_enqueue_style( 'shipinsure-admin-styles' );
		}
	
		wp_enqueue_script('si-admin-scripts', plugins_url('admin-scripts.js', __FILE__), array('jquery'), null, true);
		
		wp_localize_script('si-admin-scripts', 'myAjax', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('shipinsure_ajax_nonce'),
		));
	
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/*
	* Admin Menu add function
	* WC sub menu
	*/
	public function shipinsure_register_menu() {
		add_submenu_page( 'woocommerce', 'ShipInsure', __( 'ShipInsure', 'shipinsure-for-woocommerce' ), 'manage_woocommerce', 'shipinsure-for-woocommerce', array( $this, 'shipinsure_admin_page_callback' ) );
	}

	/**
	 * Retrieve the site's URL without common URL prefixes and protocols.
	 *
	 * This function removes 'http://', 'https://', 'https://www.', 'http://www.', 
	 * and 'www.' from the site's URL to provide a clean domain name.
	 *
	 * @return string The sanitized domain name without common URL prefixes and protocols.
	 */
	public function shipinsure_get_shop_url_without_protocol() {

		$is_staging_site = get_option('shipinsure_is_staging_site');

		$site_url = get_bloginfo('url');

		if($is_staging_site && $is_staging_site !== 'false'){
			$production_url = get_option('shipinsure_production_site_url');
			if($production_url !== ''){
				$site_url = $production_url;
			}
		}

		$parsed_url = parse_url($site_url);

		$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		if (substr($host, 0, 4) === 'www.') {
			$host = substr($host, 4);
		}
	
		return $host;
	}
	

	/**
	 * Retrieve merchant details by shop domain from ShipInsure API.
	 *
	 * This function strips common URL prefixes from the site's URL and
	 * then fetches merchant details from the ShipInsure API using the sanitized domain.
	 *
	 * @return string Response body containing merchant details.
	 *
	 * @throws WP_Error If the HTTP request returns an error.
	 */
	public function shipinsure_get_merchant(){
		$response = wp_remote_get('https://api.shipinsure.io/v1/merchant/byShopifyDomain/'.$this->shipinsure_get_shop_url_without_protocol())['body'];
		return $response;
	}

	/*
	* callback for ShipInsure page
	*/
	public function shipinsure_admin_page_callback() {

		// Display setup progress information
		global $order, $wpdb;
		
		echo '<h1>Welcome to ShipInsure</h1>';

		$is_staging_site = get_option('shipinsure_is_staging_site', false);
		$site_production_url = get_option('shipinsure_production_site_url', false);

		if(!$is_staging_site && !$site_production_url){
		?>

		<div class="status-container si_staging_container">
			<form method="post">
				<h3>Is this a staging site?</h3>
				<label><input type="radio" class="shipinsure_is_staging_site" name="shipinsure_is_staging_site" value="true" <?php echo ($is_staging_site === 'true') ? 'checked' : ''; ?>> Yes</label>
				<label><input type="radio" class="shipinsure_is_staging_site" name="shipinsure_is_staging_site" value="false" <?php echo ($is_staging_site === 'false') ? 'checked' : ''; ?>> No</label><br/><br/>
				<p class="shipinsure_production_url_container">
					<label for="shipinsure_production_url">Live Production Site URL:</label><br/>
					<input type="text" id="shipinsure_production_url" name="shipinsure_production_url" value="<?php echo esc_attr($site_production_url); ?>" placeholder="None">
				</p>
				<button type="submit" id="saveStagingSettings" class="button action">Save Settings</button>
			</form>
		</div>
		<?php
			return;
		}
		$si_merchant_status = $this->shipinsure_get_merchant();
		if ($si_merchant_status) {
			$si_merchant_status = json_decode($si_merchant_status);
		}

		if (is_object($si_merchant_status)) {
			$merchant_status = isset($si_merchant_status->user_account_created) && $si_merchant_status->user_account_created ? 'yes' : 'no';
			$billing_status = isset($si_merchant_status->billing_setup) && $si_merchant_status->billing_setup ? 'yes' : 'no';
			$widget_status = isset($si_merchant_status->script_tag_enabled) && $si_merchant_status->script_tag_enabled ? 'yes' : 'no';
		} else {
			$merchant_status = 'no';
			$billing_status = 'no';
			$widget_status = 'no';
		}

		$countTrue = 0;

		if (is_object($si_merchant_status)) {
			$countTrue += isset($si_merchant_status->user_account_created) && $si_merchant_status->user_account_created ? 1 : 0;
			$countTrue += isset($si_merchant_status->billing_setup) && $si_merchant_status->billing_setup ? 1 : 0;
			$countTrue += isset($si_merchant_status->script_tag_enabled) && $si_merchant_status->script_tag_enabled ? 1 : 0;
		}

		$percentage = ($countTrue / 3) * 100;

		$current_user = wp_get_current_user();
		$disabled = ($current_user->user_login !== 'shipinsure-api') ? 'disabled' : '';
		?>
		<style>
		/* Progress Bar Slider */
		#progress-slider {
			width: 100%;
			-webkit-appearance: none;
			appearance: none;
			height: 25px;
			background: linear-gradient(to right, #007BFF 0% <?php echo floatval($percentage); ?>%, #e0e0e0 <?php echo floatval($percentage); ?>% 100%);
			border-radius: 12.5px;
			outline: none;
			margin-top:5%;
		}        
		.enable-si-script{
			font-size: 18px;
			padding-top: 20px;
		}
		</style>

		<div class="status-container si_status">
			<h3>Setup Progress:</h3>
			<div class="progress-container">
				<span class="task-count"><?php echo intval($countTrue); ?> of 3 tasks completed:</span>
				<input type="range" min="0" max="3" step="1" value="<?php echo intval($countTrue); ?>" id="progress-slider" disabled>
			</div>


			<ul class="status-list">
				<li><span class="dashicons dashicons-<?php echo esc_html($merchant_status); ?>"></span> <a href="https://go.shipinsure.net/onboarding" target="_blank">Create merchant account</a></li>
				<li><span class="dashicons dashicons-<?php echo esc_html($billing_status); ?>"></span> Billing setup (Settings -> Billing)</li>
				<li><span class="dashicons dashicons-<?php echo esc_html($widget_status); ?>"></span> Activate</li>
			</ul>

			<div class="enable-si-script"><input name="enable-si-script" type="checkbox"> Enable ShipInsure Script</div>
		</div>

		<div class="scripts-container">
			<h3>Asset Information:</h3>
			<?php if($is_staging_site && $is_staging_site !== 'false'){ ?>
			<label for="shipinsure_script_tag">ShipInsure Production Site URL:</label><br/>
			<input type="text" id="shipinsure_production_site_url" name="shipinsure_production_site_url" value="<?php echo get_option('shipinsure_production_site_url'); ?>" <?php echo $disabled; ?>/><br/><br/>
			<?php } ?>
			<label for="shipinsure_script_tag">ShipInsure Script URL:</label><br/>
			<input type="text" id="shipinsure_script_tag" name="shipinsure_script_tag" value="<?php echo get_option('shipinsure_script_tag'); ?>" <?php echo $disabled; ?>/><br/><br/>
			<label for="shipinsure_script_version">ShipInsure Script Version:</label><br/>
			<input type="text" id="shipinsure_script_version" name="shipinsure_script_version" value="<?php echo get_option('shipinsure_script_version'); ?>" <?php echo $disabled; ?>/><br/><br/>
			<?php if(!$disabled){?>
				<button id="saveButton" class="button action">Update</button>
			<?php } ?>
		</div>
		<?php
	}
	

	public function shipinsure_get_si_script_status() {
		$status = get_option('shipinsure_script_enabled', 'false');
		echo esc_html($status);
		wp_die();
	}
	
	public function shipinsure_update_si_script_status() {
		$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	
		if (!wp_verify_nonce($nonce, 'shipinsure_ajax_nonce')) {
			wp_die('Nonce verification failed', 'Security Error', ['response' => 403]);
		}
	
		if (isset($_POST['status']) && !empty($_POST['status'])) {
			$status_raw = sanitize_text_field(wp_unslash($_POST['status']));
	
			if (in_array($status_raw, ['true', 'false'], true)) {
				$status = $status_raw;
				update_option('shipinsure_script_enabled', $status);
			} else {
				wp_die('Invalid status value provided.', 'Validation Error', ['response' => 400]);
			}
		} else {
			wp_die('Status value is missing or empty.', 'Validation Error', ['response' => 400]);
		}
	
		wp_die();
	}

	public function shipinsure_update_si_script_tag() {
		$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	
		if (!wp_verify_nonce($nonce, 'shipinsure_ajax_nonce')) {
			wp_die('Nonce verification failed', 'Security Error', ['response' => 403]);
		}
	
		if (isset($_POST['script_tag']) && !empty($_POST['script_tag'])) {
			$script_tag_raw = sanitize_text_field(wp_unslash($_POST['script_tag']));
			$script_tag = $script_tag_raw;
			update_option('shipinsure_script_tag', $script_tag);
		} else {
			wp_die('Script tag value is missing or empty.', 'Validation Error', ['response' => 400]);
		}

		if (isset($_POST['script_version']) && !empty($_POST['script_version'])) {
			$script_version_raw = sanitize_text_field(wp_unslash($_POST['script_version']));
			$script_version = $script_version_raw;
			update_option('shipinsure_script_version', $script_version);
		} else {
			wp_die('Script version value is missing or empty.', 'Validation Error', ['response' => 400]);
		}
	
		wp_die();
	}

	public function shipinsure_update_si_site_settings() {
		$nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
	
		if (!wp_verify_nonce($nonce, 'shipinsure_ajax_nonce')) {
			wp_die('Nonce verification failed', 'Security Error', ['response' => 403]);
		}
	
		if (isset($_POST['is_staging']) && !empty($_POST['is_staging'])) {
			$is_staging_raw = sanitize_text_field(wp_unslash($_POST['is_staging']));
			update_option('shipinsure_is_staging_site', $is_staging_raw);
		} else {
			wp_die('Is staging value is missing or empty.', 'Validation Error', ['response' => 400]);
		}

		if (isset($_POST['production_url']) && !empty($_POST['production_url'])) {
			$production_url = sanitize_text_field(wp_unslash($_POST['production_url']));
			update_option('shipinsure_production_site_url', $production_url);
		}
	
		wp_die();
	}
	
}
