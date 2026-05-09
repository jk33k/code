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
        $this->tz                 = new \DateTimeZone( $tz_string );
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
        [ $y, $m, $d ] = explode( '-', $s );
        return \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            "$y-$m-$d 00:00:00",
            $this->tz
        );
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
        return $d->format( 'l, F j' ); // "Monday, June 8"
    }

    public function spots_label( int $n ): string {
        return $n === 1 ? '1 spot left' : "{$n} spots left";
    }
}
