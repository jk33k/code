<?php
defined( 'ABSPATH' ) || exit;

class FU_Admin {

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_fu_save',    [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_fu_delete',  [ __CLASS__, 'handle_delete' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function add_menu(): void {
        add_options_page(
            'Fitness Urgency',
            'Fitness Urgency',
            'manage_options',
            'fitness-urgency',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue_scripts( string $hook ): void {
        if ( $hook !== 'settings_page_fitness-urgency' ) return;
        wp_enqueue_style( 'fu-admin', FU_URL . 'assets/css/fu-admin.css', [], FU_VERSION );
    }

    // ----------------------------------------------------------------
    // Main settings page
    // ----------------------------------------------------------------

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $programs  = FU_Programs::all();
        $edit_slug = sanitize_key( $_GET['edit'] ?? '' );
        $editing   = $edit_slug ? FU_Programs::get( $edit_slug ) : null;
        require FU_DIR . 'views/admin-settings.php';
    }

    // ----------------------------------------------------------------
    // Form handlers
    // ----------------------------------------------------------------

    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );
        check_admin_referer( 'fu_save_program' );

        $raw = [
            'slug'             => sanitize_key( $_POST['slug'] ?? '' ),
            'name'             => sanitize_text_field( $_POST['name'] ?? '' ),
            'dates'            => array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['dates'] ?? [] ) ) ),
            'default'          => isset( $_POST['default'] ),
            'high_price_label' => sanitize_text_field( $_POST['high_price_label'] ?? 'Start next Monday' ),
            'high_price_spots' => (int) ( $_POST['high_price_spots'] ?? 2 ),
        ];

        $result = FU_Programs::sanitize( $raw );
        if ( is_wp_error( $result ) ) {
            wp_redirect( add_query_arg( [ 'page' => 'fitness-urgency', 'error' => urlencode( $result->get_error_message() ) ], admin_url( 'options-general.php' ) ) );
            exit;
        }

        FU_Programs::save( $result );
        wp_redirect( add_query_arg( [ 'page' => 'fitness-urgency', 'saved' => 1 ], admin_url( 'options-general.php' ) ) );
        exit;
    }

    public static function handle_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );
        $slug = sanitize_key( $_GET['slug'] ?? '' );
        check_admin_referer( 'fu_delete_' . $slug );
        FU_Programs::delete( $slug );
        wp_redirect( add_query_arg( [ 'page' => 'fitness-urgency', 'deleted' => 1 ], admin_url( 'options-general.php' ) ) );
        exit;
    }
}
