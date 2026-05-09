<?php
defined( 'ABSPATH' ) || exit;

class FU_Programs {

    private static string $option = 'fu_programs';

    public static function init(): void {
        // Nothing to hook at boot — data is read on demand.
    }

    // ----------------------------------------------------------------
    // Read
    // ----------------------------------------------------------------

    /** @return array<int, array> */
    public static function all(): array {
        return get_option( self::$option, [] );
    }

    public static function get( string $slug ): ?array {
        foreach ( self::all() as $p ) {
            if ( $p['slug'] === $slug ) return $p;
        }
        return null;
    }

    public static function default(): ?array {
        foreach ( self::all() as $p ) {
            if ( ! empty( $p['default'] ) ) return $p;
        }
        $all = self::all();
        return $all[0] ?? null;
    }

    // ----------------------------------------------------------------
    // Write
    // ----------------------------------------------------------------

    public static function save( array $program ): bool {
        $program = self::sanitize( $program );
        if ( is_wp_error( $program ) ) return false;

        $all  = self::all();
        $found = false;

        foreach ( $all as &$p ) {
            if ( $p['slug'] === $program['slug'] ) {
                $p     = $program;
                $found = true;
                break;
            }
        }
        unset( $p );

        if ( ! $found ) {
            $all[] = $program;
        }

        // Ensure exactly one default.
        if ( ! empty( $program['default'] ) ) {
            foreach ( $all as &$p ) {
                if ( $p['slug'] !== $program['slug'] ) {
                    $p['default'] = false;
                }
            }
            unset( $p );
        }

        return update_option( self::$option, $all );
    }

    public static function delete( string $slug ): bool {
        $all = array_filter( self::all(), fn( $p ) => $p['slug'] !== $slug );
        $all = array_values( $all );

        // If we deleted the default, promote the first remaining.
        $has_default = array_filter( $all, fn( $p ) => ! empty( $p['default'] ) );
        if ( empty( $has_default ) && ! empty( $all ) ) {
            $all[0]['default'] = true;
        }

        return update_option( self::$option, $all );
    }

    public static function set_default( string $slug ): bool {
        $p = self::get( $slug );
        if ( ! $p ) return false;
        $p['default'] = true;
        return self::save( $p );
    }

    // ----------------------------------------------------------------
    // Sanitize
    // ----------------------------------------------------------------

    /** @return array|\WP_Error */
    public static function sanitize( array $raw ) {
        $slug = sanitize_key( $raw['slug'] ?? '' );
        $name = sanitize_text_field( $raw['name'] ?? '' );

        if ( ! $slug ) return new \WP_Error( 'bad_slug', 'Program slug is required.' );
        if ( ! $name ) return new \WP_Error( 'bad_name', 'Program name is required.' );

        $dates = [];
        foreach ( (array) ( $raw['dates'] ?? [] ) as $d ) {
            $d = trim( $d );
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
                $dates[] = $d;
            }
        }

        return [
            'slug'             => $slug,
            'name'             => $name,
            'dates'            => array_values( $dates ),
            'default'          => ! empty( $raw['default'] ),
            'high_price_label' => sanitize_text_field( $raw['high_price_label'] ?? 'Start next Monday' ),
            'high_price_spots' => max( 1, (int) ( $raw['high_price_spots'] ?? 2 ) ),
        ];
    }
}
