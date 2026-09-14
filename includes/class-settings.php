<?php
/**
 * Global settings page.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
final class Settings {

	const OPTION = 'schemagic_settings';
	const GROUP  = 'schemagic';
	const PAGE   = 'schemagic-settings';

	/**
	 * Hook suffix returned by add_submenu_page().
	 *
	 * @var string
	 */
	private static $hook_suffix = '';

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( Plugin::class, 'capability' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'output_enabled' => 1,
			'org_name'       => '',
			'logo'           => 0,
			'same_as'        => array(),
			'delete_data'    => 0,
		);
	}

	/**
	 * Saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Settings page URL.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'edit.php?post_type=' . Location_Post_Type::POST_TYPE . '&page=' . self::PAGE );
	}

	/**
	 * Screen ID of the settings page.
	 *
	 * @return string
	 */
	public static function screen_id() {
		return '' !== self::$hook_suffix ? self::$hook_suffix : Location_Post_Type::POST_TYPE . '_page_' . self::PAGE;
	}

	/**
	 * Field definitions for the settings page, in the same format as Fields::all().
	 *
	 * @return array<string, array>
	 */
	public static function fields() {
		return array(
			'output_enabled' => array(
				'label'       => __( 'Schema output', 'schemagic' ),
				'section'     => 'output',
				'type'        => 'checkbox',
				'description' => __( 'Add location schema to the site. Turn off to pause output without losing any data.', 'schemagic' ),
			),
			'org_name'       => array(
				'label'       => __( 'Organization name', 'schemagic' ),
				'section'     => 'organization',
				'type'        => 'text',
				'description' => __( 'The brand or company that owns your locations. Shown as the parent organization.', 'schemagic' ),
			),
			'logo'           => array(
				'label'   => __( 'Logo', 'schemagic' ),
				'section' => 'organization',
				'type'    => 'image',
			),
			'same_as'        => array(
				'label'       => __( 'Social and profile links', 'schemagic' ),
				'section'     => 'organization',
				'type'        => 'url_lines',
				'placeholder' => "https://www.facebook.com/…\nhttps://www.instagram.com/…",
				'description' => __( 'One link per line.', 'schemagic' ),
			),
			'delete_data'    => array(
				'label'       => __( 'Remove data on uninstall', 'schemagic' ),
				'section'     => 'data',
				'type'        => 'checkbox',
				'description' => __( 'Delete all locations and settings when the plugin is deleted. Deactivating never deletes anything.', 'schemagic' ),
			),
		);
	}

	/**
	 * Add the settings submenu.
	 */
	public static function menu() {
		self::$hook_suffix = (string) add_submenu_page(
			'edit.php?post_type=' . Location_Post_Type::POST_TYPE,
			__( 'Schemagic Settings', 'schemagic' ),
			__( 'Settings', 'schemagic' ),
			Plugin::capability(),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the setting, sections and fields.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		$sections = array(
			'output'       => array( __( 'Output', 'schemagic' ), '' ),
			'organization' => array( __( 'Organization defaults', 'schemagic' ), __( 'Used by every location that leaves these fields blank.', 'schemagic' ) ),
			'data'         => array( __( 'Data', 'schemagic' ), '' ),
		);

		foreach ( $sections as $id => $section ) {
			add_settings_section(
				'schemagic_' . $id,
				$section[0],
				static function () use ( $section ) {
					if ( '' !== $section[1] ) {
						echo '<p>' . esc_html( $section[1] ) . '</p>';
					}
				},
				self::PAGE
			);
		}

		foreach ( self::fields() as $key => $field ) {
			add_settings_field(
				$key,
				esc_html( $field['label'] ),
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				'schemagic_' . $field['section'],
				array(
					'key'       => $key,
					'field'     => $field,
					'label_for' => 'schemagic-settings-' . $key,
				)
			);
		}
	}

	/**
	 * Render one settings field.
	 *
	 * @param array $args Field args.
	 */
	public static function render_field( $args ) {
		$settings = self::get();
		$key      = $args['key'];
		$field    = $args['field'];

		if ( 'checkbox' === $field['type'] ) {
			// The description doubles as the checkbox label.
			$field['checkbox_label'] = $field['description'];
			unset( $field['description'] );
		}

		Meta_Boxes::control(
			self::OPTION . '[' . $key . ']',
			$args['label_for'],
			$field,
			$settings[ $key ]
		);

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}
	}

	/**
	 * Sanitize the settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$clean    = array();

		foreach ( self::fields() as $key => $field ) {
			$value         = array_key_exists( $key, $input ) ? $input[ $key ] : null;
			$clean[ $key ] = Sanitizer::field( $value, $field, $defaults[ $key ] );
		}

		return $clean;
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		?>
		<div class="wrap schemagic-settings">
			<h1><?php esc_html_e( 'Schemagic Settings', 'schemagic' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
