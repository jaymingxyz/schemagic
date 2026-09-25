<?php
/**
 * Reads pasted JSON-LD and maps it onto location fields.
 *
 * The code is only decoded as JSON, never stored or printed as-is. Every
 * value it yields goes through Sanitizer before it reaches the form.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Schema importer.
 */
final class Importer {

	const AJAX_ACTION = 'schemagic_import';
	const MAX_BYTES   = 1048576;
	const MAX_IMAGES  = 20;

	/**
	 * Types that describe parts of a page or business, never the business itself.
	 */
	const NOT_BUSINESS = array(
		'AggregateRating',
		'Article',
		'BlogPosting',
		'BreadcrumbList',
		'ContactPoint',
		'Event',
		'GeoCoordinates',
		'ImageObject',
		'ListItem',
		'Offer',
		'OpeningHoursSpecification',
		'Person',
		'PostalAddress',
		'Product',
		'Review',
		'SearchAction',
		'WebPage',
		'WebSite',
	);

	/**
	 * Used only when no business-like node exists.
	 */
	const ORGANIZATION_TYPES = array( 'Organization', 'Corporation', 'Place' );

	/**
	 * Common country spellings that aren't ISO codes or full names.
	 */
	const COUNTRY_ALIASES = array(
		'USA'                      => 'US',
		'U.S.A.'                   => 'US',
		'U.S.'                     => 'US',
		'UNITED STATES OF AMERICA' => 'US',
		'AMERICA'                  => 'US',
		'UK'                       => 'GB',
		'U.K.'                     => 'GB',
		'GBR'                      => 'GB',
		'GREAT BRITAIN'            => 'GB',
		'BRITAIN'                  => 'GB',
		'ENGLAND'                  => 'GB',
		'SCOTLAND'                 => 'GB',
		'WALES'                    => 'GB',
		'NORTHERN IRELAND'         => 'GB',
		'CAN'                      => 'CA',
		'AUS'                      => 'AU',
		'NZL'                      => 'NZ',
		'IRL'                      => 'IE',
		'DEU'                      => 'DE',
		'FRA'                      => 'FR',
		'ESP'                      => 'ES',
		'ITA'                      => 'IT',
		'NLD'                      => 'NL',
		'IND'                      => 'IN',
		'SGP'                      => 'SG',
		'ZAF'                      => 'ZA',
	);

	/**
	 * Two-letter day codes (as in "Mo-Fr 09:00-17:00") => stored day keys, in week order.
	 */
	const DAYS = array(
		'mo' => 'monday',
		'tu' => 'tuesday',
		'we' => 'wednesday',
		'th' => 'thursday',
		'fr' => 'friday',
		'sa' => 'saturday',
		'su' => 'sunday',
	);

	/**
	 * Nodes with an @id, for resolving references like { "@id": "#address" }.
	 *
	 * @var array<string, array>
	 */
	private static $by_id = array();

