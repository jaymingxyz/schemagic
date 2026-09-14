<?php
/**
 * Sanitizes location data using the field registry.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizer.
 */
final class Sanitizer {

	const TIME_PATTERN = '/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/';
	const DATE_PATTERN = '/^(\d{4})-(\d{2})-(\d{2})$/';

	const MAX_LINES         = 50;
	const MAX_RANGES_PER_DAY = 5;
	const MAX_SPECIAL_HOURS = 100;

	/**
	 * Sanitize a full location array. Unknown keys are dropped.
	 *
	 * @param mixed $raw Unslashed input, usually $_POST['schemagic'].
	 * @return array
	 */
	public static function location( $raw ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = Fields::defaults();
		$clean    = array();

		foreach ( Fields::all() as $key => $field ) {
			$value         = array_key_exists( $key, $raw ) ? $raw[ $key ] : null;
			$clean[ $key ] = self::field( $value, $field, $defaults[ $key ] );
		}

		return $clean;
	}

	/**
	 * Sanitize one field value according to its definition.
	 *
	 * @param mixed $value   Raw value.
	 * @param array $field   Field definition.
	 * @param mixed $default Default value.
	 * @return mixed
	 */
	public static function field( $value, array $field, $default ) {
		switch ( $field['type'] ) {
			case 'text':
			case 'tel':
				$clean = sanitize_text_field( self::scalar( $value ) );
				if ( ! empty( $field['maxlength'] ) ) {
					$clean = mb_substr( $clean, 0, (int) $field['maxlength'] );
				}
				return $clean;

			case 'textarea':
				return sanitize_textarea_field( self::scalar( $value ) );

			case 'email':
				$clean = sanitize_email( self::scalar( $value ) );
				return is_email( $clean ) ? $clean : '';

			case 'url':
				return self::url( $value );

			case 'business_type':
				$clean = self::scalar( $value );
				return Business_Types::exists( $clean ) ? $clean : Business_Types::ROOT;

			case 'country':
				$clean = strtoupper( sanitize_text_field( self::scalar( $value ) ) );
				return isset( Fields::countries()[ $clean ] ) ? $clean : '';

			case 'coordinate':
				return self::coordinate(
					$value,
					isset( $field['min'] ) ? (float) $field['min'] : -180.0,
					isset( $field['max'] ) ? (float) $field['max'] : 180.0
				);

			case 'checkbox':
				return empty( $value ) ? 0 : 1;

			case 'select':
			case 'radio':
				$clean = self::scalar( $value );
				return array_key_exists( $clean, (array) $field['options'] ) ? $clean : $default;

			case 'lines':
				return self::lines( $value, 'sanitize_text_field' );

			case 'url_lines':
				return self::lines( $value, array( __CLASS__, 'url' ) );

			case 'image':
				return absint( self::scalar( $value ) );

			case 'gallery':
			case 'pages':
				return self::id_list( $value );

			case 'hours':
				return self::hours( $value );

			case 'special_hours':
				return self::special_hours( $value );
		}

		/**
		 * Sanitize a custom field type registered through the schemagic_fields filter.
		 *
		 * @param string $clean Value run through sanitize_text_field().
		 * @param mixed  $value Raw value.
		 * @param array  $field Field definition.
		 */
		return apply_filters( 'schemagic_sanitize_' . $field['type'], sanitize_text_field( self::scalar( $value ) ), $value, $field );
	}

	/**
	 * An http(s) URL or an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function url( $value ) {
		$value = trim( self::scalar( $value ) );

		if ( '' === $value ) {
			return '';
		}

		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/**
	 * A coordinate within range, kept as a string so its precision is preserved.
	 *
	 * @param mixed $value Raw value.
	 * @param float $min   Minimum.
	 * @param float $max   Maximum.
	 * @return string
	 */
	public static function coordinate( $value, $min, $max ) {
		$value = str_replace( ' ', '', self::scalar( $value ) );

		// Accept a decimal comma, e.g. "51,50735".
		if ( false === strpos( $value, '.' ) ) {
			$value = str_replace( ',', '.', $value );
		}

		if ( ! preg_match( '/^[-+]?\d{1,3}(\.\d{1,10})?$/', $value ) ) {
			return '';
		}

		$number = (float) $value;

		if ( $number < $min || $number > $max ) {
			return '';
		}

		return ltrim( $value, '+' );
	}

