<?php
/**
 * Contains the settings class for the Pardot plugin.
 *
 * @package Pardot
 */

/**
 * Pardot plugin settings.
 */
class Pardot_Settings {


	/**
	 * Link to App Manager on Lightning where users can create their connected app.
	 *
	 * @var string
	 */
	const APP_MANAGER_URL = 'https://login.salesforce.com/lightning/setup/NavigationMenus/home';

	/**
	 * Link to the settings page on Lightning where users can find their business unit id.
	 *
	 * @var string
	 */
	const BUSINESS_UNIT_ID_URL = 'https://login.salesforce.com/lightning/setup/PardotAccountSetup/home';

	/**
	 * Tracking domain used for various things.
	 * @todo This domain can be different per instance. Need to change it to use the selected tracking domain and default to the primary. <brennan@>
	 */
	const INLINE_FORM_URL = 'https://go.pardot.com';

	/**
	 * @todo Figure out what this is for.
	 */
	private static $CODE_VERIFIER = 'pardot-code-verifier';

	/**
	 * Slug for the settings page.
	 * @var string
	 */
	private static $settings_page;

	/**
	 * @var array Contain array of the fields for the Settings API.
	 * Used as a const, defined as a var so it can be private.
	 */
	private static array $settings;

	/**
	 * Defines the settings sections.
	 *
	 * @var array
	 */
	private static array $settings_sections;

	/**
	 * @var null|string String containing the menu page's "hook_suffix" in case others need to access it.
	 * @see http://codex.wordpress.org/Function_Reference/add_options_page
	 */
	private static $admin_page = null;

	/**
	 * @var bool A flag to help combat a bug where settings-page admin notices show twice per submission.
	 * @see https://core.trac.wordpress.org/ticket/21989
	 */
	private static $showed_auth_notice = false;

	/**
	 * Contains the singleton instance of this class.
	 *
	 * @var Pardot_Settings
	 */
	private static $_instance;

	/**
	 * The hook_suffix for the submenu page.
	 *
	 * @var string|false
	 */
	private static $submenu_page_hook;

