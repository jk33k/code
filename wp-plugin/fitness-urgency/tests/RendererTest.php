<?php
use PHPUnit\Framework\TestCase;

/**
 * Ports the full JavaScript spec:
 *   - 32 slot-logic cases from test-fitness-urgency.js
 *   - 6 named-variable cases from test-fitness-urgency-vars.js
 */
class RendererTest extends TestCase {

    private FU_Renderer $r;
    private array $dates = [
        '2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29',
    ];

    protected function setUp(): void {
        $this->r = new FU_Renderer( [ 'timezone' => 'America/New_York' ] );
    }

    // ----------------------------------------------------------------
    // Helper
    // ----------------------------------------------------------------

    private function today( string $iso ): \DateTimeImmutable {
        return $this->r->parse_iso_date( $iso );
    }

    private function slots( string $iso ): array {
        return $this->r->compute_slots( $this->dates, $this->today( $iso ) );
    }

    private function date_str( array $slot ): string {
        return $slot['date']->format( 'l, F j' );
    }

    private function spots_str( array $slot ): string {
        $s = $slot['spots'];
        if ( $s['sold_out'] ?? false ) return 'SOLD OUT';
        return $this->r->spots_label( $s['count'] );
    }

    // ----------------------------------------------------------------
    // 32 spec cases (slot logic)
    // ----------------------------------------------------------------

    /** @dataProvider slotProvider */
    public function test_slot_logic( string $today, int $expected_count, array $slot1, array $slot2 = [] ): void {
        $slots = $this->slots( $today );
        $this->assertCount( $expected_count, $slots, "Wrong slot count on $today" );

        if ( $slot1['type'] === 'high_price' ) {
            $this->assertSame( 'high_price', $slots[0]['type'], "Slot1 should be high_price on $today" );
        } else {
            $this->assertSame( 'date', $slots[0]['type'] );
            $this->assertSame( $slot1['date'],  $this->date_str( $slots[0] ), "Slot1 date on $today" );
            $this->assertSame( $slot1['spots'], $this->spots_str( $slots[0] ), "Slot1 spots on $today" );
        }

        if ( $expected_count === 2 ) {
            if ( $slot2['type'] === 'high_price' ) {
                $this->assertSame( 'high_price', $slots[1]['type'], "Slot2 should be high_price on $today" );
            } else {
                $this->assertSame( $slot2['date'],  $this->date_str( $slots[1] ), "Slot2 date on $today" );
                $this->assertSame( $slot2['spots'], $this->spots_str( $slots[1] ), "Slot2 spots on $today" );
            }
        }
    }

    public static function slotProvider(): array {
        $d = static fn( $date, $spots ) => [ 'type' => 'date', 'date' => $date, 'spots' => $spots ];
        $hp = [ 'type' => 'high_price' ];
        $jun8  = 'Monday, June 8';
        $jun15 = 'Monday, June 15';
        $jun22 = 'Monday, June 22';
        $jun29 = 'Monday, June 29';

        return [
            // [today, slot_count, slot1, slot2?]
            [ '2026-05-31', 2, $d($jun8, '6 spots left'),  $d($jun15, '9 spots left') ],
            [ '2026-06-01', 2, $d($jun8, '5 spots left'),  $d($jun15, '9 spots left') ],
            [ '2026-06-02', 2, $d($jun8, '4 spots left'),  $d($jun15, '8 spots left') ],
            [ '2026-06-03', 2, $d($jun8, '3 spots left'),  $d($jun15, '8 spots left') ],
            [ '2026-06-04', 2, $d($jun8, '2 spots left'),  $d($jun15, '7 spots left') ],
            [ '2026-06-05', 2, $d($jun8, '1 spot left'),   $d($jun15, '7 spots left') ],
            [ '2026-06-06', 2, $d($jun8, '1 spot left'),   $d($jun15, '6 spots left') ],
            [ '2026-06-07', 2, $d($jun8, 'SOLD OUT'),      $d($jun15, '6 spots left') ],
            [ '2026-06-08', 2, $d($jun8, 'SOLD OUT'),      $d($jun15, '5 spots left') ],
            [ '2026-06-09', 2, $d($jun8, 'SOLD OUT'),      $d($jun15, '4 spots left') ],
            [ '2026-06-10', 2, $d($jun15, '3 spots left'), $d($jun22, '8 spots left') ],
            [ '2026-06-11', 2, $d($jun15, '2 spots left'), $d($jun22, '7 spots left') ],
            [ '2026-06-12', 2, $d($jun15, '1 spot left'),  $d($jun22, '7 spots left') ],
            [ '2026-06-13', 2, $d($jun15, '1 spot left'),  $d($jun22, '6 spots left') ],
            [ '2026-06-14', 2, $d($jun15, 'SOLD OUT'),     $d($jun22, '6 spots left') ],
            [ '2026-06-15', 2, $d($jun15, 'SOLD OUT'),     $d($jun22, '5 spots left') ],
            [ '2026-06-16', 2, $d($jun15, 'SOLD OUT'),     $d($jun22, '4 spots left') ],
            [ '2026-06-17', 2, $d($jun22, '3 spots left'), $d($jun29, '8 spots left') ],
            [ '2026-06-18', 2, $d($jun22, '2 spots left'), $d($jun29, '7 spots left') ],
            [ '2026-06-19', 2, $d($jun22, '1 spot left'),  $d($jun29, '7 spots left') ],
            [ '2026-06-20', 2, $d($jun22, '1 spot left'),  $d($jun29, '6 spots left') ],
            [ '2026-06-21', 2, $d($jun22, 'SOLD OUT'),     $d($jun29, '6 spots left') ],
            [ '2026-06-22', 2, $d($jun22, 'SOLD OUT'),     $d($jun29, '5 spots left') ],
            [ '2026-06-23', 2, $d($jun22, 'SOLD OUT'),     $d($jun29, '4 spots left') ],
            [ '2026-06-24', 1, $d($jun29, '3 spots left') ],
            [ '2026-06-25', 1, $d($jun29, '2 spots left') ],
            [ '2026-06-26', 1, $d($jun29, '1 spot left')  ],
            [ '2026-06-27', 1, $d($jun29, '1 spot left')  ],
            [ '2026-06-28', 2, $d($jun29, 'SOLD OUT'),     $hp ],
            [ '2026-06-29', 2, $d($jun29, 'SOLD OUT'),     $hp ],
            [ '2026-07-01', 1, $hp ],
            [ '2026-07-15', 1, $hp ],
        ];
    }

