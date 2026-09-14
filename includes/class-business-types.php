<?php
/**
 * Schema.org LocalBusiness type tree.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Business type lookups.
 */
final class Business_Types {

	const ROOT = 'LocalBusiness';

	/**
	 * Cached types.
	 *
	 * @var array<string, array{label: string, parent: string}>|null
	 */
	private static $types = null;

	/**
	 * All types, keyed by schema.org type name.
	 *
	 * @return array<string, array{label: string, parent: string}>
	 */
	public static function all() {
		if ( null !== self::$types ) {
			return self::$types;
		}

		$raw = require SCHEMAGIC_DIR . 'includes/data/business-types.php';

		/**
		 * Filters the available business types.
		 *
		 * @param array $types Map of schema.org type => array( label, parent type ).
		 *                     The root type 'LocalBusiness' has an empty parent.
		 */
		$raw = (array) apply_filters( 'schemagic_business_types', $raw );

		self::$types = array();

		foreach ( $raw as $type => $entry ) {
			if ( ! is_string( $type ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9]*$/', $type ) ) {
				continue;
			}

			$entry = array_values( (array) $entry );

			self::$types[ $type ] = array(
				'label'  => isset( $entry[0] ) ? (string) $entry[0] : $type,
				'parent' => isset( $entry[1] ) ? (string) $entry[1] : '',
			);
		}

		return self::$types;
	}

	/**
	 * Whether a type is known.
	 *
	 * @param string $type Type name.
	 * @return bool
	 */
	public static function exists( $type ) {
		return is_string( $type ) && isset( self::all()[ $type ] );
	}

	/**
	 * Human-readable label.
	 *
	 * @param string $type Type name.
	 * @return string
	 */
	public static function label( $type ) {
		$types = self::all();

		return isset( $types[ $type ] ) ? $types[ $type ]['label'] : (string) $type;
	}

	/**
	 * Whether $type is $ancestor or descends from it.
	 *
	 * @param string $type     Type name.
	 * @param string $ancestor Ancestor type name.
	 * @return bool
	 */
	public static function is_a( $type, $ancestor ) {
		$types = self::all();
		$guard = 0;

		while ( '' !== $type && isset( $types[ $type ] ) && $guard++ < 20 ) {
			if ( $type === $ancestor ) {
				return true;
			}

			$type = $types[ $type ]['parent'];
		}

		return false;
	}

	/**
	 * Map of type => parent, for the admin script.
	 *
	 * @return array<string, string>
	 */
	public static function parent_map() {
		return wp_list_pluck( self::all(), 'parent' );
	}

	/**
	 * Types in depth-first order with children sorted by label.
	 *
	 * @return array<int, array{type: string, label: string, depth: int}>
	 */
	public static function tree() {
		$children = array();

		foreach ( self::all() as $type => $entry ) {
			$children[ $entry['parent'] ][ $type ] = $entry['label'];
		}

		foreach ( $children as &$group ) {
			asort( $group, SORT_NATURAL | SORT_FLAG_CASE );
		}
		unset( $group );

		$list = array();
		self::walk( '', 0, $children, $list );

		return $list;
	}

	/**
	 * Recursive helper for tree().
	 *
	 * @param string $parent   Parent type.
	 * @param int    $depth    Current depth.
	 * @param array  $children Children grouped by parent.
	 * @param array  $list     Output list, by reference.
	 */
	private static function walk( $parent, $depth, $children, &$list ) {
		if ( empty( $children[ $parent ] ) || $depth > 10 ) {
			return;
		}

		foreach ( $children[ $parent ] as $type => $label ) {
			$list[] = array(
				'type'  => $type,
				'label' => $label,
				'depth' => $depth,
			);

			self::walk( $type, $depth + 1, $children, $list );
		}
	}
}
