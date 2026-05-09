<?php
defined( 'ABSPATH' ) || exit;

class FU_Plugin {

    public static function init() {
        FU_Programs::init();
        FU_Shortcodes::init();
        if ( is_admin() ) {
            FU_Admin::init();
        }
    }

    public static function activate() {
        // Seed default program if none exist yet.
        if ( ! get_option( 'fu_programs' ) ) {
            update_option( 'fu_programs', [
                [
                    'slug'    => 'default',
                    'name'    => 'My Fitness Program',
                    'dates'   => [],
                    'default' => true,
                ],
            ] );
        }
    }
}
