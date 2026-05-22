<?php
defined( 'ABSPATH' ) || exit;

class FU_Plugin {

    public static function init() {
        if ( ! is_multisite() ) {
            add_action( 'admin_notices', [ __CLASS__, 'multisite_notice' ] );
            add_action( 'network_admin_notices', [ __CLASS__, 'multisite_notice' ] );
            return;
        }
        FU_Programs::init();
        FU_Shortcodes::init();
        if ( is_admin() ) {
            FU_Admin::init();
        }
    }

    public static function multisite_notice() {
        echo '<div class="notice notice-error"><p>'
            . esc_html__( 'DWC - Spots Left requires WordPress Multisite and is inactive.', 'fitness-urgency' )
            . '</p></div>';
    }

    public static function activate() {
        // This plugin is multisite-only.
        if ( ! is_multisite() ) {
            deactivate_plugins( FU_BASENAME );
            wp_die(
                esc_html__( 'DWC - Spots Left requires WordPress Multisite. Please network-activate it on a multisite install.', 'fitness-urgency' ),
                esc_html__( 'Multisite required', 'fitness-urgency' ),
                [ 'back_link' => true ]
            );
        }

        // Seed default program network-wide if none exist yet.
        if ( ! get_site_option( 'fu_programs' ) ) {
            update_site_option( 'fu_programs', [
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
