<?php
/**
 * Checks a location for missing required and recommended data.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Health check.
 */
final class Health_Check {

	/**
	 * Run all checks.
	 *
	 * @param array  $data   Sanitized location data.
	 * @param string $title  Post title (the name fallback).
	 * @param string $status Post status.
	 * @return array{score: int, items: array[]}
	 */
	public static function run( array $data, $title = '', $status = 'publish' ) {
		$data   = wp_parse_args( $data, Fields::defaults() );
		$items  = array();
		$total  = 0;
		$passed = 0;

		foreach ( Fields::all() as $key => $field ) {
			$priority = isset( $field['priority'] ) ? $field['priority'] : 'optional';

			if ( 'required' !== $priority && 'recommended' !== $priority ) {
				continue;
			}

			// Service-area businesses may hide their street address.
			if ( 'street' === $key && $data['service_area'] ) {
				continue;
			}

			$value  = isset( $data[ $key ] ) ? $data[ $key ] : null;
			$filled = 'name' === $key
				? ( '' !== $data['name'] || '' !== trim( (string) $title ) )
				: self::is_filled( $value, $field );

			++$total;

			if ( $filled ) {
				++$passed;
				continue;
			}

			$items[] = array(
				'status'  => 'required' === $priority ? 'error' : 'warning',
				'section' => $field['section'],
				'message' => sprintf(
					'required' === $priority
						/* translators: %s: field label. */
						? __( '%s is required.', 'schemagic' )
						/* translators: %s: field label. */
						: __( '%s is recommended.', 'schemagic' ),
					$field['label']
				),
			);
		}

		if ( 'pages' === $data['display'] && empty( $data['pages'] ) ) {
			$items[] = array(
				'status'  => 'error',
				'section' => 'display',
				'message' => __( 'Choose at least one page, or this schema won\'t appear anywhere.', 'schemagic' ),
			);
		}

		foreach ( array( 'latitude', 'longitude' ) as $key ) {
			if ( '' !== (string) $data[ $key ] && self::decimals( $data[ $key ] ) < 5 ) {
				$items[] = array(
					'status'  => 'warning',
					'section' => 'address',
					'message' => __( 'Use at least 5 decimal places for latitude and longitude.', 'schemagic' ),
				);
				break;
			}
		}

		if ( $data['service_area'] && empty( $data['area_served'] ) ) {
			$items[] = array(
				'status'  => 'warning',
				'section' => 'address',
				'message' => __( 'List the areas you serve, since the street address is hidden.', 'schemagic' ),
			);
		}

		if ( 'publish' !== $status ) {
			array_unshift(
				$items,
				array(
					'status'  => 'warning',
					'section' => '',
					'message' => __( 'Not published yet. Publish this location to add its schema to your site.', 'schemagic' ),
				)
			);
		}

		// Errors first.
		usort(
			$items,
			static function ( $a, $b ) {
				return ( 'error' === $b['status'] ) - ( 'error' === $a['status'] );
			}
		);

		return array(
			'score' => $total ? (int) floor( $passed / $total * 100 ) : 100,
			'items' => $items,
		);
	}

	/**
	 * Health check result as HTML. Every value is escaped here.
	 *
	 * @param array $result Result of run().
	 * @return string
	 */
	public static function render( array $result ) {
		$score = (int) $result['score'];
		$level = $score >= 90 ? 'good' : ( $score >= 60 ? 'ok' : 'poor' );

		$html  = '<div class="schemagic-health">';
		$html .= '<p class="schemagic-health__score schemagic-health__score--' . esc_attr( $level ) . '">';
		$html .= '<span class="schemagic-health__meter" style="--schemagic-score:' . esc_attr( (string) $score ) . '%"></span>';
		/* translators: %d: percentage of required and recommended fields filled in. */
		$html .= esc_html( sprintf( __( '%d%% complete', 'schemagic' ), $score ) );
		$html .= '</p>';

		if ( ! $result['items'] ) {
			$html .= '<p class="schemagic-health__done">' . esc_html__( 'Everything Google looks for is filled in.', 'schemagic' ) . '</p>';
		} else {
			$html .= '<ul class="schemagic-health__list">';

			foreach ( $result['items'] as $item ) {
				$html .= '<li class="schemagic-health__item is-' . esc_attr( $item['status'] ) . '">';
				$html .= '<span class="schemagic-health__label">' . ( 'error' === $item['status'] ? esc_html__( 'Missing', 'schemagic' ) : esc_html__( 'Tip', 'schemagic' ) ) . '</span> ';
				$html .= esc_html( $item['message'] );

				if ( '' !== $item['section'] ) {
					$html .= ' <a href="#schemagic-panel-' . esc_attr( $item['section'] ) . '" data-schemagic-tab="' . esc_attr( $item['section'] ) . '">' . esc_html__( 'Fix', 'schemagic' ) . '</a>';
				}

				$html .= '</li>';
			}

			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Whether a field has a meaningful value.
	 *
	 * @param mixed $value Value.
	 * @param array $field Field definition.
	 * @return bool
	 */
	private static function is_filled( $value, array $field ) {
		if ( 'hours' === $field['type'] ) {
			foreach ( (array) $value as $row ) {
				if ( isset( $row['mode'] ) && 'closed' !== $row['mode'] ) {
					return true;
				}
			}

			return false;
		}

		if ( is_array( $value ) ) {
			return ! empty( $value );
		}

		if ( 'image' === $field['type'] ) {
			return (int) $value > 0;
		}

		return '' !== trim( (string) $value );
	}

	/**
	 * Number of decimal places in a numeric string.
	 *
	 * @param string $value Number.
	 * @return int
	 */
	private static function decimals( $value ) {
		$pos = strpos( (string) $value, '.' );

		return false === $pos ? 0 : strlen( (string) $value ) - $pos - 1;
	}
}
