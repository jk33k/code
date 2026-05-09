<?php
defined( 'ABSPATH' ) || exit;

class FU_Shortcodes {

    public static function init(): void {
        add_shortcode( 'fu_startdate',     [ __CLASS__, 'startdate' ] );
        add_shortcode( 'fu_spotsleft',     [ __CLASS__, 'spotsleft' ] );
        add_shortcode( 'fu_card',          [ __CLASS__, 'card' ] );
        add_shortcode( 'fu_show_if_slot',  [ __CLASS__, 'show_if_slot' ] );
        add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'enqueue_frontend' ] );
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
        if ( ! $renderer ) return '';

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

        $slot_index = max( 1, (int) $atts['slot'] ) - 1;
        if ( ! isset( $slots[ $slot_index ] ) ) return '';

        return do_shortcode( $content );
    }

    // ----------------------------------------------------------------
    // Frontend assets
    // ----------------------------------------------------------------

    public static function enqueue_frontend(): void {
        // Only load on pages that actually contain our shortcodes.
        global $post;
        if ( ! $post ) return;
        $has_sc = false;
        foreach ( [ 'fu_startdate', 'fu_spotsleft', 'fu_card', 'fu_show_if_slot' ] as $sc ) {
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

        // Inline the program data so the JS midnight-refresh script can
        // recompute without an AJAX round-trip.
        $programs_js = [];
        foreach ( FU_Programs::all() as $p ) {
            $programs_js[ $p['slug'] ] = [
                'dates'             => array_values( $p['dates'] ),
                'highPriceMessage'  => $p['high_price_label'] ?? 'Start next Monday',
                'highPriceSpots'    => (int) ( $p['high_price_spots'] ?? 2 ),
            ];
        }

        // Site UTC offset in minutes (wp_timezone() returns a DateTimeZone).
        $now        = new \DateTime( 'now', wp_timezone() );
        $tz_offset  = (int) ( $now->getOffset() / 60 ); // seconds → minutes

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

    /**
     * Resolve [renderer, slots] for given shortcode atts.
     * Returns [null, []] when the program is not found or has no dates.
     *
     * @return array{ FU_Renderer|null, array }
     */
    private static function resolve( array $atts ): array {
        $program = $atts['program']
            ? FU_Programs::get( $atts['program'] )
            : FU_Programs::default();

        if ( ! $program || empty( $program['dates'] ) ) return [ null, [] ];

        $renderer = new FU_Renderer( [
            'timezone'           => wp_timezone_string(),
            'high_price_message' => $program['high_price_label'] ?? 'Start next Monday',
            'high_price_spots'   => $program['high_price_spots'] ?? 2,
        ] );

        $slots = $renderer->compute_slots( $program['dates'] );
        return [ $renderer, $slots ];
    }
}
