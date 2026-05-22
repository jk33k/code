<?php
/**
 * Pure-PHP port of the fitness-urgency.js slot/variable logic.
 * No WordPress dependencies — safe to unit-test without WP bootstrap.
 *
 * All date arithmetic uses midnight in the site's local timezone so
 * every visitor sees the same numbers regardless of their own timezone.
 */

defined( 'ABSPATH' ) || ( defined( 'FU_TESTING' ) || exit );

class FU_Renderer {

    // ----------------------------------------------------------------
    // Configuration (override via constructor options)
    // ----------------------------------------------------------------
    public string $high_price_message;
    public int    $high_price_spots;
    private string $locale;
    /** @var DateTimeZone */
    private \DateTimeZone $tz;

    public function __construct( array $opts = [] ) {
        $this->high_price_message = $opts['high_price_message'] ?? 'Start next Monday';
        $this->high_price_spots   = $opts['high_price_spots']   ?? 2;
        $this->locale             = $opts['locale']             ?? 'en_US';
        $tz_string                = $opts['timezone']           ?? 'UTC';
        try {
            $this->tz = new \DateTimeZone( $tz_string );
        } catch ( \Exception $e ) {
            $this->tz = new \DateTimeZone( 'UTC' );
        }
    }

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    /**
     * Compute 1–2 slot objects for the given date strings and reference date.
     *
     * @param  string[]         $date_strings  ISO dates 'YYYY-MM-DD'
     * @param  \DateTimeInterface|null $today  Defaults to now in site TZ
     * @return array<int, array>               1 or 2 slot arrays
     */
    public function compute_slots( array $date_strings, ?\DateTimeInterface $today = null ): array {
        $today   = $this->start_of_day( $today ?? new \DateTime( 'now', $this->tz ) );
        $dates   = array_map( [ $this, 'parse_iso_date' ], $date_strings );

        $slot1_index = -1;
        foreach ( $dates as $i => $d ) {
            if ( $this->days_between( $today, $d ) >= -1 ) {
                $slot1_index = $i;
                break;
            }
        }

        if ( $slot1_index === -1 ) {
            return [ [ 'type' => 'high_price' ] ];
        }

        $slot1_date = $dates[ $slot1_index ];
        $slot1_t    = $this->days_between( $today, $slot1_date );
        $slot1      = [
            'type'       => 'date',
            'date'       => $slot1_date,
            'days_until' => $slot1_t,
            'spots'      => $this->spots_for_days_until( $slot1_t ),
        ];

        $slot2_date = $dates[ $slot1_index + 1 ] ?? null;
        if ( $slot2_date ) {
            $slot2_t = $this->days_between( $today, $slot2_date );
            return [
                $slot1,
                [
                    'type'       => 'date',
                    'date'       => $slot2_date,
                    'days_until' => $slot2_t,
                    'spots'      => $this->spots_for_days_until( $slot2_t ),
                ],
            ];
        }

        if ( $slot1['spots']['sold_out'] ?? false ) {
            return [ $slot1, [ 'type' => 'high_price' ] ];
        }
        return [ $slot1 ];
    }

    /**
     * Map slots to named variables: startdate1/2, spotsleft1/2.
     * Each value: [ visible, text, sold_out, high_price ]
     */
    public function compute_variables( array $slots ): array {
        $vars = [];
        for ( $i = 0; $i < 2; $i++ ) {
            $n    = $i + 1;
            $slot = $slots[ $i ] ?? null;
            if ( ! $slot ) {
                $vars[ "startdate{$n}" ] = [ 'visible' => false ];
                $vars[ "spotsleft{$n}" ] = [ 'visible' => false ];
                continue;
            }
            if ( $slot['type'] === 'high_price' ) {
                $vars[ "startdate{$n}" ] = [ 'visible' => true, 'text' => $this->high_price_message, 'high_price' => true ];
                $vars[ "spotsleft{$n}" ] = [ 'visible' => true, 'text' => $this->spots_label( $this->high_price_spots ), 'high_price' => true ];
            } else {
                $vars[ "startdate{$n}" ] = [ 'visible' => true, 'text' => $this->format_date( $slot['date'] ) ];
                $spots = $slot['spots'];
                $vars[ "spotsleft{$n}" ] = $spots['sold_out'] ?? false
                    ? [ 'visible' => true, 'text' => 'SOLD OUT', 'sold_out' => true ]
                    : [ 'visible' => true, 'text' => $this->spots_label( $spots['count'] ) ];
            }
        }
        return $vars;
    }

    /**
     * Resolve the display mode from stored config.
     *   evergreen        -> 'ongoing'
     *   1 date           -> 'single'
     *   2 dates          -> 'double'
     *   3 dates          -> 'triple'
     *   0 or 4+ dates    -> 'fixed'
     */
    public static function resolve_mode( array $dates, bool $evergreen ): string {
        if ( $evergreen ) {
            return 'ongoing';
        }
        switch ( count( $dates ) ) {
            case 1:  return 'single';
            case 2:  return 'double';
            case 3:  return 'triple';
            default: return 'fixed';
        }
    }

