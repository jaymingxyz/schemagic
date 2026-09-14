<?php
/**
 * Removes Schemagic data on uninstall, but only if the user opted in.
 *
 * @package Schemagic
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete locations and settings for the current site if "Remove data on uninstall" is on.
 */
function schemagic_uninstall_site() {
	$settings = get_option( 'schemagic_settings' );

	if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'      => 'schemagic_location',
			'post_status'    => array_keys( get_post_stati() ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}

	delete_option( 'schemagic_settings' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $schemagic_site_id ) {
		switch_to_blog( $schemagic_site_id );
		schemagic_uninstall_site();
		restore_current_blog();
	}
} else {
	schemagic_uninstall_site();
}

// Notice dismissals are harmless to remove whatever the setting.
delete_metadata( 'user', 0, 'schemagic_dismissed_seo_notice', '', true );
