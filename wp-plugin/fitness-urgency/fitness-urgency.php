<?php
/**
 * Plugin Name:       Fitness Urgency
 * Description:       Automatic "spots left" countdown for fitness program landing pages. Shows the next 1–2 Monday start dates with a shrinking spot count, then switches to a high-price message after the final date passes. Supports multiple programs via shortcodes.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * Text Domain:       fitness-urgency
 */

defined( 'ABSPATH' ) || exit;

define( 'FU_VERSION',  '1.0.0' );
define( 'FU_DIR',      plugin_dir_path( __FILE__ ) );
define( 'FU_URL',      plugin_dir_url( __FILE__ ) );
define( 'FU_BASENAME', plugin_basename( __FILE__ ) );

// Simple autoloader — maps class FU_Foo_Bar -> includes/class-fu-foo-bar.php
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'FU_' ) !== 0 ) {
        return;
    }
    $file = FU_DIR . 'includes/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

register_activation_hook( __FILE__, [ 'FU_Plugin', 'activate' ] );

add_action( 'plugins_loaded', [ 'FU_Plugin', 'init' ] );
