<?php
/**
 * Field registry: the single source of truth for location fields.
 *
 * The edit form, sanitizer, health check and defaults all read from here.
 *
 * @package Schemagic
 */

namespace Schemagic;

defined( 'ABSPATH' ) || exit;

/**
 * Field definitions.
 */
final class Fields {

	/**
	 * Form sections, in tab order.
	 *
	 * @return array<string, array{label: string, show_if_type?: string}>
	 */
	public static function sections() {
		return array(
			'business' => array( 'label' => __( 'Business', 'schemagic' ) ),
			'contact'  => array( 'label' => __( 'Contact', 'schemagic' ) ),
			'address'  => array( 'label' => __( 'Address', 'schemagic' ) ),
			'hours'    => array( 'label' => __( 'Hours', 'schemagic' ) ),
			'images'   => array( 'label' => __( 'Images', 'schemagic' ) ),
			'social'   => array( 'label' => __( 'Social', 'schemagic' ) ),
			'food'     => array(
				'label'        => __( 'Food & drink', 'schemagic' ),
				'show_if_type' => 'FoodEstablishment',
			),
			'display'  => array( 'label' => __( 'Display on', 'schemagic' ) ),
		);
	}

	/**
	 * All field definitions.
	 *
	 * Keys per field:
	 * - label       (string)  Visible label.
	 * - section     (string)  Key from sections().
	 * - type        (string)  text|textarea|tel|email|url|business_type|country|coordinate|checkbox|select|radio|lines|url_lines|image|gallery|hours|special_hours|pages
	 * - priority    (string)  required|recommended|optional. Drives badges and the health check.
	 * - description (string)  Optional help text.
	 * - placeholder (string)  Optional.
	 * - maxlength   (int)     Optional, for text inputs.
	 * - options     (array)   For select/radio.
	 * - default     (mixed)   Optional default value.
	 * - min/max     (float)   For coordinates.
	 *
	 * @return array<string, array>
	 */
	public static function all() {
		$fields = array(
			// Business.
			'type'                 => array(
				'label'       => __( 'Business type', 'schemagic' ),
				'section'     => 'business',
				'type'        => 'business_type',
				'priority'    => 'required',
				'default'     => 'LocalBusiness',
				'description' => __( 'Pick the most specific type that fits. If nothing fits, keep "Local business".', 'schemagic' ),
			),
			'name'                 => array(
				'label'       => __( 'Business name', 'schemagic' ),
				'section'     => 'business',
				'type'        => 'text',
				'priority'    => 'required',
				'description' => __( 'Leave blank to use the title above.', 'schemagic' ),
			),
			'alternate_name'       => array(
				'label'    => __( 'Alternate name', 'schemagic' ),
				'section'  => 'business',
				'type'     => 'text',
				'priority' => 'optional',
			),
			'description'          => array(
				'label'    => __( 'Description', 'schemagic' ),
				'section'  => 'business',
				'type'     => 'textarea',
				'priority' => 'optional',
			),
			'url'                  => array(
				'label'       => __( 'Website URL', 'schemagic' ),
				'section'     => 'business',
				'type'        => 'url',
				'priority'    => 'optional',
				'placeholder' => 'https://',
				'description' => __( 'The page for this location. Leave blank to use the page the schema is shown on.', 'schemagic' ),
			),
			'parent_brand'         => array(
				'label'       => __( 'Parent brand or organization', 'schemagic' ),
				'section'     => 'business',
				'type'        => 'text',
				'priority'    => 'optional',
				'description' => __( 'Leave blank to use the organization name from Settings.', 'schemagic' ),
			),

			// Contact.
			'telephone'            => array(
				'label'       => __( 'Phone number', 'schemagic' ),
				'section'     => 'contact',
				'type'        => 'tel',
				'priority'    => 'recommended',
				'placeholder' => '+1-555-010-2000',
				'description' => __( 'Include the country code.', 'schemagic' ),
			),
			'email'                => array(
				'label'    => __( 'Email', 'schemagic' ),
				'section'  => 'contact',
				'type'     => 'email',
				'priority' => 'optional',
			),
			'price_range'          => array(
				'label'       => __( 'Price range', 'schemagic' ),
				'section'     => 'contact',
				'type'        => 'text',
				'priority'    => 'recommended',
				'placeholder' => '$$',
				'maxlength'   => 100,
				'description' => __( 'For example "$$" or "$10–25".', 'schemagic' ),
			),
			'payment_accepted'     => array(
				'label'       => __( 'Payment accepted', 'schemagic' ),
				'section'     => 'contact',
				'type'        => 'text',
				'priority'    => 'optional',
				'placeholder' => __( 'Cash, Credit Card', 'schemagic' ),
			),
			'currencies_accepted'  => array(
				'label'       => __( 'Currencies accepted', 'schemagic' ),
				'section'     => 'contact',
				'type'        => 'text',
				'priority'    => 'optional',
				'placeholder' => 'USD',
				'description' => __( 'Three-letter currency codes, separated by commas.', 'schemagic' ),
			),

			// Address.
			'street'               => array(
				'label'    => __( 'Street address', 'schemagic' ),
				'section'  => 'address',
				'type'     => 'text',
				'priority' => 'required',
			),
			'locality'             => array(
				'label'    => __( 'City', 'schemagic' ),
				'section'  => 'address',
				'type'     => 'text',
				'priority' => 'required',
			),
			'region'               => array(
				'label'    => __( 'State / region', 'schemagic' ),
				'section'  => 'address',
				'type'     => 'text',
				'priority' => 'recommended',
			),
			'postal_code'          => array(
				'label'    => __( 'Postal code', 'schemagic' ),
				'section'  => 'address',
				'type'     => 'text',
				'priority' => 'recommended',
			),
			'country'              => array(
				'label'    => __( 'Country', 'schemagic' ),
				'section'  => 'address',
				'type'     => 'country',
				'priority' => 'required',
			),
			'latitude'             => array(
				'label'       => __( 'Latitude', 'schemagic' ),
				'section'     => 'address',
				'type'        => 'coordinate',
				'priority'    => 'recommended',
				'min'         => -90,
				'max'         => 90,
				'placeholder' => '39.78172',
				'description' => __( 'Use at least 5 decimal places. Right-click your location in Google Maps to copy it.', 'schemagic' ),
			),
			'longitude'            => array(
				'label'       => __( 'Longitude', 'schemagic' ),
				'section'     => 'address',
				'type'        => 'coordinate',
				'priority'    => 'recommended',
				'min'         => -180,
				'max'         => 180,
				'placeholder' => '-89.65015',
			),
			'map_url'              => array(
				'label'       => __( 'Map link', 'schemagic' ),
				'section'     => 'address',
				'type'        => 'url',
				'priority'    => 'optional',
				'placeholder' => 'https://',
				'description' => __( 'A link to this location on Google Maps, OpenStreetMap or similar.', 'schemagic' ),
			),
			'service_area'         => array(
				'label'       => __( 'Service-area business', 'schemagic' ),
				'section'     => 'address',
				'type'        => 'checkbox',
				'priority'    => 'optional',
				'description' => __( 'I serve customers at their location and don\'t show a street address.', 'schemagic' ),
			),
			'area_served'          => array(
				'label'       => __( 'Areas served', 'schemagic' ),
				'section'     => 'address',
				'type'        => 'lines',
				'priority'    => 'optional',
				'description' => __( 'One town, region or postal code per line.', 'schemagic' ),
			),

			// Hours.
			'hours'                => array(
				'label'       => __( 'Opening hours', 'schemagic' ),
				'section'     => 'hours',
				'type'        => 'hours',
				'priority'    => 'recommended',
				'description' => __( 'Leave every day closed to leave hours out of the schema. Closing times after midnight are fine.', 'schemagic' ),
			),
			'special_hours'        => array(
				'label'       => __( 'Holiday and special hours', 'schemagic' ),
				'section'     => 'hours',
				'type'        => 'special_hours',
				'priority'    => 'optional',
				'description' => __( 'Dates when your hours differ from normal, such as public holidays.', 'schemagic' ),
			),

			// Images.
			'logo'                 => array(
				'label'       => __( 'Logo', 'schemagic' ),
				'section'     => 'images',
				'type'        => 'image',
				'priority'    => 'optional',
				'description' => __( 'Leave blank to use the logo from Settings.', 'schemagic' ),
			),
			'images'               => array(
				'label'       => __( 'Photos', 'schemagic' ),
				'section'     => 'images',
				'type'        => 'gallery',
				'priority'    => 'recommended',
				'description' => __( 'Photos of the business. Google prefers high-resolution images in 16:9, 4:3 and 1:1 shapes.', 'schemagic' ),
			),

			// Social.
			'same_as'              => array(
				'label'       => __( 'Social and profile links', 'schemagic' ),
				'section'     => 'social',
				'type'        => 'url_lines',
				'priority'    => 'optional',
				'placeholder' => "https://www.facebook.com/…\nhttps://www.instagram.com/…",
				'description' => __( 'One link per line. Leave blank to use the links from Settings.', 'schemagic' ),
			),

			// Food & drink.
			'menu_url'             => array(
				'label'       => __( 'Menu URL', 'schemagic' ),
				'section'     => 'food',
				'type'        => 'url',
				'priority'    => 'optional',
				'placeholder' => 'https://',
			),
			'serves_cuisine'       => array(
				'label'       => __( 'Cuisine', 'schemagic' ),
				'section'     => 'food',
				'type'        => 'text',
				'priority'    => 'optional',
				'placeholder' => __( 'Italian, Pizza', 'schemagic' ),
				'description' => __( 'Separate multiple cuisines with commas.', 'schemagic' ),
			),
			'accepts_reservations' => array(
				'label'    => __( 'Accepts reservations', 'schemagic' ),
				'section'  => 'food',
				'type'     => 'select',
				'priority' => 'optional',
				'default'  => '',
				'options'  => array(
					''    => __( 'Not specified', 'schemagic' ),
					'yes' => __( 'Yes', 'schemagic' ),
					'no'  => __( 'No', 'schemagic' ),
				),
			),

			// Display.
			'display'              => array(
				'label'    => __( 'Show this schema on', 'schemagic' ),
				'section'  => 'display',
				'type'     => 'radio',
				'priority' => 'optional',
				'default'  => 'front',
				'options'  => array(
					'front' => __( 'The front page', 'schemagic' ),
					'site'  => __( 'Every page of the site', 'schemagic' ),
					'pages' => __( 'Selected pages only', 'schemagic' ),
				),
			),
			'pages'                => array(
				'label'       => __( 'Pages', 'schemagic' ),
				'section'     => 'display',
				'type'        => 'pages',
				'priority'    => 'optional',
				'description' => __( 'Hold Ctrl (or Cmd) to select more than one page. With several locations, pick the page about each one.', 'schemagic' ),
			),
		);

		/**
		 * Filters the location field definitions.
		 *
		 * @param array $fields Field definitions keyed by field key.
		 */
		return (array) apply_filters( 'schemagic_fields', $fields );
	}

