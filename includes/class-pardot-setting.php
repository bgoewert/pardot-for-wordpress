<?php
/**
 * Contains the setting class.
 *
 * @package Pardot
 */

/**
 * Setting class
 */
class Pardot_Setting
{
	/**
	 * The name of the setting. This will also be used to generate a slug using the plugin's text domain as a prefix.
	 *
	 * @var string $name
	 */
	public string $name;

	/**
	 * The machine name for the setting.
	 *
	 * @var string $slug
	 */
	public string $slug;

	/**
	 * Display name for the setting.
	 *
	 * @var string $title
	 */
	public string $title;

	/**
	 * The setting's default value.
	 *
	 * @var mixed $default_value
	 */
	public mixed $default_value;

	/**
	 * @var string $description
	 */
	public string $description;

	/**
	 * The callback to use to display the setting.
	 *
	 * @var callable $callback
	 */
	protected $callback;

	private $option_only;

	/**
	 * @var array $plugin_data Plugin header data from the plugin file.
	 */
	private static $plugin_data;

	public function __construct( $name, $option_only=false, $default_value=\null, $title='', $type='', $description='', $tab=\null, $section=\null, $callback=\null)
	{
		self::$plugin_data = get_plugin_data( PARDOT_PLUGIN_FILE );

		$this->option_only = $option_only;
		$this->name = $name;
		$this->slug = self::$plugin_data['TextDomain'] . '_' . $this->name;
		$this->default_value = $default_value;

		// If this is just an option, no need for anything specific to a setting.
		if ( ! $option_only ) {
			$this->title = $title ?? preg_replace( '[-_]', ' ', ucwords( $name ) );
			$this->type = $type;
			$this->page = $tab;
			$this->section = preg_replace( '/\s+/', '_', strtolower( $section ) );
			$this->description = $description;
			$this->callback = $callback;

			if ( $this->callback === null ) {
				switch ( $this->type ) {
					case 'checkbox':
						$this->callback = array( $this, 'init_checkbox' );
						if ( $this->default_value && $this->default_value !== 'on' ) $this->default_value = 'on';
						break;

					case 'status':
						$this->callback = array( $this, 'init_status' );
						break;

					case 'select':
						$this->callback = array( $this, 'init_select' );
						break;

					default:
						$this->callback = array( $this, 'init_type' );
						break;
				}
			}
		}
	}

	public function init()
	{
		$this->add_setting();
	}

	private function add_setting()
	{
		add_option( $this->slug, $this->default_value );

		// No need to register a setting if this is only supposed to be an option.
		if ( ! $this->option_only ) {
			register_setting( self::$plugin_data['TextDomain'] . '_' . $this->page, $this->slug, ['default' => $this->default_value] );
			add_settings_field( $this->slug . '_field', $this->title, $this->callback, self::$plugin_data['TextDomain'] . '_' . $this->page, self::$plugin_data['TextDomain'] . '_section_' . $this->section );
		}
	}

	public static function get($setting)
	{
		if ( strpos( $setting, self::$plugin_data['TextDomain'] ) === false )
		{
			return get_option( self::$plugin_data['TextDomain'] . '_' . $setting );
		}
		return get_option( $setting );
	}

	public static function set( $setting, $value )
	{
		if (strpos( $setting, self::$plugin_data['TextDomain']) === false )
		{
			return update_option( self::$plugin_data['TextDomain'] . '_' . $setting, $value );
		}
		return update_option( $setting, $value );
	}

	public static function clear( $setting )
	{
		if ( false === strpos( $setting, self::$plugin_data['TextDomain'] ) )
		{
			return update_option( self::$plugin_data['TextDomain'] . '_' . $setting, null );
		}
		return update_option( $setting, null );
	}

	public function init_type(){
		printf( '<input type="%s" name="%s" value="%s">', $this->type, $this->slug, self::get( $this->slug ) ?? $this->default_value );
		if ( $this->description) printf('<p class="description">%s</p>', $this->description );
	}

	public function init_checkbox()
	{
		printf('<input type="checkbox" name="%s" %s>', $this->slug, checked(Pardot_Setting::get($this->name), 'on', false));
		if ($this->description) printf('<p class="description">%s</p>', $this->description);
	}

	public function init_status()
	{
		$status = Pardot_Setting::get( $this->name );
		$classes = array(
			( $status ? 'success' : 'failure'),
		);
		printf( '<span class="%s">%s</span>', join(' ', $classes) , ($status ? 'Authenticated' : 'Not Authenticated') );
	}
}

?>