    /**
     * Generate `count` consecutive Monday ISO dates, starting from the Monday
     * on or before `today` (Monday of the current ISO week). Used by ongoing
     * mode so new Mondays roll in indefinitely.
     *
     * @return string[] ISO 'YYYY-MM-DD'
     */
    public function upcoming_mondays( int $count, ?\DateTimeInterface $today = null ): array {
        $today  = $this->start_of_day( $today ?? new \DateTime( 'now', $this->tz ) );
        $dow    = (int) $today->format( 'N' );          // Mon=1 … Sun=7
        $anchor = $today->modify( '-' . ( $dow - 1 ) . ' days' );

        $out = [];
        for ( $i = 0; $i < $count; $i++ ) {
            $out[] = $anchor->modify( '+' . ( $i * 7 ) . ' days' )->format( 'Y-m-d' );
        }
        return $out;
    }

    // ----------------------------------------------------------------
    // Spots table
    // ----------------------------------------------------------------

    /**
     * T = days until start date.
     *   T=15,14->9  T=13,12->8  T=11,10->7  T=9,8->6
     *   T=7->5  T=6->4  T=5->3  T=4->2  T=3,2->1
     *   T=1,0,-1->SOLD OUT
     */
    public function spots_for_days_until( int $t ): array {
        if ( $t < -1 ) return [ 'past' => true ];
        if ( $t <= 1  ) return [ 'sold_out' => true ];
        if ( $t <= 3  ) return [ 'count' => 1 ];
        if ( $t === 4 ) return [ 'count' => 2 ];
        if ( $t === 5 ) return [ 'count' => 3 ];
        if ( $t === 6 ) return [ 'count' => 4 ];
        if ( $t === 7 ) return [ 'count' => 5 ];
        return [ 'count' => 5 + (int) ceil( ( $t - 7 ) / 2 ) ];
    }

    // ----------------------------------------------------------------
    // Date helpers
    // ----------------------------------------------------------------

    public function parse_iso_date( string $s ): \DateTimeImmutable {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
            throw new \InvalidArgumentException( "Invalid ISO date: {$s}" );
        }
        $dt = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            "{$s} 00:00:00",
            $this->tz
        );
        if ( ! $dt ) {
            throw new \InvalidArgumentException( "Unparseable ISO date: {$s}" );
        }
        return $dt;
    }

    public function start_of_day( \DateTimeInterface $dt ): \DateTimeImmutable {
        return \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $dt->format( 'Y-m-d' ) . ' 00:00:00',
            $this->tz
        );
    }

    public function days_between( \DateTimeInterface $from, \DateTimeInterface $to ): int {
        $f = $this->start_of_day( $from );
        $t = $this->start_of_day( $to );
        return (int) round( ( $t->getTimestamp() - $f->getTimestamp() ) / 86400 );
    }

    public function format_date( \DateTimeInterface $d ): string {
        $day = (int) $d->format( 'j' );
        return $d->format( 'l, F ' ) . $day . $this->ordinal_suffix( $day );
    }

    public function format_date_short( \DateTimeInterface $d ): string {
        $day = (int) $d->format( 'j' );
        return $d->format( 'F ' ) . $day . $this->ordinal_suffix( $day );
    }

    public function spots_label( int $n ): string {
        return $n === 1 ? '1 spot left' : "{$n} spots left";
    }

    // ----------------------------------------------------------------
    // Phase helpers
    // ----------------------------------------------------------------

    /**
     * Returns the current urgency phase for the given program dates:
     *   'high_demand' — D1+2 ≤ today ≤ Dlast-9
     *   'final_week'  — Dlast-8 ≤ today ≤ Dlast-2
     *   null          — outside both windows
     */
    public function compute_phase( array $date_strings, ?\DateTimeInterface $today = null ): ?string {
        if ( empty( $date_strings ) ) return null;

        $today   = $this->start_of_day( $today ?? new \DateTime( 'now', $this->tz ) );
        $first   = $this->parse_iso_date( $date_strings[0] );
        $last    = $this->parse_iso_date( $date_strings[ count( $date_strings ) - 1 ] );
        $t_first = $this->days_between( $today, $first );
        $t_last  = $this->days_between( $today, $last );

        if ( $t_first <= -2 && $t_last >= 9 ) return 'high_demand';
        if ( $t_last >= 2 && $t_last <= 8   ) return 'final_week';
        return null;
    }

    /**
     * Unix timestamp (UTC) of midnight (site-local) on the day before the last date.
     * The live [fu_countdown] ticks down to this moment.
     */
    public function countdown_target_timestamp( array $date_strings ): int {
        $last   = $this->parse_iso_date( $date_strings[ count( $date_strings ) - 1 ] );
        $target = $last->modify( '-1 day' );
        return $target->getTimestamp();
    }

    // ----------------------------------------------------------------
    // Internal
    // ----------------------------------------------------------------

    private function ordinal_suffix( int $n ): string {
        if ( $n >= 11 && $n <= 13 ) return 'th';
        switch ( $n % 10 ) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }
}