	/**
	 * A time as HH:MM, or an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function time( $value ) {
		if ( ! preg_match( self::TIME_PATTERN, trim( self::scalar( $value ) ), $m ) ) {
			return '';
		}

		return $m[1] . ':' . $m[2];
	}

	/**
	 * A date as Y-m-d, or an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function date( $value ) {
		$value = trim( self::scalar( $value ) );

		if ( ! preg_match( self::DATE_PATTERN, $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Newline-separated text (or an array) to a clean, unique list.
	 *
	 * @param mixed    $value    Raw value.
	 * @param callable $callback Per-item sanitizer.
	 * @return string[]
	 */
	private static function lines( $value, $callback ) {
		if ( is_array( $value ) ) {
			$value = implode( "\n", array_map( array( __CLASS__, 'scalar' ), $value ) );
		}

		$items = preg_split( '/\R/', self::scalar( $value ) );
		$items = array_map( 'trim', array_map( $callback, (array) $items ) );
		$items = array_filter( $items, 'strlen' );

		return array_slice( array_values( array_unique( $items ) ), 0, self::MAX_LINES );
	}

	/**
	 * Comma-separated IDs (or an array) to a list of positive integers.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]
	 */
	private static function id_list( $value ) {
		if ( ! is_array( $value ) ) {
			$value = explode( ',', self::scalar( $value ) );
		}

		$ids = array_filter( array_map( 'absint', array_map( array( __CLASS__, 'scalar' ), $value ) ) );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Weekly opening hours.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	private static function hours( $value ) {
		$clean = Fields::default_hours();
		$modes = Fields::hour_modes();

		if ( ! is_array( $value ) ) {
			return $clean;
		}

		foreach ( array_keys( $clean ) as $day ) {
			$row    = isset( $value[ $day ] ) && is_array( $value[ $day ] ) ? $value[ $day ] : array();
			$mode   = isset( $row['mode'] ) ? self::scalar( $row['mode'] ) : 'closed';
			$mode   = isset( $modes[ $mode ] ) ? $mode : 'closed';
			$ranges = array();

			if ( 'open' === $mode && isset( $row['ranges'] ) && is_array( $row['ranges'] ) ) {
				foreach ( $row['ranges'] as $range ) {
					if ( ! is_array( $range ) ) {
						continue;
					}

					$opens  = self::time( isset( $range['opens'] ) ? $range['opens'] : '' );
					$closes = self::time( isset( $range['closes'] ) ? $range['closes'] : '' );

					// Closing earlier than opening is allowed: it means after midnight.
					if ( '' !== $opens && '' !== $closes && $opens !== $closes ) {
						$ranges[] = array(
							'opens'  => $opens,
							'closes' => $closes,
						);
					}
				}

				usort(
					$ranges,
					static function ( $a, $b ) {
						return strcmp( $a['opens'], $b['opens'] );
					}
				);

				$ranges = array_slice( $ranges, 0, self::MAX_RANGES_PER_DAY );
			}

			if ( 'open' === $mode && ! $ranges ) {
				$mode = 'closed';
			}

			$clean[ $day ] = array(
				'mode'   => $mode,
				'ranges' => $ranges,
			);
		}

		return $clean;
	}

	/**
	 * Holiday and special hours.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	private static function special_hours( $value ) {
		$clean = array();

		if ( ! is_array( $value ) ) {
			return $clean;
		}

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$from    = self::date( isset( $row['from'] ) ? $row['from'] : '' );
			$through = self::date( isset( $row['through'] ) ? $row['through'] : '' );

			if ( '' === $from ) {
				continue;
			}

			if ( '' === $through ) {
				$through = $from;
			} elseif ( $through < $from ) {
				list( $from, $through ) = array( $through, $from );
			}

			$closed = empty( $row['closed'] ) ? 0 : 1;
			$opens  = $closed ? '' : self::time( isset( $row['opens'] ) ? $row['opens'] : '' );
			$closes = $closed ? '' : self::time( isset( $row['closes'] ) ? $row['closes'] : '' );

			if ( ! $closed && ( '' === $opens || '' === $closes || $opens === $closes ) ) {
				continue;
			}

			$clean[] = array(
				'from'    => $from,
				'through' => $through,
				'closed'  => $closed,
				'opens'   => $opens,
				'closes'  => $closes,
			);
		}

		usort(
			$clean,
			static function ( $a, $b ) {
				return strcmp( $a['from'], $b['from'] );
			}
		);

		return array_slice( $clean, 0, self::MAX_SPECIAL_HOURS );
	}

	/**
	 * A scalar as a string; anything else as an empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function scalar( $value ) {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