	/**
	 * Messages about anything that couldn't be imported.
	 *
	 * @var string[]
	 */
	private static $notes = array();

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax' ) );
	}

	/**
	 * AJAX: parse pasted code and return sanitized field values.
	 */
	public static function ajax() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $post_id || Location_Post_Type::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'schemagic' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded as JSON only; values are sanitized in parse().
		$code  = isset( $_POST['code'] ) && is_string( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '';
		$index = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : 0;

		$result = self::parse( $code, $index );

		if ( '' !== $result['error'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		$result['media'] = self::media_previews( $result['data'] );

		wp_send_json_success( $result );
	}

	/**
	 * Parse JSON-LD into sanitized location data.
	 *
	 * @param string $code  Pasted code: JSON, script tags, or page HTML.
	 * @param int    $index Which business to import when the code has several.
	 * @return array{data: array, filled: string[], notes: string[], businesses: string[], index: int, error: string}
	 */
	public static function parse( $code, $index = 0 ) {
		$result = array(
			'data'       => array(),
			'filled'     => array(),
			'notes'      => array(),
			'businesses' => array(),
			'index'      => 0,
			'error'      => '',
		);

		self::$by_id = array();
		self::$notes = array();

		$code = trim( (string) $code );

		if ( '' === $code ) {
			$result['error'] = __( 'Paste some schema code first.', 'schemagic' );
			return $result;
		}

		if ( strlen( $code ) > self::MAX_BYTES ) {
			$result['error'] = __( 'That code is too long. Paste only the JSON-LD for your business.', 'schemagic' );
			return $result;
		}

		$json_error = '';
		$documents  = self::decode( $code, $json_error );

		if ( ! $documents ) {
			$result['error'] = '' !== $json_error
				/* translators: %s: JSON parser error, such as "Syntax error". */
				? sprintf( __( 'That isn\'t valid JSON-LD (%s). Paste the contents of a <script type="application/ld+json"> tag, or the whole tag.', 'schemagic' ), $json_error )
				: __( 'No JSON-LD was found. Paste the contents of a <script type="application/ld+json"> tag, or the whole tag.', 'schemagic' );
			return $result;
		}

		$nodes = array();

		foreach ( $documents as $document ) {
			self::collect( $document, $nodes, 0 );
		}

		$candidates = self::candidates( $nodes );

		if ( ! $candidates ) {
			$result['error'] = __( 'No business was found in that code. Schemagic looks for a LocalBusiness (or one of its types) or an Organization.', 'schemagic' );
			return $result;
		}

		$index = min( $index, count( $candidates ) - 1 );

		$result['index']      = $index;
		$result['businesses'] = array_map( array( __CLASS__, 'label' ), $candidates );

		$fields   = Fields::all();
		$defaults = Fields::defaults();

		foreach ( self::map( $candidates[ $index ] ) as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}

			$clean = Sanitizer::field( $value, $fields[ $key ], $defaults[ $key ] );

			if ( self::is_blank( $clean, $fields[ $key ] ) ) {
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					self::note(
						sprintf(
							/* translators: 1: field label, 2: the value that was skipped. */
							__( '%1$s ("%2$s") isn\'t valid, so it was skipped.', 'schemagic' ),
							$fields[ $key ]['label'],
							wp_html_excerpt( $value, 60, '…' )
						)
					);
				}
				continue;
			}

			$result['data'][ $key ] = $clean;
			$result['filled'][]     = $fields[ $key ]['label'];
		}

		if ( ! $result['data'] ) {
			$result['error'] = __( 'A business was found, but none of its details could be read.', 'schemagic' );
			return $result;
		}

		$result['notes'] = self::$notes;

		return $result;
	}

	/**
	 * Decode every JSON-LD block in the code.
	 *
	 * @param string $code  Pasted code.
	 * @param string $error Set to the first JSON error, if any.
	 * @return array[]
	 */
	private static function decode( $code, &$error ) {
		$blocks    = preg_match_all( '#<script\b[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $code, $matches ) ? $matches[1] : array( $code );
		$documents = array();

		foreach ( $blocks as $block ) {
			// Some plugins wrap JSON-LD in HTML comments or CDATA markers.
			$block = preg_replace( '#^\s*(?:<!--|//\s*<!\[CDATA\[|/\*\s*<!\[CDATA\[\s*\*/)#', '', $block );
			$block = preg_replace( '#(?:-->|//\s*\]\]>|/\*\s*\]\]>\s*\*/)\s*$#', '', $block );

			$decoded = json_decode( trim( $block ), true, 128 );

			if ( is_array( $decoded ) ) {
				$documents[] = $decoded;
			} elseif ( '' === $error && JSON_ERROR_NONE !== json_last_error() ) {
				$error = json_last_error_msg();
			}
		}

		return $documents;
	}

	/**
	 * Collect every typed node, including nested ones and @graph members.
	 *
	 * @param mixed $value Decoded JSON.
	 * @param array $nodes Collected nodes, by reference.
	 * @param int   $depth Recursion depth.
	 */
	private static function collect( $value, array &$nodes, $depth ) {
		if ( ! is_array( $value ) || $depth > 20 ) {
			return;
		}

		if ( isset( $value['@type'] ) && ! self::is_list( $value ) ) {
			$id = isset( $value['@id'] ) && is_string( $value['@id'] ) ? $value['@id'] : '';

			if ( '' === $id || ! isset( self::$by_id[ $id ] ) ) {
				$nodes[] = $value;

				if ( '' !== $id ) {
					self::$by_id[ $id ] = $value;
				}
			}
		}

		foreach ( $value as $key => $item ) {
			if ( '@context' !== $key ) {
				self::collect( $item, $nodes, $depth + 1 );
			}
		}
	}

	/**
	 * Nodes that could be the business, best matches first.
	 *
	 * @param array[] $nodes Typed nodes.
	 * @return array[]
	 */
	private static function candidates( array $nodes ) {
		$known         = array();
		$likely        = array();
		$organizations = array();

		foreach ( $nodes as $node ) {
			$types = self::types( $node );

			if ( array_filter( $types, array( Business_Types::class, 'exists' ) ) ) {
				$known[] = $node;
				continue;
			}

			if ( array_intersect( $types, self::NOT_BUSINESS ) || '' === self::text( self::prop( $node, 'name' ) ) ) {
				continue;
			}

			// A named node with an address, phone or hours is probably a business of a type we don't list.
			foreach ( array( 'address', 'telephone', 'geo', 'openingHoursSpecification', 'openingHours' ) as $key ) {
				if ( isset( $node[ $key ] ) ) {
					$likely[] = $node;
					continue 2;
				}
			}

			if ( array_intersect( $types, self::ORGANIZATION_TYPES ) ) {
				$organizations[] = $node;
			}
		}

		if ( $known ) {
			return $known;
		}

		return $likely ? $likely : $organizations;
	}

	/**
	 * Map one business node to raw (unsanitized) field values.
	 *
	 * @param array $node Business node.
	 * @return array
	 */
	private static function map( array $node ) {
		$raw   = array();
		$types = self::types( $node );
		$type  = self::pick_type( $types );

		if ( '' !== $type ) {
			$raw['type'] = $type;
		} else {
			self::note(
				sprintf(
					/* translators: %s: schema.org type name(s). */
					__( 'The type "%s" isn\'t in Schemagic\'s list. Choose the closest business type on the Business tab.', 'schemagic' ),
					implode( ', ', $types )
				)
			);
		}

		$raw['name']                = self::text( self::prop( $node, 'name' ) );
		$raw['alternate_name']      = self::text( self::prop( $node, 'alternateName' ) );
		$raw['description']         = self::text( self::prop( $node, 'description' ) );
		$raw['url']                 = self::url_of( self::prop( $node, 'url' ) );
		$raw['telephone']           = self::text( self::prop( $node, 'telephone' ) );
		$raw['email']               = self::email( self::prop( $node, 'email' ) );
		$raw['price_range']         = self::text( self::prop( $node, 'priceRange' ) );
		$raw['payment_accepted']    = self::joined( self::prop( $node, 'paymentAccepted' ) );
		$raw['currencies_accepted'] = self::joined( self::prop( $node, 'currenciesAccepted' ) );

		// Fall back to the first contact point for phone and email.
		$contact = self::first( self::prop( $node, 'contactPoint' ) );

		if ( is_array( $contact ) ) {
			if ( '' === $raw['telephone'] ) {
				$raw['telephone'] = self::text( self::prop( $contact, 'telephone' ) );
			}

			if ( '' === $raw['email'] ) {
				$raw['email'] = self::email( self::prop( $contact, 'email' ) );
			}
		}

		$raw = array_merge( $raw, self::address( $node ), self::geo( $node ) );

		$raw['map_url']     = self::url_of( self::prop( $node, 'hasMap' ) );
		$raw['area_served'] = self::area_names( self::prop( $node, 'areaServed' ) );

		list( $hours, $special ) = self::hours( $node );

		if ( null !== $hours ) {
			$raw['hours'] = $hours;
		}

		if ( $special ) {
			$raw['special_hours'] = $special;
		}

		$missing = 0;
		$logo    = self::attachment_ids( self::prop( $node, 'logo' ), $missing );
		$images  = self::attachment_ids( self::prop( $node, 'image' ), $missing );

		if ( $logo ) {
			$raw['logo'] = $logo[0];
		}

		if ( $images ) {
			$raw['images'] = $images;
		}

		if ( $missing ) {
			self::note(
				sprintf(
					/* translators: %d: number of images. */
					_n(
						'%d image isn\'t in your Media Library, so it was skipped. Upload it, then choose it on the Images tab.',
						'%d images aren\'t in your Media Library, so they were skipped. Upload them, then choose them on the Images tab.',
						$missing,
						'schemagic'
					),
					$missing
				)
			);
		}

		$raw['same_as']      = self::urls( self::prop( $node, 'sameAs' ) );
		$raw['parent_brand'] = self::name_of( self::prop( $node, 'parentOrganization' ) );

		if ( '' === $raw['parent_brand'] ) {
			$raw['parent_brand'] = self::name_of( self::prop( $node, 'brand' ) );
		}

		$menu = self::prop( $node, 'hasMenu' );

		$raw['menu_url']             = self::url_of( null !== $menu ? $menu : self::prop( $node, 'menu' ) );
		$raw['serves_cuisine']       = self::joined( self::prop( $node, 'servesCuisine' ) );
		$raw['accepts_reservations'] = self::yes_no( self::prop( $node, 'acceptsReservations' ) );

		if ( isset( $node['aggregateRating'] ) || isset( $node['review'] ) ) {
			self::note( __( 'Reviews and ratings were skipped. Google ignores reviews a business publishes about itself.', 'schemagic' ) );
		}

		if ( isset( $node['department'] ) ) {
			self::note( __( 'Departments were skipped. Add each department as its own location.', 'schemagic' ) );
		}

		return $raw;
	}

	/**
	 * Address fields.
	 *
	 * @param array $node Business node.
	 * @return array
	 */
	private static function address( array $node ) {
		$address = self::first( self::prop( $node, 'address' ) );

		if ( is_string( $address ) && '' !== trim( $address ) ) {
			self::note( __( 'The address was one line of text, so it was put in Street address. Move each part to the right field on the Address tab.', 'schemagic' ) );

			return array( 'street' => $address );
		}

		if ( ! is_array( $address ) ) {
			return array();
		}

		return array(
			'street'      => self::joined( self::prop( $address, 'streetAddress' ) ),
			'locality'    => self::text( self::prop( $address, 'addressLocality' ) ),
			'region'      => self::text( self::prop( $address, 'addressRegion' ) ),
			'postal_code' => self::text( self::prop( $address, 'postalCode' ) ),
			'country'     => self::country( self::prop( $address, 'addressCountry' ) ),
		);
	}

	/**
	 * Latitude and longitude, from a GeoCoordinates node or the node itself.
	 *
	 * @param array $node Business node.
	 * @return array
	 */
	private static function geo( array $node ) {
		$geo    = self::first( self::prop( $node, 'geo' ) );
		$source = is_array( $geo ) ? $geo : $node;
		$lat    = self::text( self::prop( $source, 'latitude' ) );
		$lng    = self::text( self::prop( $source, 'longitude' ) );

		if ( '' === $lat && '' === $lng ) {
			return array();
		}

		return array(
			'latitude'  => $lat,
			'longitude' => $lng,
		);
	}

	/**
	 * Weekly and special hours from openingHoursSpecification and openingHours.
	 *
	 * @param array $node Business node.
	 * @return array{0: array|null, 1: array} Weekly hours (null if none found) and special hours rows.
	 */
	private static function hours( array $node ) {
		$weekly   = Fields::default_hours();
		$found    = false;
		$special  = array();
		$skipped  = false;
		$unparsed = array();

		foreach ( self::as_list( self::prop( $node, 'openingHoursSpecification' ) ) as $spec ) {
			$spec = self::resolve( $spec );

			if ( ! is_array( $spec ) ) {
				continue;
			}

			$opens  = self::time_of( self::prop( $spec, 'opens' ) );
			$closes = self::time_of( self::prop( $spec, 'closes' ) );
			$from   = self::date_of( self::prop( $spec, 'validFrom' ) );

			// Dated specifications are holiday or seasonal hours.
			if ( '' !== $from ) {
				$through = self::date_of( self::prop( $spec, 'validThrough' ) );
				$closed  = '' === $opens || '' === $closes || $opens === $closes;

				$special[] = array(
					'from'    => $from,
					'through' => '' !== $through ? $through : $from,
					'closed'  => $closed ? 1 : 0,
					'opens'   => $closed ? '' : $opens,
					'closes'  => $closed ? '' : self::before_midnight( $closes ),
				);
				continue;
			}

			foreach ( self::as_list( self::prop( $spec, 'dayOfWeek' ) ) as $day ) {
				$day = self::resolve( $day );
				$key = self::day_key( is_array( $day ) ? self::text( isset( $day['@id'] ) ? $day['@id'] : self::prop( $day, 'name' ) ) : self::text( $day ) );

				if ( '' === $key ) {
					$skipped = true;
					continue;
				}

				if ( self::add_range( $weekly, $key, $opens, $closes ) ) {
					$found = true;
				}
			}
		}

		// Text format, e.g. "Mo-Fr 09:00-17:00" or "Mo,We 09:00-12:00, Sa 10:00-14:00".
		foreach ( self::as_list( self::prop( $node, 'openingHours' ) ) as $line ) {
			$line = self::text( $line );

			if ( ! preg_match_all( '/([A-Za-z]{2}(?:\s*[-,]\s*[A-Za-z]{2})*)\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $line, $matches, PREG_SET_ORDER ) ) {
				if ( '' !== $line ) {
					$unparsed[] = $line;
				}
				continue;
			}

			foreach ( $matches as $match ) {
				foreach ( self::expand_days( $match[1] ) as $key ) {
					if ( self::add_range( $weekly, $key, self::time_of( $match[2] ), self::time_of( $match[3] ) ) ) {
						$found = true;
					}
				}
			}
		}

		if ( $skipped ) {
			self::note( __( 'Hours for public holidays were skipped. Add the dates under Holiday and special hours on the Hours tab.', 'schemagic' ) );
		}

		if ( $unparsed ) {
			self::note(
				sprintf(
					/* translators: %s: opening hours text that couldn't be read. */
					__( 'These opening hours couldn\'t be read: %s. Set them on the Hours tab.', 'schemagic' ),
					implode( '; ', $unparsed )
				)
			);
		}

		return array( $found ? $weekly : null, $special );
	}

	/**
	 * Add a time range to a day. 00:00–23:59 means open 24 hours; 00:00–00:00 means closed.
	 *
	 * @param array  $weekly Weekly hours, by reference.
	 * @param string $day    Day key.
	 * @param string $opens  Opening time.
	 * @param string $closes Closing time.
	 * @return bool Whether the day was changed.
	 */
	private static function add_range( array &$weekly, $day, $opens, $closes ) {
		if ( '' === $opens || '' === $closes || $opens === $closes ) {
			return false;
		}

		if ( '00:00' === $opens && ( '23:59' === $closes || '24:00' === $closes ) ) {
			$weekly[ $day ] = array(
				'mode'   => '24h',
				'ranges' => array(),
			);
			return true;
		}

		if ( '24h' !== $weekly[ $day ]['mode'] ) {
			$weekly[ $day ]['mode']     = 'open';
			$weekly[ $day ]['ranges'][] = array(
				'opens'  => $opens,
				'closes' => self::before_midnight( $closes ),
			);
		}

		return true;
	}

	/**
	 * Expand "Mo-Fr" or "Mo,We,Fr" (or "Fr-Mo", wrapping the weekend) to day keys.
	 *
	 * @param string $text Day codes.
	 * @return string[]
	 */
	private static function expand_days( $text ) {
		$order = array_keys( self::DAYS );
		$days  = array();

		foreach ( explode( ',', strtolower( $text ) ) as $part ) {
			$bounds = array_map( 'trim', explode( '-', $part ) );
			$start  = array_search( $bounds[0], $order, true );
			$end    = array_search( end( $bounds ), $order, true );

			if ( false === $start || false === $end ) {
				continue;
			}

			for ( $i = $start, $guard = 0; $guard < 7; $i = ( $i + 1 ) % 7, ++$guard ) {
				$days[] = self::DAYS[ $order[ $i ] ];

				if ( $i === $end ) {
					break;
				}
			}
		}

		return array_values( array_unique( $days ) );
	}

	/**
	 * Day key from "Monday", "https://schema.org/Monday", "Mon" or "Mo".
	 *
	 * @param string $text Day.
	 * @return string Day key, or '' if not a weekday (e.g. PublicHolidays).
	 */
	private static function day_key( $text ) {
		$text = strtolower( self::strip_prefix( $text ) );

		foreach ( self::DAYS as $abbr => $day ) {
			if ( $text === $day || $text === $abbr || substr( $day, 0, 3 ) === $text ) {
				return $day;
			}
		}

		return '';
	}

	/**
	 * Country code from a code, a name, a common alias, or a Country node.
	 *
	 * @param mixed $value addressCountry value.
	 * @return string
	 */
	private static function country( $value ) {
		$value = self::first( $value );
		$value = is_array( $value ) ? self::text( self::prop( $value, 'name' ) ) : self::text( $value );

		if ( '' === $value ) {
			return '';
		}

		$countries = Fields::countries();
		$upper     = strtoupper( $value );

		if ( isset( $countries[ $upper ] ) ) {
			return $upper;
		}

		$aliases = self::COUNTRY_ALIASES;

		if ( isset( $aliases[ $upper ] ) ) {
			return $aliases[ $upper ];
		}

		foreach ( $countries as $code => $name ) {
			if ( mb_strtolower( $name ) === mb_strtolower( $value ) ) {
				return $code;
			}
		}

		self::note(
			sprintf(
				/* translators: %s: country as written in the imported code. */
				__( 'The country "%s" wasn\'t recognized. Choose it on the Address tab.', 'schemagic' ),
				$value
			)
		);

		return '';
	}

	/**
	 * Media Library attachment IDs for image URLs or ImageObjects.
	 *
	 * @param mixed $value   Image value(s).
	 * @param int   $missing Count of images not found in the Media Library, by reference.
	 * @return int[]
	 */
	private static function attachment_ids( $value, &$missing ) {
		$ids = array();

		foreach ( array_slice( self::as_list( $value ), 0, self::MAX_IMAGES ) as $item ) {
			$url = self::url_of( $item );

			if ( '' === $url ) {
				continue;
			}

			$id = self::attachment_id( $url );

			if ( $id ) {
				$ids[] = $id;
			} else {
				++$missing;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Attachment ID for a Media Library URL, including resized versions.
	 *
	 * @param string $url Image URL.
	 * @return int
	 */
	private static function attachment_id( $url ) {
		$url = esc_url_raw( $url, array( 'http', 'https' ) );

		if ( '' === $url ) {
			return 0;
		}

		$id = attachment_url_to_postid( $url );

		if ( ! $id ) {
			// photo-300x200.jpg is a resized copy of photo.jpg.
			$full = preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', strtok( $url, '?' ) );

			if ( $full !== $url ) {
				$id = attachment_url_to_postid( $full );
			}
		}

		return $id && wp_attachment_is_image( $id ) ? (int) $id : 0;
	}

	/**
	 * Thumbnails for imported images, so the form can show them.
	 *
	 * @param array $data Sanitized data.
	 * @return array<string, array{id: int, url: string}[]>
	 */
	private static function media_previews( array $data ) {
		$media = array();

		foreach ( Fields::all() as $key => $field ) {
			if ( ! isset( $data[ $key ] ) || ! in_array( $field['type'], array( 'image', 'gallery' ), true ) ) {
				continue;
			}

			$media[ $key ] = array();

			foreach ( (array) $data[ $key ] as $id ) {
				$url = wp_get_attachment_image_url( (int) $id, 'thumbnail' );

				if ( $url ) {
					$media[ $key ][] = array(
						'id'  => (int) $id,
						'url' => $url,
					);
				}
			}
		}

		return $media;
	}

	/**
	 * Area names from strings or Place nodes. Shapes like GeoCircle are skipped.
	 *
	 * @param mixed $value areaServed value.
	 * @return string[]
	 */
	private static function area_names( $value ) {
		$names  = array();
		$shapes = false;

		foreach ( self::as_list( $value ) as $item ) {
			$item = self::resolve( $item );
			$name = is_array( $item ) ? self::text( self::prop( $item, 'name' ) ) : self::text( $item );

			if ( '' !== $name ) {
				$names[] = $name;
			} elseif ( is_array( $item ) ) {
				$shapes = true;
			}
		}

		if ( $shapes ) {
			self::note( __( 'Service areas drawn as shapes, such as a radius around a point, were skipped. Add the places you serve by name on the Address tab.', 'schemagic' ) );
		}

		return $names;
	}

	/**
	 * "yes", "no" or "" from a boolean, "True"/"False", or a reservation URL.
	 *
	 * @param mixed $value acceptsReservations value.
	 * @return string
	 */
	private static function yes_no( $value ) {
		$value = self::first( $value );

		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}

		$text = strtolower( self::strip_prefix( self::text( $value ) ) );

		if ( in_array( $text, array( 'false', 'no', '0' ), true ) ) {
			return 'no';
		}

		if ( in_array( $text, array( 'true', 'yes', '1' ), true ) || 0 === strpos( $text, 'http' ) ) {
			return 'yes';
		}

		return '';
	}

	/**
	 * The most specific known business type.
	 *
	 * @param string[] $types Type names.
	 * @return string
	 */
	private static function pick_type( array $types ) {
		$best       = '';
		$best_depth = -1;

		foreach ( $types as $type ) {
			if ( Business_Types::exists( $type ) && Business_Types::depth( $type ) > $best_depth ) {
				$best       = $type;
				$best_depth = Business_Types::depth( $type );
			}
		}

		return $best;
	}

	/**
	 * Type names without schema.org prefixes.
	 *
	 * @param array $node Node.
	 * @return string[]
	 */
	private static function types( array $node ) {
		$types = array();

		foreach ( (array) ( isset( $node['@type'] ) ? $node['@type'] : array() ) as $type ) {
			if ( is_string( $type ) ) {
				$types[] = self::strip_prefix( $type );
			}
		}

		return $types;
	}

	/**
	 * Label for choosing between several businesses.
	 *
	 * @param array $node Node.
	 * @return string
	 */
	private static function label( array $node ) {
		$name  = self::text( self::prop( $node, 'name' ) );
		$types = self::types( $node );
		$type  = $types ? Business_Types::label( $types[0] ) : '';

		if ( '' === $name ) {
			return $type;
		}

		return '' !== $type ? $name . ' (' . $type . ')' : $name;
	}

	/**
	 * Whether a sanitized value is empty for its field type.
	 *
	 * @param mixed $clean Sanitized value.
	 * @param array $field Field definition.
	 * @return bool
	 */
	private static function is_blank( $clean, array $field ) {
		if ( 'hours' === $field['type'] ) {
			foreach ( (array) $clean as $row ) {
				if ( 'closed' !== $row['mode'] ) {
					return false;
				}
			}

			return true;
		}

		if ( is_array( $clean ) ) {
			return ! $clean;
		}

		return '' === $clean || 0 === $clean;
	}

	/**
	 * A node property, with @id references resolved.
	 *
	 * @param mixed  $node Node.
	 * @param string $key  Property.
	 * @return mixed
	 */
	private static function prop( $node, $key ) {
		return is_array( $node ) && isset( $node[ $key ] ) ? self::resolve( $node[ $key ] ) : null;
	}

	/**
	 * Replace a bare { "@id": ... } reference with the node it points to.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function resolve( $value ) {
		if ( is_array( $value ) && 1 === count( $value ) && isset( $value['@id'] ) && is_string( $value['@id'] ) && isset( self::$by_id[ $value['@id'] ] ) ) {
			return self::$by_id[ $value['@id'] ];
		}

		return $value;
	}

	/**
	 * A value as a list.
	 *
	 * @param mixed $value Value.
	 * @return array
	 */
	private static function as_list( $value ) {
		if ( null === $value || '' === $value ) {
			return array();
		}

		return is_array( $value ) && self::is_list( $value ) ? $value : array( $value );
	}

	/**
	 * First item of a value that may be a list.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function first( $value ) {
		$list = self::as_list( $value );

		return $list ? self::resolve( reset( $list ) ) : null;
	}

	/**
	 * Plain text from a string, number, @value object, or the first item of a list.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function text( $value ) {
		if ( is_string( $value ) ) {
			return trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( is_array( $value ) && $value ) {
			if ( isset( $value['@value'] ) ) {
				return self::text( $value['@value'] );
			}

			if ( self::is_list( $value ) ) {
				return self::text( reset( $value ) );
			}
		}

		return '';
	}

	/**
	 * Several values joined with commas (e.g. "Cash, Credit Card").
	 *
	 * @param mixed $value Value(s).
	 * @return string
	 */
	private static function joined( $value ) {
		$items = array();

		foreach ( self::as_list( $value ) as $item ) {
			$item = self::resolve( $item );
			$text = is_array( $item ) && ! self::is_list( $item ) ? self::name_of( $item ) : self::text( $item );

			if ( '' !== $text ) {
				$items[] = $text;
			}
		}

		return implode( ', ', $items );
	}

	/**
	 * A list of URLs from strings or nodes with a url.
	 *
	 * @param mixed $value Value(s).
	 * @return string[]
	 */
	private static function urls( $value ) {
		$urls = array();

		foreach ( self::as_list( $value ) as $item ) {
			$url = self::url_of( $item );

			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		return $urls;
	}

	/**
	 * A URL from a string, or the url / contentUrl of a node.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function url_of( $value ) {
		$value = self::first( $value );

		if ( ! is_array( $value ) ) {
			return self::text( $value );
		}

		foreach ( array( 'url', 'contentUrl' ) as $key ) {
			$url = self::text( self::prop( $value, $key ) );

			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * A name from a string or a node's name.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function name_of( $value ) {
		$value = self::first( $value );

		return is_array( $value ) ? self::text( self::prop( $value, 'name' ) ) : self::text( $value );
	}

	/**
	 * An email address without a mailto: prefix.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function email( $value ) {
		return (string) preg_replace( '/^mailto:/i', '', self::text( $value ) );
	}

	/**
	 * HH:MM from "9:00", "09:00:00" or "09:00:00+01:00".
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function time_of( $value ) {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})/', self::text( $value ), $m ) || (int) $m[1] > 24 ) {
			return '';
		}

		return sprintf( '%02d:%s', (int) $m[1], $m[2] );
	}

	/**
	 * Y-m-d from a date or date-time.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function date_of( $value ) {
		return preg_match( '/^\d{4}-\d{2}-\d{2}/', self::text( $value ), $m ) ? $m[0] : '';
	}

	/**
	 * 24:00 is valid in schema.org but not in a time input, so use 23:59.
	 *
	 * @param string $time HH:MM.
	 * @return string
	 */
	private static function before_midnight( $time ) {
		return '24:00' === $time ? '23:59' : $time;
	}

	/**
	 * Remove a schema.org prefix, e.g. "https://schema.org/Dentist" => "Dentist".
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function strip_prefix( $value ) {
		return (string) preg_replace( '#^(?:https?://schema\.org/|schema:)#i', '', trim( (string) $value ) );
	}

	/**
	 * Whether an array is a list (sequential integer keys).
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	private static function is_list( array $value ) {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Record a message for the user, once.
	 *
	 * @param string $message Message.
	 */
	private static function note( $message ) {
		if ( ! in_array( $message, self::$notes, true ) ) {
			self::$notes[] = $message;
		}
	}
}
