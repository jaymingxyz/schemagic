<?php
/**
 * Warns about SEO plugins that may output overlapping schema.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * SEO plugin conflict notice.
 */
final class Seo_Conflicts {

	const DISMISS_ACTION = 'schemagic_dismiss_seo_notice';
	const USER_META      = 'schemagic_dismissed_seo_notice';

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * Names of active SEO plugins known to output schema.
	 *
	 * @return string[]
	 */
	public static function detected() {
		$plugins = array();

		if ( defined( 'WPSEO_VERSION' ) ) {
			$plugins[] = defined( 'WPSEO_LOCAL_VERSION' ) ? 'Yoast SEO and Yoast Local SEO' : 'Yoast SEO';
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$plugins[] = 'Rank Math';
		}

		if ( defined( 'AIOSEO_VERSION' ) ) {
			$plugins[] = 'All in One SEO';
		}

		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$plugins[] = 'SEOPress';
		}

		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			$plugins[] = 'The SEO Framework';
		}

		/**
		 * Filters the detected SEO plugins that may output overlapping schema.
		 *
		 * @param string[] $plugins Plugin names.
		 */
		return (array) apply_filters( 'schemagic_seo_conflicts', $plugins );
	}

	/**
	 * Show the notice on Schemagic screens only.
	 */
	public static function notice() {
		if ( ! Plugin::is_plugin_screen() || ! current_user_can( Plugin::capability() ) ) {
			return;
		}

		$plugins = self::detected();

		if ( ! $plugins || get_user_meta( get_current_user_id(), self::USER_META, true ) === self::signature( $plugins ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );

		printf(
			'<div class="notice notice-warning"><p>%1$s</p><p><a href="%2$s">%3$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: list of SEO plugin names. */
					__( '%s may also add business or organization schema. To avoid duplicate markup, turn off its local business schema feature, then check your pages with the Rich Results Test.', 'schemagic' ),
					wp_sprintf_l( '%l', $plugins )
				)
			),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'schemagic' )
		);
	}

	/**
	 * Remember the dismissal for this set of plugins.
	 */
	public static function dismiss() {
		check_admin_referer( self::DISMISS_ACTION );

		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'schemagic' ), 403 );
		}

		update_user_meta( get_current_user_id(), self::USER_META, self::signature( self::detected() ) );

		$redirect = wp_get_referer();
		wp_safe_redirect( $redirect ? $redirect : admin_url( 'edit.php?post_type=' . Location_Post_Type::POST_TYPE ) );
		exit;
	}

	/**
	 * Stable signature of a plugin list, so a newly activated plugin shows the notice again.
	 *
	 * @param string[] $plugins Plugin names.
	 * @return string
	 */
	private static function signature( array $plugins ) {
		sort( $plugins );

		return md5( implode( '|', $plugins ) );
	}
}