    // ----------------------------------------------------------------
    // 6 named-variable cases
    // ----------------------------------------------------------------

    public function test_variables_normal_day(): void {
        $vars = $this->r->compute_variables( $this->slots( '2026-05-31' ) );
        $this->assertSame( 'Monday, June 8',  $vars['startdate1']['text'] );
        $this->assertSame( '6 spots left',    $vars['spotsleft1']['text'] );
        $this->assertSame( 'Monday, June 15', $vars['startdate2']['text'] );
        $this->assertSame( '9 spots left',    $vars['spotsleft2']['text'] );
    }

    public function test_variables_slot1_sold_out(): void {
        $vars = $this->r->compute_variables( $this->slots( '2026-06-09' ) );
        $this->assertSame( 'SOLD OUT', $vars['spotsleft1']['text'] );
        $this->assertTrue( $vars['spotsleft1']['sold_out'] ?? false );
        $this->assertSame( '4 spots left', $vars['spotsleft2']['text'] );
    }

    public function test_variables_single_date_day(): void {
        $vars = $this->r->compute_variables( $this->slots( '2026-06-25' ) );
        $this->assertSame( 'Monday, June 29', $vars['startdate1']['text'] );
        $this->assertSame( '2 spots left',    $vars['spotsleft1']['text'] );
        $this->assertFalse( $vars['startdate2']['visible'] );
        $this->assertFalse( $vars['spotsleft2']['visible'] );
    }

    public function test_variables_singular_spot(): void {
        $vars = $this->r->compute_variables( $this->slots( '2026-06-26' ) );
        $this->assertSame( '1 spot left', $vars['spotsleft1']['text'] );
    }

    public function test_variables_final_date_sold_out_high_price_slot2(): void {
        $vars = $this->r->compute_variables( $this->slots( '2026-06-28' ) );
        $this->assertSame( 'SOLD OUT',         $vars['spotsleft1']['text'] );
        $this->assertSame( 'Start next Monday', $vars['startdate2']['text'] );
        $this->assertSame( '2 spots left',      $vars['spotsleft2']['text'] );
        $this->assertTrue( $vars['startdate2']['high_price'] ?? false );
    }

    public function test_variables_after_program_ends(): void {
        $vars = $this->r->compute_variables( $this->slots( '2026-07-01' ) );
        $this->assertSame( 'Start next Monday', $vars['startdate1']['text'] );
        $this->assertSame( '2 spots left',      $vars['spotsleft1']['text'] );
        $this->assertTrue( $vars['startdate1']['high_price'] ?? false );
        $this->assertFalse( $vars['startdate2']['visible'] );
        $this->assertFalse( $vars['spotsleft2']['visible'] );
    }
}
