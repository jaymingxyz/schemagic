<?php
/**
 * Prints JSON-LD in the front-end <head>.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output.
 */
final class Output {

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_action( 'wp_head', array( __CLASS__, 'print_schema' ), 20 );
	}

	/**
	 * Print the script tag for every location that belongs on this request.
	 */
	public static function print_schema() {
		if ( is_feed() || is_embed() || is_404() || is_robots() ) {
			return;
		}

		$settings = Settings::get();

		if ( empty( $settings['output_enabled'] ) ) {
			return;
		}

		$ids = self::matching_location_ids();

		/**
		 * Filters whether to print schema on the current request.
		 *
		 * @param bool  $should_output Whether any location matches.
		 * @param int[] $location_ids  Matching location IDs.
		 */
		if ( ! apply_filters( 'schemagic_should_output', ! empty( $ids ), $ids ) || ! $ids ) {
			return;
		}

		$nodes = array_values( array_filter( array_map( array( Schema_Builder::class, 'for_location' ), $ids ) ) );

		if ( ! $nodes ) {
			return;
		}

		echo "\n<!-- Schemagic -->\n";
		echo '<script type="application/ld+json" class="schemagic-schema">';
		// JSON is encoded with JSON_HEX_TAG, so it cannot break out of the script tag.
		echo Schema_Builder::encode( self::payload( $nodes ), defined( 'WP_DEBUG' ) && WP_DEBUG ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "</script>\n";
	}

	/**
	 * A single node, or an @graph when there are several.
	 *
	 * @param array[] $nodes Schema nodes, each with its own @context.
	 * @return array
	 */
	public static function payload( array $nodes ) {
		if ( 1 === count( $nodes ) ) {
			return $nodes[0];
		}

		$graph = array();

		foreach ( $nodes as $node ) {
			unset( $node['@context'] );
			$graph[] = $node;
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Published locations whose "Display on" rule matches the current request.
	 *
	 * @return int[]
	 */
	public static function matching_location_ids() {
		$ids = array();

		foreach ( Location_Post_Type::published_ids() as $id ) {
			if ( self::matches( Location_Post_Type::get_data( $id ) ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Whether a location's display rule matches the current request.
	 *
	 * @param array $data Location data.
	 * @return bool
	 */
	public static function matches( array $data ) {
		switch ( $data['display'] ) {
			case 'site':
				return true;

			case 'pages':
				return is_singular()
					&& in_array( (int) get_queried_object_id(), array_map( 'intval', (array) $data['pages'] ), true );

			default:
				return is_front_page();
		}
	}
}
