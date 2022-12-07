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
 *
 * @author Mike Schinkel <mike@newclarity.net>
 * @version 1.0.0
 *
 * @author Brennan Goewert <brennan@goewert.me>
 * @version 2.0.0
 */
class Pardot_API
{

	/**
	 *
	 */
	private static $_instance;

	/**
	 * API Version. Defaults to v5.
	 * @var int
	 * @since 2.0.0
	 * @see https://developer.salesforce.com/docs/marketing/pardot/guide/overview.html#pardot-api-versions
	 */
	static int $_api_version = 5;

	/**
	 * API path
	 * @var string
	 * @since 2.0.0
	 */
	static string $base_path = '/api/';

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
	 * Constructor class for `Pardot_API`.
	 * @param array $auth_keys Array of auth key(s) for authentication.
	 */
	public function authorize( string $client_id, string $business_unit_id, string $response_type = 'code' )
	{

		$headers = array(
			'client' => 'Bearer ' . self::$access_token,
			'Pardot-Business-Unit' => $business_unit_id
		);


		$body = array(
			'offset' => $offset,
		);

		wp_remote_get();

	}

	/**
	 * Verify that multiple keys exist.
	 * @since 2.0.0
	 */
	private static function array_keys_exists( array $keys, array $array ) {
		return ! array_diff_key( array_flip( $keys ), $array);
	}

	/**
	 * Return an instance of the API.
	 *
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
		self::$_api_version = $api_version;
	}


}
