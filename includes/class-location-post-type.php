<?php
/**
 * The private "location" post type and helpers for reading location data.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Location post type.
 */
final class Location_Post_Type {

	const POST_TYPE = 'schemagic_location';
	const META_KEY  = '_schemagic_location';

	/**
	 * Register the post type.
	 */
	public static function register() {
		$cap = Plugin::capability();

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Locations', 'schemagic' ),
					'singular_name'      => __( 'Location', 'schemagic' ),
					'menu_name'          => __( 'Schemagic', 'schemagic' ),
					'all_items'          => __( 'Locations', 'schemagic' ),
					'add_new'            => __( 'Add New', 'schemagic' ),
					'add_new_item'       => __( 'Add New Location', 'schemagic' ),
					'edit_item'          => __( 'Edit Location', 'schemagic' ),
					'new_item'           => __( 'New Location', 'schemagic' ),
					'search_items'       => __( 'Search Locations', 'schemagic' ),
					'not_found'          => __( 'No locations yet.', 'schemagic' ),
					'not_found_in_trash' => __( 'No locations in Trash.', 'schemagic' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_position'       => 80,
				'menu_icon'           => 'dashicons-location',
				'supports'            => array( 'title' ),
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'              => $cap,
					'read_post'              => $cap,
					'delete_post'            => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'edit_private_posts'     => $cap,
					'edit_published_posts'   => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'delete_posts'           => $cap,
					'delete_private_posts'   => $cap,
					'delete_published_posts' => $cap,
					'delete_others_posts'    => $cap,
					'create_posts'           => $cap,
				),
			)
		);
	}

	/**
	 * Admin-only hooks.
	 */
	public static function admin_hooks() {
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		add_action( 'admin_notices', array( __CLASS__, 'empty_state_notice' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
	}

	/**
	 * Get a location's saved data merged with defaults.
	 *
	 * @param int $post_id Location post ID.
	 * @return array
	 */
	public static function get_data( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_KEY, true );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), Fields::defaults() );
	}

	/**
	 * IDs of all published locations.
	 *
	 * @return int[]
	 */
	public static function published_ids() {
		static $ids = null;

		if ( null === $ids ) {
			$ids = array_map(
				'intval',
				get_posts(
					array(
						'post_type'              => self::POST_TYPE,
						'post_status'            => 'publish',
						'posts_per_page'         => -1,
						'orderby'                => 'menu_order title',
						'order'                  => 'ASC',
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
					)
				)
			);
		}

		return $ids;
	}

	/**
	 * Title placeholder on the edit screen.
	 *
	 * @param string   $text Placeholder.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		if ( self::POST_TYPE === $post->post_type ) {
			return __( 'Business or location name', 'schemagic' );
		}

		return $text;
	}

	/**
	 * Update messages without "View post" links (locations have no front-end page).
	 *
	 * @param array $messages Messages keyed by post type.
	 * @return array
	 */
	public static function updated_messages( $messages ) {
		$saved = __( 'Location saved.', 'schemagic' );

		$messages[ self::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Location updated.', 'schemagic' ),
			4  => __( 'Location updated.', 'schemagic' ),
			6  => __( 'Location published. Its schema is now live.', 'schemagic' ),
			7  => $saved,
			8  => $saved,
			10 => __( 'Draft saved. Publish the location to output its schema.', 'schemagic' ),
		);

		return $messages;
	}

	/**
	 * Friendly prompt on the list screen when there are no locations.
	 */
	public static function empty_state_notice() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . self::POST_TYPE !== $screen->id ) {
			return;
		}

		$counts = wp_count_posts( self::POST_TYPE );
		$total  = 0;

		foreach ( (array) $counts as $status => $count ) {
			if ( 'trash' !== $status && 'auto-draft' !== $status ) {
				$total += (int) $count;
			}
		}

		if ( $total > 0 ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Welcome to Schemagic.', 'schemagic' ),
			esc_html__( 'Add your business details and Schemagic will add LocalBusiness structured data to your site.', 'schemagic' ),
			esc_url( admin_url( 'post-new.php?post_type=' . self::POST_TYPE ) ),
			esc_html__( 'Add your business', 'schemagic' )
		);
	}

	/**
	 * List table columns.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );

		$columns['schemagic_type']    = __( 'Business type', 'schemagic' );
		$columns['schemagic_display'] = __( 'Shown on', 'schemagic' );

		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Render custom list table columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render_column( $column, $post_id ) {
		$data = self::get_data( $post_id );

		if ( 'schemagic_type' === $column ) {
			echo esc_html( Business_Types::label( $data['type'] ) );
			return;
		}

		if ( 'schemagic_display' === $column ) {
			if ( 'site' === $data['display'] ) {
				esc_html_e( 'Entire site', 'schemagic' );
			} elseif ( 'front' === $data['display'] ) {
				esc_html_e( 'Front page', 'schemagic' );
			} else {
				$count = count( $data['pages'] );
				/* translators: %d: number of pages. */
				echo esc_html( sprintf( _n( '%d page', '%d pages', $count, 'schemagic' ), $count ) );
			}
		}
	}
}
