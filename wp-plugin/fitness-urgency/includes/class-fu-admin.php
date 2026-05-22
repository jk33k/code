<?php
defined( 'ABSPATH' ) || exit;

class FU_Admin {

    private static string $hook = '';

    public static function init(): void {
        add_action( 'network_admin_menu',           [ __CLASS__, 'add_menu' ] );
        add_action( 'network_admin_edit_fu_save',   [ __CLASS__, 'handle_save' ] );
        add_action( 'network_admin_edit_fu_delete', [ __CLASS__, 'handle_delete' ] );
        add_action( 'admin_enqueue_scripts',        [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function add_menu(): void {
        self::$hook = (string) add_submenu_page(
            'settings.php',
            'DWC - Spots Left',
            'DWC - Spots Left',
            'manage_network_options',
            'fitness-urgency',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue_scripts( string $hook ): void {
        if ( $hook !== self::$hook ) return;
        wp_enqueue_style( 'fu-admin', FU_URL . 'assets/css/fu-admin.css', [], FU_VERSION );
    }

    // ----------------------------------------------------------------
    // Main settings page
    // ----------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) return;

        $edit_slug = sanitize_key( wp_unslash( $_GET['edit'] ?? '' ) );
        if ( $edit_slug && ! wp_verify_nonce( wp_unslash( $_GET['_fu_edit'] ?? '' ), 'fu_edit_' . $edit_slug ) ) {
            $edit_slug = '';
        }

        $programs = FU_Programs::all();
        $editing  = $edit_slug ? FU_Programs::get( $edit_slug ) : null;
        require FU_DIR . 'views/admin-settings.php';
    }

    /** Network-admin settings URL with optional extra query args. */
    private static function page_url( array $args = [] ): string {
        return add_query_arg(
            array_merge( [ 'page' => 'fitness-urgency' ], $args ),
            network_admin_url( 'settings.php' )
        );
    }

    // ----------------------------------------------------------------
    // Form handlers
    // ----------------------------------------------------------------

    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) wp_die( 'Unauthorised' );
        check_admin_referer( 'fu_save_program' );

        $raw = [
            'slug'             => sanitize_key( $_POST['slug'] ?? '' ),
            'name'             => sanitize_text_field( $_POST['name'] ?? '' ),
            'schedule'         => sanitize_text_field( wp_unslash( $_POST['schedule'] ?? '' ) ),
            'dates'            => array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['dates'] ?? [] ) ) ),
            'default'          => isset( $_POST['default'] ),
            'high_price_label' => sanitize_text_field( $_POST['high_price_label'] ?? 'Start next Monday' ),
            'high_price_spots' => (int) ( $_POST['high_price_spots'] ?? 2 ),
        ];

        $result = FU_Programs::sanitize( $raw );
        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( self::page_url( [ 'error' => rawurlencode( $result->get_error_message() ) ] ) );
            exit;
        }

        FU_Programs::save( $result );
        wp_safe_redirect( self::page_url( [ 'saved' => 1 ] ) );
        exit;
    }

    public static function handle_delete(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) wp_die( 'Unauthorised' );
        $slug = sanitize_key( wp_unslash( $_GET['slug'] ?? '' ) );
        check_admin_referer( 'fu_delete_' . $slug );
        FU_Programs::delete( $slug );
        wp_safe_redirect( self::page_url( [ 'deleted' => 1 ] ) );
        exit;
    }
}