	/**
	 * Adds action and filter hooks when this singleton object is instantiated.
	 */
	private function __construct() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = $this;
		}

		self::$settings_page = Pardot::$plugin_data['TextDomain'];

		self::$settings_sections = array(
			array(
				'name' => __( 'Account', 'pardot' ),
				'slug' => 'account',
				'tab' => 'account',
			),
			array(
				'name' => __( 'General', 'pardot' ),
				'slug' => 'general',
				'tab' => 'general',
			),
		);

		// Concat the Business Unit ID description.
		$buid_desc = __( 'Find your Pardot Business Unit ID in', 'pardot' ) . '<a href="' . self::BUSINESS_UNIT_ID_URL . '" target="_blank"> ' . __( 'Pardot Account Setup', 'pardot' ) . '<span class="dashicons dashicons-external" aria-hidden="true" style="font-size:0.8rem;text-decoration:none;"></span></a>';

		self::$settings = array(
			'api_version'       => new Pardot_Setting( 'api_version', true, Pardot::$plugin_data['Version'] ),
			'auth_status'       => new Pardot_Setting( 'auth_status', false, \null, __( 'Authentication Status', 'pardot' ), 'status', __( 'Account settings.', 'pardot' ), self::$settings_sections[0]['tab'], self::$settings_sections[0]['slug'] ),
			'sandbox'           => new Pardot_Setting( 'sandbox', false, 'on', __( 'Sandbox', 'pardot' ), 'checkbox', '', self::$settings_sections[0]['tab'], self::$settings_sections[0]['slug'] ),
			'consumer_key'      => new Pardot_Setting( 'consumer_key', false, \null, __( 'Consumer Key', 'pardot' ), 'text', '', self::$settings_sections[0]['tab'], self::$settings_sections[0]['slug'] ),
			'consumer_secret'   => new Pardot_Setting( 'consumer_secret', false, \null, __( 'Consumer Secret', 'pardot' ), 'password', '', self::$settings_sections[0]['tab'], self::$settings_sections[0]['slug'] ),
			'business_unit_id'  => new Pardot_Setting( 'business_unit_id', false, \null, __( 'Business Unit ID', 'pardot' ), 'text', $buid_desc, self::$settings_sections[0]['tab'], self::$settings_sections[0]['slug'] ),
			'campaign'          => new Pardot_Setting( 'campaign', false, \null, __( 'Associated Campaign', 'pardot' ), 'select', __( 'Used for tracking code', 'pardot' ), self::$settings_sections[1]['tab'], self::$settings_sections[1]['slug'], array( self::get_instance(), 'campaign_select_callback' ) ),
			'always_use_https' 	=> new Pardot_Setting( 'always_use_https', false, \null, __( 'Always Use HTTPS', 'pardot' ), 'checkbox', '', self::$settings_sections[1]['tab'], self::$settings_sections[1]['slug'] ),
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

		// Configure the settings page for the Pardot Plugin if we are currently on the settings page.
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		// Configure the admin menu that points to this settings page for the Pardot Plugin.
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		/*
		 * Present an admin message telling the user to configure the plugin if there are not yet any credentials.
		 * This gets displayed at the top of an admin page.
		 */
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		/*
		 * Because a crypto key is *REQUIRED* we're going to check to see if a pardot crypto key exists
		 * in the settings table, and if it doesn't we're going to create one for use. We only ever want to do this
		 * one time so it's not going to be continuing logic and it won't be stored with other pardot settings
		 */
		if ( get_option( 'pardot_crypto_key', null ) === null ) {
			$crypto = new PardotCrypto();
			$crypto->set_key();
		}
	}

	/**
	 * Return the instance of this class.
	 *
	 * @return Pardot_Settings
	 */
	public static function get_instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Use to determine if we are in the Pardot Settings admin page.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	static function is_admin_page() {
		global $pagenow;
		// $is_admin_page = false;

		// if ( ! isset( $is_admin_page ) ) {

		// 	// Are we on the plugin's settings page?
		// 	$is_admin_page = 'options-general.php' == $pagenow && isset( $_GET['page'] ) && self::$settings_page == $_GET['page'];

		//	// Maybe we are trying to update the settings?
		// 	if (!$is_admin_page) {
		// 	$is_admin_page = 'options.php' == $pagenow &&
		// 	isset($_POST['action']) && 'update' == $_POST['action'] &&
		// 	isset($_POST['option_page']) && self::$OPTION_GROUP == $_POST['option_page'];
		// 	}
		// }

		return 'options-general.php' == $pagenow && isset( $_GET['page'] ) && self::$settings_page == $_GET['page'];
	}

	/**
	 * Load the admin submenu.
	 */
	public function admin_menu() {
		self::$submenu_page_hook = add_submenu_page( 'options-general.php', Pardot::$plugin_data['Name'], Pardot::$plugin_data['Name'], 'manage_options', Pardot::$plugin_data['TextDomain'], array( self::get_instance(), 'menu_page_callback' ) );

		add_action( 'load-' . self::$submenu_page_hook, array( self::get_instance(), 'load_menu_screen' ) );
	}

	/**
	 * Display admin notices if applicable.
	 *
	 * @since 1.0.0
	 */
	function admin_notices() {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'manage_options' ) ) {
			/**
			 * If the user can't install plugins or manage options, then of course we should bail!
			 * No message for you!
			 */
			return;
		}

		if ( self::is_admin_page() ) {
			/**
			 * No need to ask them to visit the settings page if they are already here
			 */
			return;
		}

		if ( Pardot_API::$is_authenticated ) {
			/**
			 * No need to ask them to configure of they have already configured.
			 */
			return;
		}

		/**
		 * The Pardot plugin has been activated but it can't be authenticated yet because it has no credentials.
		 * Give the user a message so they know where to go to make it work.
		 */
		$msg = __( '<strong>The Pardot plugin is activated (yay!)</strong>, but it needs some quick %s to start working correctly.', 'pardot' );
		$msg = sprintf( $msg, self::get_admin_page_link( array( 'link_text' => __( 'configuration', 'pardot' ) ) ) );
		echo "<div class=\"notice notice-success\"><p>{$msg}</p></div>";
	}

	/**
	 * Display a admin notice.
	 *
	 * @param string $message
	 * @param string $type One of 'warning', 'info', 'error', or 'success'.
	 * @param bool   $dismissible Whether the notice is dismissible.
	 */
	public static function notice( string $message, string $type = 'warning', bool $dismissible = true ) {
		if ( in_array( $type, array( 'warning', 'info', 'error', 'success' ) ) ) {
			$notice = function () use ( $message, $type, $dismissible ) {
				printf( '<div class="notice notice-%s %s"><p>%s</p></div>', $type, $dismissible ? 'is-dismissible' : '', $message );
			};
			add_action( 'admin_notices', $notice );
		} else {
			throw new Error( 'Incorrect notice type' );
		}
	}

	public function menu_page_callback() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get the logo and alt text.
		$logo_url = plugins_url( '/images/pardot-logo.png', dirname( __FILE__ ) );
		$alt_text = __( 'Pardot, a Salesforce Company', 'pardot' );

		// Get the active tab.
		$default_tab = Pardot_API::$is_authenticated ? 'general' : 'account';
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $default_tab;

		?>
		<header>
			<img src="<?php echo $logo_url; ?>" alt="<?php echo $alt_text; ?>" width="181" height="71" />
		</header>

		<nav class="nav-tab-wrapper">
			<a href="?page=<?php echo Pardot::$plugin_data['TextDomain']; ?>&tab=general" class="nav-tab<?php if ( 'general' === $tab ) : ?> nav-tab-active<?php endif; ?>">General</a>
			<a href="?page=<?php echo Pardot::$plugin_data['TextDomain']; ?>&tab=account" class="nav-tab<?php if ( 'account' === $tab ) : ?> nav-tab-active<?php endif; ?>">Account</a>
			<a href="https://help.salesforce.com/s/articleView?id=000386189&type=1" target="_blank">Contact Pardot Support<span class="dashicons dashicons-external" aria-hidden="true" style="font-size:0.8rem;text-decoration:none;"></span></a>
		</nav>

		<div class="tab-content">
		<?php
		switch ( $tab ) {
			case 'account':
				// Attempt authentication.
				if ( isset( $_GET['settings-updated'] ) && Pardot_setting::get( 'consumer_key' ) ) {
					Pardot_API::get_instance()->authorize( Pardot_setting::get( 'consumer_key' ), get_admin_url(\null, 'options-general.php?page=' . Pardot::$plugin_data['TextDomain'] . '&tab=account') );
				}
				?>
				<form action="options.php" method="post" class="<?php echo Pardot::$plugin_data['TextDomain'] . '-' . $tab; ?>">
					<?php
					settings_fields( Pardot::$plugin_data['TextDomain'] );
					do_settings_sections( Pardot::$plugin_data['TextDomain'] . '_' . $tab );
					submit_button( 'Connect' );
					?>
				</form>
				<?php
				break;

			default:
				/*
				 * Check if the user have submitted the settings.
				 * WordPress will add the "settings-updated" $_GET parameter to the url
				 */
				if ( isset( $_GET['settings-updated'] ) ) {
					// Add settings saved message with the class of "updated".
					add_settings_error( Pardot::$plugin_data['TextDomain'] . '_messages', Pardot::$plugin_data['TextDomain'] . '_message', __( ucwords( $tab ) . ' Saved', Pardot::$plugin_data['TextDomain'] ), 'updated' );
				}
				?>
				<form action="options.php" method="post" class="<?php echo Pardot::$plugin_data['TextDomain'] . '-' . $tab; ?>">
					<?php
					settings_fields( Pardot::$plugin_data['TextDomain'] );
					do_settings_sections( Pardot::$plugin_data['TextDomain'] . '_' . $tab );
					submit_button( 'Save' );
					?>
				</form>
				<?php
				break;
		}
	}

	public function load_menu_screen() {
		// Get the active tab
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'account';
	}

	public function general_section_callback() {
		// echo '<p>' . __( 'General settings', 'pardot' ) . '</p>';
	}

	public function account_section_callback() {
		echo '<p>' . __( 'The consumer key and secret are obtained after creating a Connected App in', 'pardot' ) . '<a href="' . self::APP_MANAGER_URL . '" target="_blank"> ' . __( 'App Manager', 'pardot' ) . '<span class="dashicons dashicons-external" aria-hidden="true" style="font-size:0.8rem;text-decoration:none;"></span></a></a></p>';
	}

	public function campaign_select_callback() {
		$options = '';
		// foreach ( Pardot_API::get_campaigns() as $c) {
		// $options = $options . '';
		// }
		printf( '<select>%s</select>', $options );
	}

	/**
	 * Encodes plain text into base64 for URLs
	 *
	 * @param $plainText
	 * @return string
	 *
	 * @since 1.5.0
	 */
	function base64url_encode( $plainText ) {
		$base64    = base64_encode( $plainText );
		$base64    = trim( $base64, '=' );
		$base64url = strtr( $base64, '+/', '-_' );
		return ( $base64url );
	}

	/**
	 * Saves a new code_verifier to WP Options
	 *
	 * @since 1.5.0
	 */
	function create_code_verifier() {
		$random   = wp_create_nonce();
		$verifier = self::base64url_encode( pack( 'H*', $random ) );
		update_option( self::$CODE_VERIFIER, $verifier );
	}

	/**
	 * Load scripts and styles for the admin page.
	 */
	public function enqueue_admin( $hook ) {

		// Don't load if we are not on the correct admin page.
		if ( $hook !== self::$submenu_page_hook ) return;

		// Load scripts/styles for this page.
		wp_enqueue_style( Pardot::$plugin_data['TextDomain'] . '-admin', plugins_url( '/css/admin.css', PARDOT_PLUGIN_FILE ), array(), Pardot::$plugin_data['Version'] );
		wp_enqueue_script( Pardot::$plugin_data['TextDomain'] . '-admin', plugins_url( '/js/admin.js', PARDOT_PLUGIN_FILE ), array(), Pardot::$plugin_data['Version'], true );

		// Localize the code_challenge for the admin.js script to use.
		$code_challenge = self::base64url_encode( pack( 'H*', hash( 'sha256', get_option( self::$CODE_VERIFIER ) ) ) );
		wp_localize_script( Pardot::$plugin_data['TextDomain'] . '-admin', 'Pardot', array( 'challenge' => $code_challenge ) );
	}

	/**
	 * Configure the settings page for the Pardot Plugin if we are currently on the settings page.
	 */
	function admin_init() {

		// If we are not on the admin page for this plugin, bail.
		if ( ! self::is_admin_page() ) {
			return;
		}

		// Create settings sections
		foreach ( self::$settings_sections as $section ) {
			add_settings_section( Pardot::$plugin_data['TextDomain'] . '_section_' . $section['slug'], __( $section['name'], Pardot::$plugin_data['TextDomain'] ), array( $this, $section['slug'] . '_section_callback' ), Pardot::$plugin_data['TextDomain'] . '_' . $section['tab'] );
		}

		// Create settings
		foreach ( self::$settings as $setting ) {
			$setting->init();
		}

		// Checks if authorization token request failed
		if ( $error_description = isset( $_GET['error_description'] ) ) {
			add_settings_error( self::$settings['auth_status']->slug, 'update_settings', 'Failed to authenticate.  Please check your credentials again. (' . $error_description . ')', 'error' );
			settings_errors( 'update_settings' );
		}

		/*
		 * Does not create new code verifier when 'code' query string present.
		 * First needs to verify the code challenge passed during the authorization code process.
		 */
		if ( ! isset( $_GET['code'] ) ) {
			$this->create_code_verifier();
		}

		if ( isset( $_GET['code'] ) && isset( $_GET['status'] ) && $_GET['status'] == 'success' && ! Pardot_API::$is_authenticated ) {
			$url  = 'https://login.salesforce.com/services/oauth2/token';
			$body = array(
				'grant_type'    => 'authorization_code',
				'code'          => $_GET['code'],
				'client_id'     => Pardot_Setting::get( 'consumer_key' ),
				'client_secret' => self::decrypt_or_original( Pardot_Setting::get( 'consumer_secret' ) ),
				'redirect_uri'  => ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) ? admin_url( 'options-general.php?page=pardot' ) : admin_url( 'options-general.php?page=pardot', 'https' ),
				'code_verifier' => get_option( self::$CODE_VERIFIER ),
			);

			$args = array(
				'body'        => $body,
				'timeout'     => '5',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array( 'Content-type: application/json' ),
				'cookies'     => array(),
			);

			$response = wp_remote_post( $url, $args );

			$response = json_decode( wp_remote_retrieve_body( $response ) );

			if ( isset( $response->{'error'} ) ) {
				add_settings_error( self::$settings['access_token']['slug'], 'update_settings', 'Failed to authenticate!  Please check your credentials again. (' . $response->{'error'} . ':' . $response->{'error_description'} . ')', 'error' );
				settings_errors( 'update_settings' );
			}


			/* if (isset($response->{'access_token'})) {
				self::set_setting('api_key', $response->{'access_token'});
			}

			if (isset($response->{'refresh_token'})) {
				self::set_setting('refresh_token', $response->{'refresh_token'});
			} // Error message to remind user that they should have enabled refresh_token scope for auto-reauth
			elseif ($body['grant_type'] != 'refresh_token' && !isset($response->{'error'}) && !isset($response->{'refresh_token'})) {
				add_settings_error(self::$OPTION_GROUP, 'update_settings', 'Make sure you enable the refresh_token scope if you want to be want to be reauthenticated automatically.', 'error');
				settings_errors('update_settings');
			} */

			// After using the code_verifier is used, delete it
			delete_option( self::$CODE_VERIFIER );
		}

		/* if ( Pardot_API::$is_authenticated ) {
			Pardot_API::get_instance()->get_account();

			$api_error = $this->retrieve_api_error();

			if ( ! empty( $api_error ) ) {
				$msg = sprintf( esc_html_x( 'Error: %s', 'pardot' ), "<i>$api_error</i>" );
				add_settings_error( self::$OPTION_GROUP, 'update_settings', $msg );
			}
		} */
	}

	/**
	 * Get an array with all the settings fields with all empty values.
	 *
	 * Get the setting field names and then set each array key to an
	 * empty string ('') so we will have initialized all potential elements.
	 *
	 * @return array
	 */
	static function get_empty_settings() {
		static $empty_settings;
		if ( ! isset( $empty_settings ) ) {
			/**
			 * First time in, create an array with all expected keys and with empty string values.
			 */
			$empty_settings                  = array_fill_keys( array_keys( self::$FIELDS ), '' );
			$empty_settings['api_key']       = '';
			$empty_settings['refresh_token'] = '';
		}
		return $empty_settings;
	}

	/**
	 * Sanitize Settings Account.
	 *
	 * @param array $dirty List of values that may be settings.
	 * @return array Sanitized array of all recognized settings.
	 *
	 * @since 1.0.0
	 */
	function sanitize_fields( $dirty ) {
		// Nothing passed? Then nothing to sanitize.
		if ( empty( $dirty ) ) {
			return false;
		}

		/*
		 * Get the setting field names and add 'action' and 'status',
		 * then set each array key to an empty string ('') so we
		 * will have initialized all potential array elements.
		 */
		$clean = self::get_empty_settings();

		if ( isset( $_POST['reset'] ) ) {
			foreach ( self::$settings as $setting ) {
				Pardot_Setting::clear( $setting->slug );
			}

			wp_safe_redirect( admin_url( 'options-general.php?page=pardot' ) );
			exit;
		}

		if ( isset( $_POST['clear'] ) ) {
			Pardot_Plugin::clear_cache();
			add_settings_error( self::$settings[''], 'reset_settings', __( 'The cache has been cleared!', 'pardot' ), 'updated' );
		}

		// Use existing client_secret if the setting has not been changed.
		if ( empty( $dirty['client_secret'] ) ) {
			$dirty['client_secret'] = Pardot_Setting::get( 'consumer_secret' );
		}

		// Use existing api_key if the setting has not been changed.
		if ( empty( $dirty['api_key'] ) ) {
			$dirty['api_key'] = Pardot_Setting::get( 'access_token' );
		}

		// Use existing refresh_token if the setting has not been changed.
		if ( empty( $dirty['refresh_token'] ) ) {
			$dirty['refresh_token'] = Pardot_Setting::get( 'refresh_token' );
		}

		// Sanitize each of the fields values.
		foreach ( $clean as $name => $value ) {
			if ( isset( $dirty[ $name ] ) ) {
				$clean[ $name ] = trim( esc_attr( $dirty[ $name ] ) );
			}
		}

		// Call the Pardot API to attempt to authenticate if there isn't an api_key.
		if ( ! $clean['access_token'] && ! Pardot_API::authenticate( $clean ) ) {
			if ( ! $clean['client_id'] ) {
				$msg = __( 'Please check the Consumer Key field below and click "Save Settings" again.', 'pardot' );
				add_settings_error( self::$OPTION_GROUP, 'update_settings', $msg );
			} elseif ( ! $clean['client_secret'] ) {
				$msg = __( 'Please check the Consumer Secret field below and click "Save Settings" again.', 'pardot' );
				add_settings_error( self::$OPTION_GROUP, 'update_settings', $msg );
			} elseif ( ! $clean['business_unit_id'] ) {
				$msg = __( 'Please check the Business Unit ID field below and click "Save Settings" again.', 'pardot' );
				add_settings_error( self::$OPTION_GROUP, 'update_settings', $msg );
			}

			Pardot_API::authenticate( $clean );
		} else {
			if ( ! self::$showed_auth_notice ) {
				$msg = __( 'Authentication successful. Settings saved.', 'pardot' );
				add_settings_error( self::$OPTION_GROUP, 'update_settings', $msg, 'updated' );

				/**
				 * Capture the api_key so we can save to the wp_options table.
				 */
				$clean['access_token'] = Pardot_API::get_access_token();

				self::$showed_auth_notice = true;
			}
		}

		// Add a filter to encrypt credentials.
		add_filter( 'pre_update_option_pardot_settings', array( $this, 'pre_update_option_pardot_settings' ), 10, 2 );

		return $clean;
	}

	/**
	 * Returns the error message provided in the last API response or null if
	 * an error was not supplied.
	 *
	 * @return string|null
	 * @since 1.4.8
	 */
	private function retrieve_api_error() {
		 $api_error = null;

		if ( ! empty( self::$api->error ) ) {
			// Get the raw error text from the (SimpleXMLElement) error object
			$api_error = esc_html( trim( (string) self::$api->error ) );

			// Convert any URLs contained within into actual links
			$api_error = make_clickable( $api_error );
		}

		return empty( $api_error ) ? null : $api_error;
	}

	/**
	 * Extract the auth args from the passed array.
	 *
	 * @param array $auth Values 'client_id', 'client_secret', 'business_unit_id', 'refresh_token', and 'api_key' supported.
	 * @return array Contains 'client_id', 'client_secret', 'business_unit_id','refresh_token' and 'api_key' if they existing as keys in $auth.
	 */
	static function extract_auth_args( $auth = array() ) {
		return array_intersect_key( $auth, array_flip( array( 'access_token', 'consumer_key', 'consumer_secret', 'business_unit_id', 'refresh_token' ) ) );
	}

	/**
	 * Clean the Pardot settings before saving to wp_options
	 *
	 * @param array $new_options The settings as they user edited them.
	 * @param array $old_options The settings as they were previously in the database.
	 * @return mixed The settings after we removed 'submit' and 'reset'
	 *
	 * @since 1.0.0
	 */
	function pre_update_option_pardot_settings( $new_options, $old_options ) {

		// We don't need to call this filter again on this page load.
		remove_filter( 'pre_update_option_pardot_settings', array( $this, 'pre_update_option_pardot_settings' ) );

		// Trim whitespace
		$new_options['client_id']        = trim( $new_options['client_id'] );
		$new_options['client_secret']    = trim( $new_options['client_secret'] );
		$new_options['business_unit_id'] = trim( $new_options['business_unit_id'] );

		if ( $new_options['client_secret'] != null ) {
			$new_options['client_secret'] = self::pardot_encrypt( $new_options['client_secret'] );
		}

		if ( $new_options['api_key'] != null ) {
			$new_options['api_key'] = self::pardot_encrypt( $new_options['api_key'] );
		}

		if ( $new_options['refresh_token'] != null ) {
			$new_options['refresh_token'] = self::pardot_encrypt( $new_options['refresh_token'] );
		}

		return $new_options;
	}

	/**
	 * Displays Section text for the Settings API
	 *
	 * @since 1.0.0
	 */
	function user_account_section() {
		$msg = __( 'Use your Salesforce Connected App information to securely connect (you\'ll only need to do this once).', 'pardot' );
		echo "<span id=\"instructions\">{$msg}</span>";
	}

	/**
	 * Returns HTML input name for a raw field name
	 *
	 * The Settings API want HTML input names in the form "pardot_settings[client_id]" instead of just "client_id".
	 *
	 * @param string $field_name
	 * @return string
	 *
	 * @since 1.0.0
	 */
	private function _get_html_name( $field_name ) {
		return self::$OPTION_GROUP . "[{$field_name}]";
	}

	/**
	 * Displays the Consumer Key field for the Settings API
	 *
	 * @since 1.5.0
	 */
	function client_id_field() {
		$client_id = Pardot_Setting::get( 'consumer_key' );
		$html_name = $this->_get_html_name( 'consumer_key' );
		$msg       = __( 'Consumer Key and Consumer Secret are obtained after creating a connected app in <a href="%s" target="_blank">App Manager</a>.', 'pardot' );
		$msg       = sprintf( $msg, self::APP_MANAGER_URL );

		$html = <<<HTML
<div id="client-id-wrap">
	<input type="text" size="30" id="client-id" name="{$html_name}" value="{$client_id}" />
	<p>{$msg}</p>
</div>
HTML;
		echo $html;
	}

	/**
	 * Displays the Consumer Secret field for the Settings API
	 *
	 * @since 1.5.0
	 */
	public function client_secret_field() {
		/*
		 * Grab the length of the real client_secret and turn it into a placeholder string that looks like it is filled
		 * in whenever a client_secret is set.
		 */
		$secretLength = strlen( Pardot_Setting::get( 'consumer_secret' ) );

		/*
		 * Set client_secret length to some arbitrary amount iff there is a set client_secret already so that it shows
		 * that the client_secret is set already without disclosing the exact number of characters in the client_secret
		 */
		$secretLength      = $secretLength > 0 ? 64 : 0;
		$secretPlaceholder = str_repeat( '&#8226;', $secretLength );

		$html_name = $this->_get_html_name( 'consumer_secret' );
		$html      = <<<HTML
<div id="client-secret-wrap">
	<input type="password" size="30" id="client-secret" name="{$html_name}" placeholder="{$secretPlaceholder}" />
</div>
HTML;
		echo $html;
	}

	/**
	 * Displays the Business Unit ID Secret field for the Settings API
	 *
	 * @since 1.5.0
	 */
	public function business_unit_id_field() {
		 $business_unit_id = Pardot_Setting::get( 'business_unit_id' );
		$html_name         = $this->_get_html_name( 'business_unit_id' );
		$msg               = __( 'Find your Pardot Business Unit ID in <a href="%s" target="_blank">Pardot Account Setup</a>.', 'pardot' );
		$msg               = sprintf( $msg, self::BUSINESS_UNIT_ID_URL );

		$html = <<<HTML
<div id="business-unit-id-wrap">
	<input type="text" size="30" id="business-unit-id" name="{$html_name}" value="{$business_unit_id}" />
	<p>{$msg}</p>
</div>
HTML;
		echo $html;
	}

	/**
	 * Displays the Campaign drop-down field for the Settings API
	 *
	 * @since 1.0.0Select
	 */
	public function campaign_field()
	{
		$campaigns = null;

		if ( Pardot_API::$is_authenticated ) {
			$campaigns = Pardot_API::get_campaigns();
		}

		if (!$campaigns) {
			$msg = __('These will show up once you\'re connected.', 'pardot');
			echo "<p>{$msg}</p>";
		} else {
			$label = __('Select Campaign', 'pardot');
			$html_name = $this->_get_html_name('campaign');
			$html = [];
			$html[] = <<<HTML
<div id="campaign-wrap">
<select id="campaign" name="{$html_name}">
<option selected="selected" value="">{$label}</option>
HTML;

			$selected_value = Pardot_Setting::get('campaign');

			foreach ($campaigns as $campaign => $data) {

				$campaign_id = esc_attr($campaign);
				$selected = selected($selected_value, $campaign_id, false);

				// A fallback in the rare case of a malformed/empty stdClass of campaign data.
				$campaign_name = sprintf(__('Campaign ID: %s', 'pardot'), $campaign_id);

				if (isset($data->name) && is_string($data->name)) {
					$campaign_name = esc_html($data->name);
				}

				$html[] = "<option {$selected} value=\"{$campaign_id}\">{$campaign_name}</option>";
			}

			$html[] = '</select></div>';
			echo implode('', $html);
		}
	}

	/**
	 * Displays the API Version drop-down field for the Settings API
	 *
	 * @since 1.4.1
	 */
	public function version_field() {
		$version   = Pardot_Setting::get( 'api_version' );
		$html_name = $this->_get_html_name( 'api_version' );
		$html      = '<div id="version-wrap"><select id="version" name="' . $html_name . '">';
		$html     .= '<option';
		if ( $version === '3' ) {
			$html .= ' selected="selected"';
		}
		$html .= ' value="3">3</option>';
		$html .= '<option';
		if ( $version === '4' ) {
			$html .= ' selected="selected"';
		}
		$html .= ' value="4">4</option>';
		$html .= '<option';
		echo $html;
	}

	/**
	 * Displays the HTTPS-only checkbox for the Settings API
	 *
	 * @since 1.4
	 */
	public function https_field() {
		$https = Pardot_Setting::get( 'always_use_https' );
		if ( $https ) {
			$https = 'checked';
		}
		$html_name = $this->_get_html_name( 'always_use_https' );
		$html      = <<<HTML
<input type="checkbox" id="https" name="{$html_name}" {$https} />
HTML;
		echo $html;
	}

	/**
	 * Displays the Submit button for the Settings API
	 *
	 * @since 1.0.0
	 */
	public function submit_field() {
		$value           = __( 'Save Settings', 'pardot' );
		$valuecache      = __( 'Clear Cache', 'pardot' );
		$valuereset      = __( 'Reset All Settings', 'pardot' );
		$msgResetConfirm = __( 'This will remove all your Pardot account information from the database. Click OK to proceed.', 'pardot' );
		$msgResetTrue    = __( 'Your Pardot settings have been reset.', 'pardot' );
		$html            = <<<HTML
<script>
function resetSettingsClick() {
	if (confirm('{$msgResetConfirm}')) {
		alert("{$msgResetTrue}");
		document.getElementById("resetSettings").click();
	}
}
</script>

<input type="submit" class="button-primary" name="save" value="{$value}" />
<input type="submit" class="button-secondary" name="clear" value="{$valuecache}" style="margin-left: 50px;" />
<div onclick="resetSettingsClick()" class="button-secondary">{$valuereset}</div>
<input type="submit" name="reset" style="display: none" id="resetSettings"/>

HTML;
		echo $html;
	}

	/**
	 * Encrypts with a bit more complexity
	 * returns false if the string could not be encrypted (cases where encryption fails, or Sodium or OpenSSL are not present in PHP).
	 *
	 * @since 1.1.2
	 */
	public static function pardot_encrypt( $input_string ) {
		$crypto = new PardotCrypto();
		return $crypto->encrypt( $input_string );
	}


	/**
	 * Decrypts with a bit more complexity.
	 *
	 * In situations where the string could not be decrypted boolean false will
	 * be returned. This could include scenarios where the string has already
	 * been decrypted.
	 *
	 * @return string|bool
	 * @throws Exception
	 * @since 1.1.2
	 */
	public static function pardot_decrypt( $encrypted_input_string ) {
		$crypto = new PardotCrypto();
		return $crypto->decrypt( $encrypted_input_string );
	}


	/**
	 * Returns the decrypted form of the input string or if decryption fails it
	 * will pass back the input string unmodified.
	 *
	 * @param string        $input_string
	 * @param string string $key
	 *
	 * @return string
	 * @see   self::pardot_decrypt()
	 *
	 * @since 1.4.6
	 */
	public static function decrypt_or_original( $input_string ) {
		$decrypted_pass = self::pardot_decrypt( $input_string );

		if (
			! empty( $decrypted_pass )
			&& $decrypted_pass !== $input_string
			&& ctype_print( $decrypted_pass )
		) {
			return $decrypted_pass;
		}

		return $input_string;
	}

	/**
	 * Return list of Pardot plugin settings
	 *
	 * @static
	 * @return array List of settings
	 *
	 * @since 1.0.0
	 */
	public static function get_settings() {
		// Grab the (expected) array of settings
		$settings = get_option( self::$OPTION_GROUP );

		if ( empty( $settings ) ) {
			// If it's empty, make sure it's an empty array.
			$settings = array();
		}

		if ( isset( $settings['client_secret'] ) && ! empty( $settings['client_secret'] ) ) {
			$decrypted_token = self::pardot_decrypt( $settings['client_secret'] );

			if ( $decrypted_token !== $settings['client_secret'] && ctype_print( $decrypted_token ) ) {
				$settings['client_secret'] = $decrypted_token;
			}
		}

		if ( isset( $settings['api_key'] ) && ! empty( $settings['api_key'] ) ) {
			$decrypted_token = self::pardot_decrypt( $settings['api_key'] );

			if ( $decrypted_token !== $settings['api_key'] && ctype_print( $decrypted_token ) ) {
				$settings['api_key'] = $decrypted_token;
			}
		}

		if ( isset( $settings['refresh_token'] ) && ! empty( $settings['refresh_token'] ) ) {
			$decrypted_token = self::pardot_decrypt( $settings['refresh_token'] );

			if ( $decrypted_token !== $settings['refresh_token'] && ctype_print( $decrypted_token ) ) {
				$settings['refresh_token'] = $decrypted_token;
			}
		}

		// Merge in the empty settings to make sure all expected setting keys are in returned array.
		return array_merge( self::get_empty_settings(), $settings );
	}

	/**
	 * Return an individual Pardot plugin settings
	 *
	 * @static
	 * @param string $key Identifies a setting
	 * @return mixed|null Value of the setting
	 *
	 * @since 1.0.0
	 */
	public static function get_setting( $key ) {
		$settings = self::get_settings();
		$value    = null;

		if ( isset( $settings[ $key ] ) ) {
			$value = $settings[ $key ];
		}

		/**
		 * Provides an opportunity to intercept and override Pardot settings.
		 *
		 * @param mixed $value
		 * @param string $key
		 * @since 1.4.6
		 */
		return apply_filters( 'pardot_get_setting', $value, $key );
	}

	/**
	 * Get the URL for the Pardot plugin settings page in the admin.
	 *
	 * @static
	 * @return string URL for the settings page.
	 *
	 * @since 1.0.0
	 */
	public static function get_admin_page_url() {
		return admin_url( 'options-general.php?page=' . self::$settings_page );
	}

	/**
	 * Simple function to return an HTML link to the admin URL for Settings
	 *
	 * @param array $args Options for changing the link: onclick, target, and/or link_text.
	 * @return string HTML <a> link to the admin page
	 *
	 * @since 1.0.0
	 */
	public static function get_admin_page_link( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'onclick'   => false,
				'target'    => false,
				'link_text' => false,
			)
		);

		$onclick   = $args['onclick'] ? " onclick=\"{$args['onclick']}\"" : '';
		$target    = $args['target'] ? " target=\"{$args['target']}\"" : '';
		$link_text = $args['link_text'] ? $args['link_text'] : __( 'Settings', 'pardot' );

		return "<a{$target}{$onclick} href=\"" . self::get_admin_page_url() . "\">{$link_text}</a>";
	}

}

add_action( 'plugins_loaded', array( 'Pardot_Settings', 'get_instance' ) );
