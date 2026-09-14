<?php
/**
 * Plugin Name:       Schemagic
 * Plugin URI:        https://github.com/jaymingxyz/schemagic
 * Description:       Add LocalBusiness structured data (JSON-LD) to your site by filling in a simple form. Every feature is free.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jay
 * Author URI:        https://github.com/jaymingxyz/schemagic
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       schemagic
 * Domain Path:       /languages
 *
 * @package Schemagic
 */

defined( 'ABSPATH' ) || exit;

define( 'SCHEMAGIC_VERSION', '0.1.0' );
define( 'SCHEMAGIC_FILE', __FILE__ );
define( 'SCHEMAGIC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCHEMAGIC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload classes in the Schemagic namespace.
 *
 * Schemagic\Schema_Builder => includes/class-schema-builder.php
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Schemagic\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$name = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
		$file = SCHEMAGIC_DIR . 'includes/class-' . $name . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

Schemagic\Plugin::init();
