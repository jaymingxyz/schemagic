<?php
/**
 * [schemagic] shortcode: shows business details visibly on a page, so what
 * visitors see matches the structured data.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode.
 */
final class Shortcode {

	const TAG = 'schemagic';

	/**
	 * Register the shortcode.
	 */
	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * Usage: [schemagic show="name,address,phone,email,hours" location="12"]
	 * show defaults to "all"; location defaults to the location shown on the
	 * current page, or the first published location.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => 0,
				'show'     => 'all',
			),
			$atts,
			self::TAG
		);

		$id = absint( $atts['location'] );
		$id = $id ? $id : self::default_location();

		if ( ! $id || Location_Post_Type::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}

		$data = Location_Post_Type::get_data( $id );
		$show = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $atts['show'] ) ) ) );

		if ( ! $show || in_array( 'all', $show, true ) ) {
			$show = array( 'name', 'address', 'phone', 'email', 'hours' );
		}

		$parts = array();

		foreach ( $show as $part ) {
			$html = self::part( $part, $data, $id );

			if ( '' !== $html ) {
				$parts[] = '<div class="schemagic-info__row schemagic-info__row--' . esc_attr( $part ) . '">' . $html . '</div>';
			}
		}

		return $parts ? '<div class="schemagic-info">' . implode( '', $parts ) . '</div>' : '';
	}

	/**
	 * HTML for one part.
	 *
	 * @param string $part Part name.
	 * @param array  $data Location data.
	 * @param int    $id   Location ID.
	 * @return string
	 */
	private static function part( $part, array $data, $id ) {
		switch ( $part ) {
			case 'name':
				$name = '' !== $data['name'] ? $data['name'] : get_the_title( $id );
				return '' !== $name ? '<strong class="schemagic-info__name">' . esc_html( $name ) . '</strong>' : '';

			case 'address':
				return self::address( $data );

			case 'phone':
				if ( '' === $data['telephone'] ) {
					return '';
				}
				return sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $data['telephone'] ), array( 'tel' ) ),
					esc_html( $data['telephone'] )
				);

			case 'email':
				if ( '' === $data['email'] ) {
					return '';
				}
				$email = antispambot( $data['email'] );
				return sprintf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $email, array( 'mailto' ) ), esc_html( $email ) );

			case 'hours':
				return self::hours( $data['hours'] );
		}

		return '';
	}

	/**
	 * Address block.
	 *
	 * @param array $data Location data.
	 * @return string
	 */
	private static function address( array $data ) {
		$countries = Fields::countries();
		$city_line = trim( implode( ' ', array_filter( array( trim( $data['locality'] . ( '' !== $data['region'] ? ', ' . $data['region'] : '' ), ', ' ), $data['postal_code'] ), 'strlen' ) ) );
		$lines     = array_filter(
			array(
				$data['service_area'] ? '' : $data['street'],
				$city_line,
				isset( $countries[ $data['country'] ] ) ? $countries[ $data['country'] ] : '',
			),
			'strlen'
		);

		if ( ! $lines ) {
			return '';
		}

		return '<address class="schemagic-info__address">' . implode( '<br />', array_map( 'esc_html', $lines ) ) . '</address>';
	}

	/**
	 * Weekly hours table.
	 *
	 * @param array $hours Hours keyed by day.
	 * @return string
	 */
	private static function hours( array $hours ) {
		$rows = '';
		$open = false;

		foreach ( Fields::days() as $day => $info ) {
			$row  = isset( $hours[ $day ] ) ? $hours[ $day ] : array( 'mode' => 'closed' );
			$mode = isset( $row['mode'] ) ? $row['mode'] : 'closed';

			if ( '24h' === $mode ) {
				$text = __( 'Open 24 hours', 'schemagic' );
				$open = true;
			} elseif ( 'open' === $mode && ! empty( $row['ranges'] ) ) {
				$text = implode(
					', ',
					array_map(
						static function ( $range ) {
							return self::format_time( $range['opens'] ) . ' – ' . self::format_time( $range['closes'] );
						},
						$row['ranges']
					)
				);
				$open = true;
			} else {
				$text = __( 'Closed', 'schemagic' );
			}

			$rows .= '<tr><th scope="row">' . esc_html( $info['label'] ) . '</th><td>' . esc_html( $text ) . '</td></tr>';
		}

		// Nothing set: show nothing rather than "Closed" every day.
		if ( ! $open ) {
			return '';
		}

		return '<table class="schemagic-info__hours">' . $rows . '</table>';
	}

	/**
	 * Format HH:MM using the site's time format.
	 *
	 * @param string $time Time.
	 * @return string
	 */
	private static function format_time( $time ) {
		$timestamp = strtotime( '1970-01-01 ' . $time . ':00 UTC' );

		if ( false === $timestamp ) {
			return $time;
		}

		return (string) wp_date( get_option( 'time_format' ), $timestamp, new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * The location shown on this page, or the first published one.
	 *
	 * @return int
	 */
	private static function default_location() {
		$ids = Location_Post_Type::published_ids();

		foreach ( $ids as $id ) {
			$data = Location_Post_Type::get_data( $id );

			if ( 'site' !== $data['display'] && Output::matches( $data ) ) {
				return $id;
			}
		}

		return $ids ? $ids[0] : 0;
	}
}
