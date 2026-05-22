<?php
defined( 'ABSPATH' ) || exit;

class FU_Shortcodes {

    public static function init(): void {
        add_shortcode( 'fu_startdate',    [ __CLASS__, 'startdate'   ] );
        add_shortcode( 'fu_spotsleft',    [ __CLASS__, 'spotsleft'   ] );
        add_shortcode( 'fu_card',         [ __CLASS__, 'card'        ] );
        add_shortcode( 'fu_show_if_slot', [ __CLASS__, 'show_if_slot'] );
        add_shortcode( 'fu_finaldate',    [ __CLASS__, 'finaldate'   ] );
        add_shortcode( 'fu_show_phase',   [ __CLASS__, 'show_phase'  ] );
        add_shortcode( 'fu_countdown',    [ __CLASS__, 'countdown'   ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend' ] );
    }

    // ----------------------------------------------------------------
    // [fu_startdate slot="1|2" program="slug" class="..."]
    // ----------------------------------------------------------------
    public static function startdate( array $atts ): string {
        $atts = shortcode_atts( [ 'slot' => '1', 'program' => '', 'class' => '' ], $atts );
        [ $renderer, $slots ] = self::resolve( $atts );
        if ( ! $renderer ) return '';

        $vars = $renderer->compute_variables( $slots );
        $key  = 'startdate' . max( 1, (int) $atts['slot'] );
        $v    = $vars[ $key ] ?? [ 'visible' => false ];

        if ( ! $v['visible'] ) return '';

        $classes = array_filter( [
            'fu-var',
            'fu-startdate',
            ! empty( $v['sold_out']   ) ? 'fu-sold-out'   : '',
            ! empty( $v['high_price'] ) ? 'fu-high-price'  : '',
            sanitize_html_class( $atts['class'] ),
        ] );
        $program_slug = $atts['program'] ?: ( FU_Programs::default()['slug'] ?? '' );

        return sprintf(
            '<span class="%s" data-fu-var="startdate%d" data-fu-program="%s">%s</span>',
            esc_attr( implode( ' ', $classes ) ),
            max( 1, (int) $atts['slot'] ),
            esc_attr( $program_slug ),
            esc_html( $v['text'] )
        );
    }

    // ----------------------------------------------------------------
    // [fu_spotsleft slot="1|2" program="slug" class="..."]
    // ----------------------------------------------------------------
    public static function spotsleft( array $atts ): string {
        $atts = shortcode_atts( [ 'slot' => '1', 'program' => '', 'class' => '' ], $atts );
        [ $renderer, $slots ] = self::resolve( $atts );
        if ( ! $renderer ) return '';

        $vars = $renderer->compute_variables( $slots );
        $key  = 'spotsleft' . max( 1, (int) $atts['slot'] );
        $v    = $vars[ $key ] ?? [ 'visible' => false ];

        if ( ! $v['visible'] ) return '';

        $classes = array_filter( [
            'fu-var',
            'fu-spotsleft',
            ! empty( $v['sold_out']   ) ? 'fu-sold-out'   : '',
            ! empty( $v['high_price'] ) ? 'fu-high-price'  : '',
            sanitize_html_class( $atts['class'] ),
        ] );
        $program_slug = $atts['program'] ?: ( FU_Programs::default()['slug'] ?? '' );

        return sprintf(
            '<span class="%s" data-fu-var="spotsleft%d" data-fu-program="%s">%s</span>',
            esc_attr( implode( ' ', $classes ) ),
            max( 1, (int) $atts['slot'] ),
            esc_attr( $program_slug ),
            esc_html( $v['text'] )
        );
    }

    // ----------------------------------------------------------------
    // [fu_card program="slug" class="..."]
    // ----------------------------------------------------------------
    public static function card( array $atts ): string {
        $atts = shortcode_atts( [ 'program' => '', 'class' => '' ], $atts );
        [ $renderer, $slots ] = self::resolve( $atts );
        if ( ! $renderer || empty( $slots ) ) return '';

        ob_start();
        require FU_DIR . 'views/card.php';
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [fu_show_if_slot slot="2" program="slug"]content[/fu_show_if_slot]
    // ----------------------------------------------------------------
    public static function show_if_slot( array $atts, ?string $content = '' ): string {
        $atts = shortcode_atts( [ 'slot' => '2', 'program' => '' ], $atts );
        [ $renderer, $slots ] = self::resolve( $atts );
        if ( ! $renderer ) return '';

        $slot_index   = max( 1, (int) $atts['slot'] ) - 1;
        $visible      = isset( $slots[ $slot_index ] );
        $program_slug = $atts['program'] ?: ( FU_Programs::default()['slug'] ?? '' );

        return sprintf(
            '<div data-fu-show-if-slot="%d" data-fu-program="%s" style="%s">%s</div>',
            max( 1, (int) $atts['slot'] ),
            esc_attr( $program_slug ),
            $visible ? '' : 'display:none',
            do_shortcode( $content )
        );
    }

    // ----------------------------------------------------------------
    // [fu_finaldate program="slug" class="..."]
    // Renders the last program date as "June 29th" — no weekday.
    // ----------------------------------------------------------------
    public static function finaldate( array $atts ): string {
        $atts    = shortcode_atts( [ 'program' => '', 'class' => '' ], $atts );
        $program = self::program_from_atts( $atts );
        if ( self::mode_of( $program ) === 'ongoing' ) return '';
        if ( ! $program || empty( $program['dates'] ) ) return '';

        $renderer     = new FU_Renderer( [ 'timezone' => wp_timezone_string() ] );
        $last         = $renderer->parse_iso_date( end( $program['dates'] ) );
        $program_slug = $atts['program'] ?: ( $program['slug'] ?? '' );

        $classes = array_filter( [
            'fu-var',
            'fu-finaldate',
            sanitize_html_class( $atts['class'] ),
        ] );

        return sprintf(
            '<span class="%s" data-fu-var="finaldate" data-fu-program="%s">%s</span>',
            esc_attr( implode( ' ', $classes ) ),
            esc_attr( $program_slug ),
            esc_html( $renderer->format_date_short( $last ) )
        );
    }

    // ----------------------------------------------------------------
    // [fu_show_phase phase="high_demand|final_week" program="slug"]
    //   content
    // [/fu_show_phase]
    // ----------------------------------------------------------------
    public static function show_phase( array $atts, ?string $content = '' ): string {
        $atts    = shortcode_atts( [ 'phase' => '', 'program' => '' ], $atts );
        $program = self::program_from_atts( $atts );
        if ( self::mode_of( $program ) === 'ongoing' ) return '';
        if ( ! $program || empty( $program['dates'] ) ) return '';

        $renderer      = new FU_Renderer( [ 'timezone' => wp_timezone_string() ] );
        $current_phase = $renderer->compute_phase( $program['dates'] );
        $visible       = ( $current_phase === $atts['phase'] );
        $program_slug  = $atts['program'] ?: ( $program['slug'] ?? '' );

        return sprintf(
            '<div data-fu-phase="%s" data-fu-program="%s" style="%s">%s</div>',
            esc_attr( $atts['phase'] ),
            esc_attr( $program_slug ),
            $visible ? '' : 'display:none',
            do_shortcode( $content )
        );
    }

    // ----------------------------------------------------------------
    // [fu_countdown program="slug" class="..."]
    // Live D/H/M/S countdown to midnight before the final date.
    // ----------------------------------------------------------------
    public static function countdown( array $atts ): string {
        $atts    = shortcode_atts( [ 'program' => '', 'class' => '' ], $atts );
        $program = self::program_from_atts( $atts );
        if ( self::mode_of( $program ) === 'ongoing' ) return '';
        if ( ! $program || empty( $program['dates'] ) ) return '';

        $renderer     = new FU_Renderer( [ 'timezone' => wp_timezone_string() ] );
        $target_ts    = $renderer->countdown_target_timestamp( $program['dates'] );
        $program_slug = $atts['program'] ?: ( $program['slug'] ?? '' );
        $remaining    = max( 0, $target_ts - time() );

        $days    = (int) floor( $remaining / 86400 );
        $hours   = (int) floor( ( $remaining % 86400 ) / 3600 );
        $minutes = (int) floor( ( $remaining % 3600 ) / 60 );
        $seconds = (int) ( $remaining % 60 );

        $classes = array_filter( [ 'fu-countdown', sanitize_html_class( $atts['class'] ) ] );

        return sprintf(
            '<span class="%s" data-fu-countdown="%s" data-fu-program="%s">'
                . '<span class="fu-cd-days">%02d</span><span class="fu-cd-label">d </span>'
                . '<span class="fu-cd-hours">%02d</span><span class="fu-cd-label">h </span>'
                . '<span class="fu-cd-minutes">%02d</span><span class="fu-cd-label">m </span>'
                . '<span class="fu-cd-seconds">%02d</span><span class="fu-cd-label">s</span>'
            . '</span>',
            esc_attr( implode( ' ', $classes ) ),
            esc_attr( (string) $target_ts ),
            esc_attr( $program_slug ),
            $days,
            $hours,
            $minutes,
            $seconds
        );
    }

    // ----------------------------------------------------------------
    // Frontend assets
    // ----------------------------------------------------------------

    public static function enqueue_frontend(): void {
        global $post;
        if ( ! $post ) return;
        $has_sc = false;
        $all_sc = [ 'fu_startdate', 'fu_spotsleft', 'fu_card', 'fu_show_if_slot',
                    'fu_finaldate', 'fu_show_phase', 'fu_countdown' ];
        foreach ( $all_sc as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) { $has_sc = true; break; }
        }
        if ( ! $has_sc ) return;

        wp_enqueue_style(
            'fitness-urgency',
            FU_URL . 'assets/css/fitness-urgency.css',
            [],
            FU_VERSION
        );
        wp_enqueue_script(
            'fitness-urgency',
            FU_URL . 'assets/js/fu-refresh.js',
            [],
            FU_VERSION,
            true
        );

        $programs_js = [];
        foreach ( FU_Programs::all() as $p ) {
            $programs_js[ $p['slug'] ] = [
                'dates'            => array_values( $p['dates'] ?? [] ),
                'evergreen'        => ! empty( $p['evergreen'] ),
                'highPriceMessage' => $p['high_price_label'] ?? 'Start next Monday',
                'highPriceSpots'   => (int) ( $p['high_price_spots'] ?? 2 ),
            ];
        }

        $now       = new \DateTime( 'now', wp_timezone() );
        $tz_offset = (int) ( $now->getOffset() / 60 );

        wp_add_inline_script(
            'fitness-urgency',
            'var fuRefreshData = ' . wp_json_encode( [
                'programs' => $programs_js,
                'tzOffset' => $tz_offset,
            ] ) . ';',
            'before'
        );
    }

    // ----------------------------------------------------------------
    // Internal
    // ----------------------------------------------------------------

    /** Fetch the program for the given atts, or null. */
    private static function program_from_atts( array $atts ): ?array {
        return $atts['program']
            ? FU_Programs::get( $atts['program'] )
            : FU_Programs::default();
    }

    /** Display mode for a program (or 'fixed' when null). */
    private static function mode_of( ?array $program ): string {
        if ( ! $program ) {
            return 'fixed';
        }
        return FU_Renderer::resolve_mode(
            array_values( $program['dates'] ?? [] ),
            ! empty( $program['evergreen'] )
        );
    }

    /**
     * @return array{ FU_Renderer|null, array }
     */
    private static function resolve( array $atts ): array {
        $program = self::program_from_atts( $atts );
        if ( ! $program ) {
            return [ null, [] ];
        }

        $mode  = self::mode_of( $program );
        $dates = array_values( $program['dates'] ?? [] );

        // Non-ongoing modes need at least one configured date.
        if ( $mode !== 'ongoing' && empty( $dates ) ) {
            return [ null, [] ];
        }

        $renderer = new FU_Renderer( [
            'timezone'           => wp_timezone_string(),
            'high_price_message' => $program['high_price_label'] ?? 'Start next Monday',
            'high_price_spots'   => $program['high_price_spots'] ?? 2,
        ] );

        if ( $mode === 'ongoing' ) {
            $dates = $renderer->upcoming_mondays( 5 );
        }
        $max_lead = ( $mode === 'single' ) ? 21 : null;

        $slots = $renderer->compute_slots( $dates, null, $max_lead );
        return [ $renderer, $slots ];
    }
}
