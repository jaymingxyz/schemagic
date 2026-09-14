<?php
/**
 * Turns sanitized location data into a schema.org LocalBusiness array.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Schema builder.
 */
final class Schema_Builder {

	/**
	 * Build the schema for a saved location.
	 *
	 * @param int $location_id Location post ID.
	 * @return array
	 */
	public static function for_location( $location_id ) {
		return self::build(
			Location_Post_Type::get_data( $location_id ),
			$location_id,
			get_the_title( $location_id )
		);
	}

	/**
	 * Build the schema from location data.
	 *
	 * The admin preview calls this with unsaved form data, so it must not read
	 * the location's saved meta.
	 *
	 * @param array  $data          Sanitized location data.
	 * @param int    $location_id   Location post ID, or 0.
	 * @param string $fallback_name Name to use when the name field is blank.
	 * @return array
	 */
	public static function build( array $data, $location_id = 0, $fallback_name = '' ) {
		$data     = wp_parse_args( $data, Fields::defaults() );
		$settings = Settings::get();
		$type     = Business_Types::exists( $data['type'] ) ? $data['type'] : Business_Types::ROOT;
		$name     = '' !== $data['name'] ? $data['name'] : (string) $fallback_name;
		$url      = '' !== $data['url'] ? $data['url'] : self::default_url( $data );
		$logo_id  = $data['logo'] ? $data['logo'] : $settings['logo'];

		$schema = array(
			'@context'                  => 'https://schema.org',
			'@type'                     => $type,
			'@id'                       => self::node_id( $url, $location_id ),
			'name'                      => $name,
			'alternateName'             => $data['alternate_name'],
			'description'               => $data['description'],
			'url'                       => $url,
			'telephone'                 => $data['telephone'],
			'email'                     => $data['email'],
			'priceRange'                => $data['price_range'],
			'paymentAccepted'           => $data['payment_accepted'],
			'currenciesAccepted'        => $data['currencies_accepted'],
			'logo'                      => self::image_url( $logo_id ),
			'image'                     => self::images( $data['images'], $logo_id ),
			'address'                   => self::address( $data ),
			'geo'                       => self::geo( $data ),
			'hasMap'                    => $data['map_url'],
			'areaServed'                => self::one_or_many( $data['area_served'] ),
			'openingHoursSpecification' => array_merge(
				self::weekly_hours( $data['hours'] ),
				self::special_hours( $data['special_hours'] )
			),
			'sameAs'                    => $data['same_as'] ? $data['same_as'] : $settings['same_as'],
			'parentOrganization'        => self::parent_organization( $data, $settings, $name ),
		);

		if ( Business_Types::is_a( $type, 'FoodEstablishment' ) ) {
			$schema['menu']          = $data['menu_url'];
			$schema['servesCuisine'] = self::one_or_many( array_map( 'trim', explode( ',', $data['serves_cuisine'] ) ) );

			if ( '' !== $data['accepts_reservations'] ) {
				$schema['acceptsReservations'] = 'yes' === $data['accepts_reservations'];
			}
		}

		$schema = self::remove_empty( $schema );

		/**
		 * Filters a location's schema before output.
		 *
		 * @param array $schema      Schema array.
		 * @param int   $location_id Location post ID (0 in some previews).
		 * @param array $data        Sanitized location data.
		 */
		$schema = apply_filters( 'schemagic_schema_data', $schema, $location_id, $data );

		return is_array( $schema ) ? $schema : array();
	}

	/**
	 * Encode schema for a <script type="application/ld+json"> tag.
	 *
	 * JSON_HEX_TAG turns < and > into < and > so no field value can
	 * close the script tag.
	 *
	 * @param array $schema Schema array.
	 * @param bool  $pretty Pretty-print.
	 * @return string
	 */
	public static function encode( array $schema, $pretty = false ) {
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;

		if ( $pretty ) {
			$flags |= JSON_PRETTY_PRINT;
		}

		return (string) wp_json_encode( $schema, $flags );
	}

