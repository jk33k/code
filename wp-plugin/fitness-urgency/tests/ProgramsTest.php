<?php
use PHPUnit\Framework\TestCase;

final class ProgramsTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['fu_test_site_options'] = [];
        FU_Programs::flush_cache();
    }

    public function test_all_reads_from_network_store(): void {
        $GLOBALS['fu_test_site_options']['fu_programs'] = [
            [ 'slug' => 'a', 'name' => 'A', 'dates' => [], 'default' => true ],
        ];
        FU_Programs::flush_cache();
        $all = FU_Programs::all();
        $this->assertCount( 1, $all );
        $this->assertSame( 'a', $all[0]['slug'] );
    }

    public function test_save_writes_to_network_store(): void {
        FU_Programs::save( [ 'slug' => 'spring', 'name' => 'Spring', 'dates' => [ '2026-06-08' ], 'default' => true ] );
        $stored = $GLOBALS['fu_test_site_options']['fu_programs'];
        $this->assertSame( 'spring', $stored[0]['slug'] );
        $this->assertSame( [ '2026-06-08' ], $stored[0]['dates'] );
    }

    public function test_delete_removes_from_network_store_and_promotes_default(): void {
        FU_Programs::save( [ 'slug' => 'a', 'name' => 'A', 'dates' => [], 'default' => true ] );
        FU_Programs::save( [ 'slug' => 'b', 'name' => 'B', 'dates' => [], 'default' => false ] );
        FU_Programs::delete( 'a' );
        $stored = $GLOBALS['fu_test_site_options']['fu_programs'];
        $this->assertCount( 1, $stored );
        $this->assertSame( 'b', $stored[0]['slug'] );
        $this->assertTrue( $stored[0]['default'] ); // promoted
    }

    public function test_cache_avoids_restore_until_flushed(): void {
        FU_Programs::save( [ 'slug' => 'a', 'name' => 'A', 'dates' => [], 'default' => true ] );
        FU_Programs::all(); // primes cache
        // Mutate the underlying store directly, bypassing FU_Programs.
        $GLOBALS['fu_test_site_options']['fu_programs'] = [];
        $this->assertCount( 1, FU_Programs::all(), 'cache should serve stale until flushed' );
        FU_Programs::flush_cache();
        $this->assertCount( 0, FU_Programs::all(), 'after flush reads fresh store' );
    }

    public function test_save_refreshes_cache(): void {
        FU_Programs::all(); // primes empty cache
        FU_Programs::save( [ 'slug' => 'a', 'name' => 'A', 'dates' => [], 'default' => true ] );
        $this->assertCount( 1, FU_Programs::all(), 'save must refresh the cache' );
    }

    public function test_sanitize_sets_evergreen_when_schedule_is_ongoing(): void {
        $p = FU_Programs::sanitize( [ 'slug' => 'ev', 'name' => 'Ever', 'schedule' => 'Ongoing', 'dates' => [] ] );
        $this->assertIsArray( $p );
        $this->assertTrue( $p['evergreen'] );
    }

    public function test_sanitize_evergreen_false_without_keyword(): void {
        $p = FU_Programs::sanitize( [ 'slug' => 'fx', 'name' => 'Fixed', 'schedule' => '', 'dates' => [ '2026-06-08' ] ] );
        $this->assertIsArray( $p );
        $this->assertFalse( $p['evergreen'] );
    }

    public function test_sanitize_evergreen_trims_and_lowercases(): void {
        $p = FU_Programs::sanitize( [ 'slug' => 'ev2', 'name' => 'Ever2', 'schedule' => '  ONGOING  ', 'dates' => [] ] );
        $this->assertTrue( $p['evergreen'] );
    }
}
