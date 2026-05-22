<?php
define( 'ABSPATH', true );       // satisfy the ABSPATH guard in class files
define( 'FU_TESTING', true );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/class-fu-renderer.php';

// ---------------------------------------------------------------------
// Minimal WordPress stubs for unit-testing FU_Programs in isolation.
// Backed by an in-memory network-option store.
// ---------------------------------------------------------------------
$GLOBALS['fu_test_site_options'] = [];

if ( ! function_exists( 'get_site_option' ) ) {
    function get_site_option( $key, $default = false ) {
        return $GLOBALS['fu_test_site_options'][ $key ] ?? $default;
    }
}
if ( ! function_exists( 'update_site_option' ) ) {
    function update_site_option( $key, $value ) {
        $GLOBALS['fu_test_site_options'][ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $message;
        public function __construct( $code = '', $message = '' ) { $this->message = $message; }
        public function get_error_message() { return $this->message; }
    }
}

require_once __DIR__ . '/../includes/class-fu-programs.php';