	/**
	 * The page this location's schema describes when no URL is set.
	 *
	 * @param array $data Location data.
	 * @return string
	 */
	public static function default_url( array $data ) {
		if ( 'pages' === $data['display'] && ! empty( $data['pages'] ) ) {
			$permalink = get_permalink( (int) reset( $data['pages'] ) );

			if ( $permalink ) {
				return $permalink;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Stable @id for the node.
	 *
	 * @param string $url         Location URL.
	 * @param int    $location_id Location post ID.
	 * @return string
	 */
	private static function node_id( $url, $location_id ) {
		$base = strtok( $url, '#' );

		return $base . '#localbusiness' . ( $location_id ? '-' . (int) $location_id : '' );
	}

	/**
	 * Full-size URL of an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function image_url( $attachment_id ) {
		if ( ! $attachment_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( (int) $attachment_id, 'full' );

		return $url ? $url : '';
	}

	/**
	 * Photo URLs, falling back to the logo.
	 *
	 * @param int[] $ids     Attachment IDs.
	 * @param int   $logo_id Logo attachment ID.
	 * @return string[]
	 */
	private static function images( array $ids, $logo_id ) {
		$urls = array_filter( array_map( array( __CLASS__, 'image_url' ), $ids ) );

		if ( ! $urls && $logo_id ) {
			$urls = array( self::image_url( $logo_id ) );
		}

		return array_values( array_filter( $urls ) );
	}

	/**
	 * PostalAddress node.
	 *
	 * @param array $data Location data.
	 * @return array
	 */
	private static function address( array $data ) {
		return array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $data['service_area'] ? '' : $data['street'],
			'addressLocality' => $data['locality'],
			'addressRegion'   => $data['region'],
			'postalCode'      => $data['postal_code'],
			'addressCountry'  => $data['country'],
		);
	}

	/**
	 * GeoCoordinates node, only when both coordinates are set.
	 *
	 * @param array $data Location data.
	 * @return array|null
	 */
	private static function geo( array $data ) {
		if ( '' === (string) $data['latitude'] || '' === (string) $data['longitude'] ) {
			return null;
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $data['latitude'],
			'longitude' => (float) $data['longitude'],
		);
	}

	/**
	 * Weekly hours, grouping days that share the same times.
	 *
	 * @param array $hours Hours keyed by day.
	 * @return array[]
	 */
	public static function weekly_hours( array $hours ) {
		$groups = array();

		foreach ( Fields::days() as $day => $info ) {
			$row = isset( $hours[ $day ] ) && is_array( $hours[ $day ] ) ? $hours[ $day ] : array();
			$mode = isset( $row['mode'] ) ? $row['mode'] : 'closed';

			if ( '24h' === $mode ) {
				$ranges = array(
					array(
						'opens'  => '00:00',
						'closes' => '23:59',
					),
				);
			} elseif ( 'open' === $mode && ! empty( $row['ranges'] ) ) {
				$ranges = $row['ranges'];
			} else {
				continue;
			}

			foreach ( $ranges as $range ) {
				if ( empty( $range['opens'] ) || empty( $range['closes'] ) ) {
					continue;
				}

				$key = $range['opens'] . '-' . $range['closes'];

				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => array(),
						'opens'     => $range['opens'],
						'closes'    => $range['closes'],
					);
				}

				$groups[ $key ]['dayOfWeek'][] = $info['schema'];
			}
		}

		return array_values( $groups );
	}

	/**
	 * Special hours. Closed all day is 00:00–00:00, per Google's guidance.
	 *
	 * @param array $rows Special hour rows.
	 * @return array[]
	 */
	public static function special_hours( array $rows ) {
		$specs = array();

		foreach ( $rows as $row ) {
			if ( empty( $row['from'] ) ) {
				continue;
			}

			$specs[] = array(
				'@type'        => 'OpeningHoursSpecification',
				'opens'        => $row['closed'] ? '00:00' : $row['opens'],
				'closes'       => $row['closed'] ? '00:00' : $row['closes'],
				'validFrom'    => $row['from'],
				'validThrough' => ! empty( $row['through'] ) ? $row['through'] : $row['from'],
			);
		}

		return $specs;
	}

	/**
	 * Parent organization node, unless it would just repeat the business name.
	 *
	 * @param array  $data     Location data.
	 * @param array  $settings Global settings.
	 * @param string $name     Business name.
	 * @return array|null
	 */
	private static function parent_organization( array $data, array $settings, $name ) {
		$parent = '' !== $data['parent_brand'] ? $data['parent_brand'] : $settings['org_name'];

		if ( '' === $parent || $parent === $name ) {
			return null;
		}

		return array(
			'@type' => 'Organization',
			'name'  => $parent,
		);
	}

	/**
	 * A single value for one item, a list for several, null for none.
	 *
	 * @param array $items Items.
	 * @return string|string[]|null
	 */
	private static function one_or_many( array $items ) {
		$items = array_values( array_filter( $items, 'strlen' ) );

		if ( ! $items ) {
			return null;
		}

		return 1 === count( $items ) ? $items[0] : $items;
	}

	/**
	 * Recursively drop null, '' and empty arrays. false and 0 are kept.
	 *
	 * A node left with only "@" keys (such as an address with no fields) is dropped too.
	 *
	 * @param array $value Array to clean.
	 * @return array
	 */
	public static function remove_empty( array $value ) {
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$item = self::remove_empty( $item );
			}

			if ( null === $item || '' === $item || array() === $item || self::only_keywords( $item ) ) {
				unset( $value[ $key ] );
				continue;
			}

			$value[ $key ] = $item;
		}

		// Re-index lists so they encode as JSON arrays, not objects.
		if ( self::is_list_with_gaps( $value ) ) {
			$value = array_values( $value );
		}

		return $value;
	}

	/**
	 * Whether an array holds nothing but JSON-LD keywords like "@type".
	 *
	 * @param mixed $item Value.
	 * @return bool
	 */
	private static function only_keywords( $item ) {
		if ( ! is_array( $item ) || ! $item ) {
			return false;
		}

		foreach ( array_keys( $item ) as $key ) {
			if ( ! is_string( $key ) || 0 !== strpos( $key, '@' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether every key is an integer (a list that lost items).
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	private static function is_list_with_gaps( array $value ) {
		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_int( $key ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}
