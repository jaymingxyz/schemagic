<?php
/**
 * Wires the plugin's components to WordPress.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
final class Plugin {

	const REPO_URL   = 'https://github.com/jaymingxyz/schemagic';
	const ISSUES_URL = 'https://github.com/jaymingxyz/schemagic/issues';

	/**
	 * Register hooks for every component.
	 */
	public static function init() {
		add_action( 'init', array( Location_Post_Type::class, 'register' ) );
		add_action( 'init', array( Shortcode::class, 'register' ) );

		Output::hooks();

		if ( is_admin() ) {
			Location_Post_Type::admin_hooks();
			Meta_Boxes::hooks();
			Importer::hooks();
			Settings::hooks();
			Seo_Conflicts::hooks();

			add_filter( 'plugin_action_links_' . plugin_basename( SCHEMAGIC_FILE ), array( __CLASS__, 'action_links' ) );
			add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
		}
	}

	/**
	 * Capability required to manage locations and settings.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability required to manage Schemagic.
		 *
		 * @param string $capability Default 'manage_options'.
		 */
		return (string) apply_filters( 'schemagic_capability', 'manage_options' );
	}

	/**
	 * Whether the current admin screen belongs to Schemagic.
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && ( Location_Post_Type::POST_TYPE === $screen->post_type || Settings::screen_id() === $screen->id );
	}

	/**
	 * Add "Locations" and "Settings" links on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$extra = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=' . Location_Post_Type::POST_TYPE ) ),
				esc_html__( 'Locations', 'schemagic' )
			),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( Settings::page_url() ),
				esc_html__( 'Settings', 'schemagic' )
			),
		);

		return array_merge( $extra, $links );
	}

	/**
	 * Author credit and bug report link in the admin footer, on Schemagic screens only.
	 *
	 * @param string $text Default footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		if ( ! self::is_plugin_screen() ) {
			return $text;
		}

		return sprintf(
			/* translators: 1: author name linked to the project, 2: "Report it on GitHub" link. */
			esc_html__( 'Schemagic by %1$s. Found a bug? %2$s.', 'schemagic' ),
			'<a href="' . esc_url( self::REPO_URL ) . '" target="_blank" rel="noopener noreferrer">Jay</a>',
			'<a href="' . esc_url( self::ISSUES_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Report it on GitHub', 'schemagic' ) . '</a>'
		);
	}
}