	/**
	 * Default value for every field.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array();

		foreach ( self::all() as $key => $field ) {
			if ( array_key_exists( 'default', $field ) ) {
				$defaults[ $key ] = $field['default'];
				continue;
			}

			switch ( $field['type'] ) {
				case 'checkbox':
				case 'image':
					$defaults[ $key ] = 0;
					break;
				case 'lines':
				case 'url_lines':
				case 'gallery':
				case 'pages':
				case 'special_hours':
					$defaults[ $key ] = array();
					break;
				case 'hours':
					$defaults[ $key ] = self::default_hours();
					break;
				default:
					$defaults[ $key ] = '';
			}
		}

		return $defaults;
	}

	/**
	 * Every day closed with no time ranges.
	 *
	 * @return array
	 */
	public static function default_hours() {
		$hours = array();

		foreach ( array_keys( self::days() ) as $day ) {
			$hours[ $day ] = array(
				'mode'   => 'closed',
				'ranges' => array(),
			);
		}

		return $hours;
	}

	/**
	 * Days of the week, keyed by the value stored in the database.
	 *
	 * @return array<string, array{label: string, schema: string}>
	 */
	public static function days() {
		return array(
			'monday'    => array(
				'label'  => __( 'Monday', 'schemagic' ),
				'schema' => 'Monday',
			),
			'tuesday'   => array(
				'label'  => __( 'Tuesday', 'schemagic' ),
				'schema' => 'Tuesday',
			),
			'wednesday' => array(
				'label'  => __( 'Wednesday', 'schemagic' ),
				'schema' => 'Wednesday',
			),
			'thursday'  => array(
				'label'  => __( 'Thursday', 'schemagic' ),
				'schema' => 'Thursday',
			),
			'friday'    => array(
				'label'  => __( 'Friday', 'schemagic' ),
				'schema' => 'Friday',
			),
			'saturday'  => array(
				'label'  => __( 'Saturday', 'schemagic' ),
				'schema' => 'Saturday',
			),
			'sunday'    => array(
				'label'  => __( 'Sunday', 'schemagic' ),
				'schema' => 'Sunday',
			),
		);
	}

	/**
	 * Hour modes for a weekday.
	 *
	 * @return array<string, string>
	 */
	public static function hour_modes() {
		return array(
			'closed' => __( 'Closed', 'schemagic' ),
			'open'   => __( 'Open', 'schemagic' ),
			'24h'    => __( 'Open 24 hours', 'schemagic' ),
		);
	}

	/**
	 * ISO 3166-1 alpha-2 country codes => names.
	 *
	 * @return array<string, string>
	 */
	public static function countries() {
		static $countries = null;

		if ( null === $countries ) {
			$countries = require SCHEMAGIC_DIR . 'includes/data/countries.php';
		}

		return $countries;
	}
}
