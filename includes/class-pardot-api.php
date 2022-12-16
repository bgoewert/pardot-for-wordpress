<?php
/**
 * File containing the Pardot API class.
 *
 * @package Pardot
 */

/**
 * PHP class for interacting with the Pardot API.
 *
 * Developed for the Pardot WordPress Plugin.
 *
 * Notes:
 *
 * - `$URL_PATH_TEMPLATE` and `$LOGIN_URL_PATH_TEMPLATE` are `private static` rather than `const` because `const` cannot be made `private`.
 * Both of these are "convenience" constants to ensure they are placed close to the top of the file, but their
 * architecture is not robust enough to expose for external use as an update would create a breaking change.
 *
 * - Requires WordPress API because of its use of `wp_remote_request()`, `wp_remote_retrieve_response_code()` and
 * `wp_remote_retrieve_body()` but otherwise independent of WordPress. However, this could be made to be a standalone if these functions
 * replaced with CURL equivalents.
 */
class Pardot_API
{

	/**
	 * Contains the singleton instance of this class.
	 * @var Pardot_API
	 */
	private static $_instance;

	/**
	 * API Version. Defaults to v5.
	 * @var int
	 * @see https://developer.salesforce.com/docs/marketing/pardot/guide/overview.html#pardot-api-versions
	 */
	static int $api_version = 5;

	/**
	 * Base API path.
	 * @var string
	 */
	static string $api_path_base = '/api';

	/**
	 * Defines the API path to forms.
	 * @var string
	 */
	static string $api_path_forms = '/form';

	/**
	 * Defines the API path to dynamic content.
	 * @var string
	 */
	static string $api_path_dynamic_content = '/content';

	/**
	 * @var string Defines the account type that we are connecting to.
	 * @since 2.0.0
	 */
	static string $account_type = 'production';

	/**
	 * @var array Defines the types of accounts.
	 * @since 2.0.0
	 */
	static array $account_types = array(
		'production',
		'developer',
		'sandbox'
	);

	/**
	 * The consumer key provided by the Salesforce Connected App. This is the `client_id` used to retrieve an access token.
	 * @var string
	 * @since 2.0.0
	 */
	static string $consumer_key;

	/**
	 * The consumer secret provided by the Salesforce Connected App. This is the `client_secret` used to retrieve an access token.
	 * @var string
	 * @since 2.0.0
	 */
	private static string $_consumer_secret;

	/**
	 * The API key that is returned after authentication.
	 * @var string
	 * @since 2.0.0
	 */
	protected static string $access_token;

	/**
	 * State of authentication.
	 * @var bool $is_authenticated
	 */
	public static bool $is_authenticated = false;

	/**
	 * Salesforce login URL used for authentication.
	 * @var string
	 */
	const SALESFORCE_LOGIN_DOMAIN = 'login.salesforce.com';

	/**
	 * Salesforce sandbox URL used for authentication and the API.
	 * @var string
	 */
	const SALESFORCE_SANDBOX_DOMAIN = 'test.salesforce.com';

	/**
	 * Pardot API URL
	 * @var string
	 */
	const PARDOT_DOMAIN = 'pi.pardot.com';

	/**
	 * Pardot Demo URL
	 * @var string
	 */
	const PARDOT_DEMO_DOMAIN = 'pi.demo.pardot.com';

	/**
	 * OAuth path that is used for retrieving the authorization token.
	 * @var string
	 */
	const SALESFORCE_AUTHORIZE_PATH = '/services/oauth2/authorize';

	/**
	 * Constructor class for `Pardot_API`.
	 * @param string $client_id The consumer_key given by Salesforce.
	 * @param string
	 */
	public function authorize( string $client_id, string $redirect_uri, string $response_type = 'code' )
	{

		$params = array(
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => $response_type
		);

		$url = ( Pardot_Setting::get( 'sandbox' ) ? self::SALESFORCE_SANDBOX_DOMAIN : self::SALESFORCE_LOGIN_DOMAIN ) . self::SALESFORCE_AUTHORIZE_PATH;

		wp_safe_remote_get( add_query_arg( $params, $url ) );

	}

	/**
	 * Verify that multiple keys exist.
	 */
	private static function array_keys_exists( array $keys, array $array ) {
		return ! array_diff_key( array_flip( $keys ), $array);
	}

	/**
	 * Return an instance of the API.
	 * @return Pardot_API
	 */
	public static function get_instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	/**
	 * Set the API version.
	 *
	 * @param int $api_version The API version to use.
	 * @since 2.0.0
	 */
	static function set_api_version( int $api_version ) {
		self::$api_version = $api_version;
	}


}
