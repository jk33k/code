# Multi-Mode Start Dates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Support single / double / triple / fixed / ongoing start-date modes — auto-detected by date count, with an `ongoing` keyword for evergreen — reusing the existing slot engine.

**Architecture:** Keep `FU_Renderer` the pure, WP-free core. Add a pure `resolve_mode()`, an `upcoming_mondays()` generator, and an optional `max_lead_days` gate on `compute_slots`. `FU_Shortcodes` becomes mode-aware at the single date-resolution point and suppresses countdown/phase output in ongoing mode. The admin form gains a "Schedule" text field that sets an `evergreen` flag. `fu-refresh.js` mirrors all of it for the midnight refresh. Additive and backward compatible.

**Tech Stack:** PHP 7.4+, WordPress, PHPUnit 10, vanilla JS.

**Spec:** `docs/superpowers/specs/2026-05-23-multi-mode-start-dates-design.md`

---

## File Map

| File | Change |
|---|---|
| `includes/class-fu-renderer.php` | `resolve_mode()`, `upcoming_mondays()`, `compute_slots` `$max_lead_days` param |
| `tests/RendererTest.php` | Tests for mode/Mondays/gate/ongoing + regression |
| `includes/class-fu-programs.php` | `sanitize()` derives `evergreen` from `schedule` |
| `tests/ProgramsTest.php` | Test evergreen derivation |
| `includes/class-fu-shortcodes.php` | Mode-aware `resolve()`; ongoing suppression in countdown/show_phase/finaldate; `evergreen` in inlined JS data |
| `includes/class-fu-admin.php` | `handle_save()` passes `schedule` into `$raw` |
| `views/admin-settings.php` | "Schedule" text field |
| `assets/js/fu-refresh.js` | Mirror mode/Mondays/gate/suppression; read `evergreen` |
| `fitness-urgency.php` | Version → 2.1.0 (header + `FU_VERSION`) |
| `wp-plugin/DESIGNER-MANUAL.md`, `wp-plugin/DESIGNER-INSTRUCTIONS.md`, `CLAUDE.md` | Document the modes |
| `wp-plugin/dwc-spots-left.zip` | Rebuilt |

**Test commands run from** `wp-plugin/fitness-urgency/`. **Git commands run from repo root** `/Users/fbonato/Documents/VSCODE-CLAUDE/SpotsLeft/code`.

---

## Task 1: `FU_Renderer::resolve_mode()` (TDD)

**Files:**
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-renderer.php`
- Test: `wp-plugin/fitness-urgency/tests/RendererTest.php`

- [ ] **Step 1: Write the failing test**

Append inside the `RendererTest` class (before its final `}`):

```php
    public function test_resolve_mode(): void {
        $this->assertSame( 'ongoing', FU_Renderer::resolve_mode( [], true ) );
        $this->assertSame( 'ongoing', FU_Renderer::resolve_mode( [ '2026-06-08' ], true ) ); // evergreen wins
        $this->assertSame( 'single',  FU_Renderer::resolve_mode( [ '2026-06-08' ], false ) );
        $this->assertSame( 'double',  FU_Renderer::resolve_mode( [ 'a', 'b' ], false ) );
        $this->assertSame( 'triple',  FU_Renderer::resolve_mode( [ 'a', 'b', 'c' ], false ) );
        $this->assertSame( 'fixed',   FU_Renderer::resolve_mode( [ 'a', 'b', 'c', 'd' ], false ) );
        $this->assertSame( 'fixed',   FU_Renderer::resolve_mode( [], false ) );
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/phpunit tests/RendererTest.php --filter test_resolve_mode`
Expected: FAIL — `Call to undefined method FU_Renderer::resolve_mode()`.

- [ ] **Step 3: Implement**

In `includes/class-fu-renderer.php`, add this method right after the `compute_variables()` method (after its closing `}`, before the "Spots table" section comment):

```php
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
```

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/phpunit tests/RendererTest.php --filter test_resolve_mode`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-renderer.php wp-plugin/fitness-urgency/tests/RendererTest.php
git commit -m "feat: add FU_Renderer::resolve_mode for date-count modes

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: `FU_Renderer::upcoming_mondays()` (TDD)

**Files:**
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-renderer.php`
- Test: `wp-plugin/fitness-urgency/tests/RendererTest.php`

- [ ] **Step 1: Write the failing tests**

Append inside the `RendererTest` class:

```php
    public function test_upcoming_mondays_from_friday(): void {
        // Fri 2026-05-22; Monday of that ISO week is Mon 2026-05-18.
        $out = $this->r->upcoming_mondays( 5, $this->r->parse_iso_date( '2026-05-22' ) );
        $this->assertSame(
            [ '2026-05-18', '2026-05-25', '2026-06-01', '2026-06-08', '2026-06-15' ],
            $out
        );
    }

    public function test_upcoming_mondays_from_sunday(): void {
        // Sun 2026-05-24 belongs to the ISO week starting Mon 2026-05-18.
        $out = $this->r->upcoming_mondays( 3, $this->r->parse_iso_date( '2026-05-24' ) );
        $this->assertSame( [ '2026-05-18', '2026-05-25', '2026-06-01' ], $out );
    }

    public function test_upcoming_mondays_from_monday(): void {
        $out = $this->r->upcoming_mondays( 2, $this->r->parse_iso_date( '2026-05-25' ) );
        $this->assertSame( [ '2026-05-25', '2026-06-01' ], $out );
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/phpunit tests/RendererTest.php --filter test_upcoming_mondays`
Expected: FAIL — `Call to undefined method FU_Renderer::upcoming_mondays()`.

- [ ] **Step 3: Implement**

In `includes/class-fu-renderer.php`, add this method immediately after `resolve_mode()`:

```php
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
```

- [ ] **Step 4: Run to verify they pass**

Run: `./vendor/bin/phpunit tests/RendererTest.php --filter test_upcoming_mondays`
Expected: PASS (all three).

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-renderer.php wp-plugin/fitness-urgency/tests/RendererTest.php
git commit -m "feat: add FU_Renderer::upcoming_mondays generator for ongoing mode

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: `compute_slots` 21-day gate + ongoing integration (TDD)

**Files:**
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-renderer.php`
- Test: `wp-plugin/fitness-urgency/tests/RendererTest.php`

- [ ] **Step 1: Write the failing tests**

Append inside the `RendererTest` class:

```php
    public function test_single_gate_hides_beyond_21_days(): void {
        $today = $this->r->parse_iso_date( '2026-06-08' );
        // 2026-06-30 is 22 days after 2026-06-08.
        $this->assertSame( [], $this->r->compute_slots( [ '2026-06-30' ], $today, 21 ) );
    }

    public function test_single_gate_shows_at_21_days(): void {
        $today = $this->r->parse_iso_date( '2026-06-08' );
        // 2026-06-29 is exactly 21 days after.
        $slots = $this->r->compute_slots( [ '2026-06-29' ], $today, 21 );
        $this->assertCount( 1, $slots );
        $this->assertSame( 'date', $slots[0]['type'] );
    }

    public function test_single_gate_still_shows_sold_out_and_high_price(): void {
        // Gate must not suppress the SOLD-OUT / high-price end of a single date.
        $today = $this->r->parse_iso_date( '2026-06-08' ); // the date itself, T=0
        $slots = $this->r->compute_slots( [ '2026-06-08' ], $today, 21 );
        $this->assertCount( 2, $slots );
        $this->assertSame( 'date', $slots[0]['type'] );
        $this->assertTrue( $slots[0]['spots']['sold_out'] ?? false );
        $this->assertSame( 'high_price', $slots[1]['type'] );
    }

    public function test_max_lead_default_is_unchanged(): void {
        // Omitting the gate keeps the original behavior (far date still shows).
        $today = $this->r->parse_iso_date( '2026-06-08' );
        $slots = $this->r->compute_slots( [ '2026-06-30' ], $today ); // 22 days, no gate
        $this->assertCount( 1, $slots );
        $this->assertSame( 'date', $slots[0]['type'] );
    }

    public function test_ongoing_always_two_date_slots(): void {
        foreach ( [ '2026-05-22', '2026-05-24', '2026-05-25', '2026-05-26', '2026-05-27', '2026-06-03' ] as $iso ) {
            $today = $this->r->parse_iso_date( $iso );
            $dates = $this->r->upcoming_mondays( 5, $today );
            $slots = $this->r->compute_slots( $dates, $today );
            $this->assertCount( 2, $slots, "two slots on $iso" );
            $this->assertSame( 'date', $slots[0]['type'], "slot1 date on $iso" );
            $this->assertSame( 'date', $slots[1]['type'], "slot2 date on $iso" );
        }
    }
```

- [ ] **Step 2: Run to verify the gate tests fail**

Run: `./vendor/bin/phpunit tests/RendererTest.php --filter "test_single_gate_hides_beyond_21_days|test_max_lead_default_is_unchanged|test_ongoing_always_two_date_slots"`
Expected: `test_single_gate_hides_beyond_21_days` FAILS (gate not implemented; 3rd positional arg ignored → returns 1 slot, not `[]`). `test_max_lead_default_is_unchanged` and `test_ongoing_always_two_date_slots` may already PASS (they don't need the gate) — that's fine.

- [ ] **Step 3: Implement the gate parameter**

In `includes/class-fu-renderer.php`, change the `compute_slots` signature. Current:

```php
    public function compute_slots( array $date_strings, ?\DateTimeInterface $today = null ): array {
```

to:

```php
    public function compute_slots( array $date_strings, ?\DateTimeInterface $today = null, ?int $max_lead_days = null ): array {
```

Then, immediately after the block that computes `$slot1` (the array assignment ending with `'spots' => $this->spots_for_days_until( $slot1_t ),` and its closing `];`), and BEFORE the `$slot2_date = ...` line, insert:

```php
        // Single-date lead gate: hide the whole display until within max_lead_days.
        if ( $max_lead_days !== null && $slot1_t > $max_lead_days ) {
            return [];
        }
```

(The gate sits after slot 1 is known and before slot 2 is read. It only affects the future-lead case; the `$slot1_index === -1` high-price branch returns earlier and is unaffected, so a single date still shows SOLD OUT then the high-price card.)

- [ ] **Step 4: Run to verify all pass**

Run: `./vendor/bin/phpunit tests/RendererTest.php`
Expected: PASS — new gate/ongoing tests green AND the existing 32-row `slotProvider`, phase, and countdown tests all still pass (the `null` default preserves behavior).

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-renderer.php wp-plugin/fitness-urgency/tests/RendererTest.php
git commit -m "feat: add optional max_lead_days gate to compute_slots (single mode)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: `FU_Programs::sanitize()` derives `evergreen` (TDD)

**Files:**
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-programs.php`
- Test: `wp-plugin/fitness-urgency/tests/ProgramsTest.php`

- [ ] **Step 1: Write the failing tests**

Append inside the `ProgramsTest` class (before its final `}`):

```php
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
```

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/phpunit tests/ProgramsTest.php --filter evergreen`
Expected: FAIL — `Undefined array key "evergreen"`.

- [ ] **Step 3: Implement**

In `includes/class-fu-programs.php`, in the `sanitize()` method, the `return` array currently ends like:

```php
            'high_price_label' => sanitize_text_field( $raw['high_price_label'] ?? 'Start next Monday' ),
            'high_price_spots' => max( 1, (int) ( $raw['high_price_spots'] ?? 2 ) ),
        ];
```

Replace that with (add the `evergreen` line + the derivation just above the `return`):

```php
            'high_price_label' => sanitize_text_field( $raw['high_price_label'] ?? 'Start next Monday' ),
            'high_price_spots' => max( 1, (int) ( $raw['high_price_spots'] ?? 2 ) ),
            'evergreen'        => ( strtolower( trim( (string) ( $raw['schedule'] ?? '' ) ) ) === 'ongoing' ),
        ];
```

- [ ] **Step 4: Run to verify they pass**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK` — all existing + 3 new tests pass.

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-programs.php wp-plugin/fitness-urgency/tests/ProgramsTest.php
git commit -m "feat: derive evergreen flag from schedule field in sanitize

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: `FU_Shortcodes` mode-aware resolution + ongoing suppression

**Files:**
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-shortcodes.php`

(WP-coupled; verified by `php -l` and the full test suite staying green. No unit test added — `FU_Shortcodes` needs WP runtime.)

- [ ] **Step 1: Add helpers and rewrite `resolve()`**

In `includes/class-fu-shortcodes.php`, replace the entire `resolve()` method (currently the private method at the bottom that returns `[ $renderer, $slots ]`) with these THREE methods:

```php
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
```

- [ ] **Step 2: Make `card()` return empty when there are no slots**

In `card()`, the current body is:

```php
        $atts = shortcode_atts( [ 'program' => '', 'class' => '' ], $atts );
        [ $renderer, $slots ] = self::resolve( $atts );
        if ( ! $renderer ) return '';

        ob_start();
```

Change the guard line to also bail on empty slots (so a gated single-date renders nothing instead of an empty wrapper):

```php
        $atts = shortcode_atts( [ 'program' => '', 'class' => '' ], $atts );
        [ $renderer, $slots ] = self::resolve( $atts );
        if ( ! $renderer || empty( $slots ) ) return '';

        ob_start();
```

- [ ] **Step 3: Suppress countdown / show_phase / finaldate in ongoing mode**

In `finaldate()`, the current guard is:

```php
        $program = $atts['program']
            ? FU_Programs::get( $atts['program'] )
            : FU_Programs::default();
        if ( ! $program || empty( $program['dates'] ) ) return '';
```

Add an ongoing check before it (no final date exists in ongoing mode):

```php
        $program = $atts['program']
            ? FU_Programs::get( $atts['program'] )
            : FU_Programs::default();
        if ( self::mode_of( $program ) === 'ongoing' ) return '';
        if ( ! $program || empty( $program['dates'] ) ) return '';
```

In `show_phase()`, the current guard is:

```php
        $program = $atts['program']
            ? FU_Programs::get( $atts['program'] )
            : FU_Programs::default();
        if ( ! $program || empty( $program['dates'] ) ) return '';
```

Change to:

```php
        $program = $atts['program']
            ? FU_Programs::get( $atts['program'] )
            : FU_Programs::default();
        if ( self::mode_of( $program ) === 'ongoing' ) return '';
        if ( ! $program || empty( $program['dates'] ) ) return '';
```

In `countdown()`, the current guard is:

```php
        $program = $atts['program']
            ? FU_Programs::get( $atts['program'] )
            : FU_Programs::default();
        if ( ! $program || empty( $program['dates'] ) ) return '';
```

Change to:

```php
        $program = $atts['program']
            ? FU_Programs::get( $atts['program'] )
            : FU_Programs::default();
        if ( self::mode_of( $program ) === 'ongoing' ) return '';
        if ( ! $program || empty( $program['dates'] ) ) return '';
```

- [ ] **Step 4: Add `evergreen` to the inlined JS data**

In `enqueue_frontend()`, the program loop currently is:

```php
        $programs_js = [];
        foreach ( FU_Programs::all() as $p ) {
            $programs_js[ $p['slug'] ] = [
                'dates'            => array_values( $p['dates'] ),
                'highPriceMessage' => $p['high_price_label'] ?? 'Start next Monday',
                'highPriceSpots'   => (int) ( $p['high_price_spots'] ?? 2 ),
            ];
        }
```

Replace with (note `?? []` guards an evergreen program that has no dates):

```php
        $programs_js = [];
        foreach ( FU_Programs::all() as $p ) {
            $programs_js[ $p['slug'] ] = [
                'dates'            => array_values( $p['dates'] ?? [] ),
                'evergreen'        => ! empty( $p['evergreen'] ),
                'highPriceMessage' => $p['high_price_label'] ?? 'Start next Monday',
                'highPriceSpots'   => (int) ( $p['high_price_spots'] ?? 2 ),
            ];
        }
```

- [ ] **Step 5: Lint and run the suite**

Run: `php -l includes/class-fu-shortcodes.php`
Expected: `No syntax errors detected`.
Run: `./vendor/bin/phpunit tests/`
Expected: `OK` (renderer + programs tests unaffected).

- [ ] **Step 6: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-shortcodes.php
git commit -m "feat: mode-aware shortcode resolution; suppress countdown/phase in ongoing

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Admin "Schedule" field

**Files:**
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-admin.php`
- Modify: `wp-plugin/fitness-urgency/views/admin-settings.php`

- [ ] **Step 1: Pass `schedule` from the POST into `$raw`**

In `includes/class-fu-admin.php`, `handle_save()` builds `$raw`. It currently starts:

```php
        $raw = [
            'slug'             => sanitize_key( $_POST['slug'] ?? '' ),
            'name'             => sanitize_text_field( $_POST['name'] ?? '' ),
```

Add a `schedule` line right after `name`:

```php
        $raw = [
            'slug'             => sanitize_key( $_POST['slug'] ?? '' ),
            'name'             => sanitize_text_field( $_POST['name'] ?? '' ),
            'schedule'         => sanitize_text_field( wp_unslash( $_POST['schedule'] ?? '' ) ),
```

- [ ] **Step 2: Add the Schedule field to the form**

In `views/admin-settings.php`, find the "Start dates" table row, which begins:

```php
          <tr>
            <th>Start dates</th>
```

Insert this NEW row immediately BEFORE that `<tr>`:

```php
          <tr>
            <th><label for="fu-schedule">Schedule</label></th>
            <td>
              <input id="fu-schedule" name="schedule" type="text" class="regular-text"
                     value="<?php echo esc_attr( ! empty( $editing['evergreen'] ) ? 'ongoing' : '' ); ?>"
                     placeholder="(leave blank to use the dates below)">
              <p class="description">
                Type <code>ongoing</code> for <strong>evergreen mode</strong> — the plugin shows the
                next two upcoming Mondays automatically and rolls forward forever (the date pickers
                below are ignored). Otherwise leave this blank and enter dates below:
                <strong>1</strong> date = single (starts 21 days out),
                <strong>2–3</strong> = that many dates, <strong>4+</strong> = the standard cycle.
              </p>
            </td>
          </tr>
```

- [ ] **Step 3: Lint**

Run: `php -l includes/class-fu-admin.php && php -l views/admin-settings.php`
Expected: both `No syntax errors detected`.

- [ ] **Step 4: Confirm the full suite is still green**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK`.

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-admin.php wp-plugin/fitness-urgency/views/admin-settings.php
git commit -m "feat: add Schedule text field for evergreen/mode selection

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Mirror logic in `fu-refresh.js`

**Files:**
- Modify: `wp-plugin/fitness-urgency/assets/js/fu-refresh.js`

- [ ] **Step 1: Add `resolveMode`, `upcomingMondays`, `effectiveDates`, `maxLeadFor`, and gate `computeSlots`**

In `fu-refresh.js`, the function `computeSlots(dateStrings, programCfg, today)` currently begins:

```js
  function computeSlots(dateStrings, programCfg, today) {
    var dates = dateStrings.map(parseISODate);
    var t0    = today || siteToday();

    var slot1Index = -1;
    for (var i = 0; i < dates.length; i++) {
      if (daysBetween(t0, dates[i]) >= -1) { slot1Index = i; break; }
    }

    if (slot1Index === -1) return [{ type: 'highPrice' }];

    var slot1Date = dates[slot1Index];
    var slot1T    = daysBetween(t0, slot1Date);
    var slot1     = { type: 'date', date: slot1Date, daysUntil: slot1T, spots: spotsForDaysUntil(slot1T) };

    var slot2Date = dates[slot1Index + 1];
```

Replace the signature line and insert the gate. Change the first line to:

```js
  function computeSlots(dateStrings, programCfg, today, maxLeadDays) {
```

and immediately after the `var slot1 = {...};` line (before `var slot2Date = ...`), insert:

```js
    if (maxLeadDays != null && slot1T > maxLeadDays) return [];
```

Then, immediately ABOVE the `computeSlots` function, add these helpers:

```js
  // ── Mode resolution (mirrors PHP FU_Renderer::resolve_mode) ────
  function resolveMode(dates, evergreen) {
    if (evergreen) return 'ongoing';
    switch ((dates || []).length) {
      case 1:  return 'single';
      case 2:  return 'double';
      case 3:  return 'triple';
      default: return 'fixed';
    }
  }

  // ── Upcoming Mondays (mirrors PHP FU_Renderer::upcoming_mondays) ─
  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  function isoOf(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }

  function upcomingMondays(count, today) {
    var t0  = today || siteToday();
    var dow = t0.getDay();                 // JS: Sun=0..Sat=6
    var back = (dow === 0) ? 6 : (dow - 1); // days back to Monday
    var anchor = new Date(t0.getFullYear(), t0.getMonth(), t0.getDate() - back);
    var out = [];
    for (var i = 0; i < count; i++) {
      out.push(isoOf(new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate() + i * 7)));
    }
    return out;
  }

  // ── Per-config effective dates + lead gate (mirrors PHP shortcode resolve) ─
  function effectiveDates(cfg, today) {
    if (resolveMode(cfg.dates, cfg.evergreen) === 'ongoing') return upcomingMondays(5, today);
    return cfg.dates || [];
  }
  function maxLeadFor(cfg) {
    return resolveMode(cfg.dates, cfg.evergreen) === 'single' ? 21 : null;
  }
```

- [ ] **Step 2: Use effective dates + gate in every `refresh()` block**

In `refresh()`, there are four `computeSlots(cfg.dates, cfg, today)` calls (cards, var spans, show_if_slot) and one phase block. Update them:

The **cards** block — change:

```js
      var slots = computeSlots(cfg.dates, cfg, today);
      var cards = wrapper.querySelectorAll('.fu-card');
      slots.forEach(function (slot, i) {
        if (cards[i]) { cards[i].style.display = ''; applySlotToCard(cards[i], slot, cfg); }
      });
      for (var i = slots.length; i < cards.length; i++) cards[i].style.display = 'none';
```

to:

```js
      var slots = computeSlots(effectiveDates(cfg, today), cfg, today, maxLeadFor(cfg));
      var cards = wrapper.querySelectorAll('.fu-card');
      slots.forEach(function (slot, i) {
        if (cards[i]) { cards[i].style.display = ''; applySlotToCard(cards[i], slot, cfg); }
      });
      for (var i = slots.length; i < cards.length; i++) cards[i].style.display = 'none';
```

The **variable spans** block — change:

```js
      var slots = computeSlots(cfg.dates, cfg, today);
      var vars  = computeVars(slots, cfg);
      applyVarEl(el, vars);
```

to:

```js
      var slots = computeSlots(effectiveDates(cfg, today), cfg, today, maxLeadFor(cfg));
      var vars  = computeVars(slots, cfg);
      applyVarEl(el, vars);
```

The **show_if_slot** block — change:

```js
      var slots = computeSlots(cfg.dates, cfg, today);
      el.style.display = slots[slotIndex] ? '' : 'none';
```

to:

```js
      var slots = computeSlots(effectiveDates(cfg, today), cfg, today, maxLeadFor(cfg));
      el.style.display = slots[slotIndex] ? '' : 'none';
```

The **show_phase** block — change:

```js
      var phase        = el.getAttribute('data-fu-phase');
      var currentPhase = computePhase(cfg.dates, today);
      el.style.display = (currentPhase === phase) ? '' : 'none';
```

to (ongoing has no phases — always hide):

```js
      var phase        = el.getAttribute('data-fu-phase');
      if (resolveMode(cfg.dates, cfg.evergreen) === 'ongoing') { el.style.display = 'none'; return; }
      var currentPhase = computePhase(cfg.dates, today);
      el.style.display = (currentPhase === phase) ? '' : 'none';
```

- [ ] **Step 3: Update the header comment for the data shape**

In the top doc comment, change the line:

```js
 *   { programs: { slug: { dates, highPriceMessage, highPriceSpots } },
```

to:

```js
 *   { programs: { slug: { dates, evergreen, highPriceMessage, highPriceSpots } },
```

- [ ] **Step 4: Syntax-check the JS**

Run (from `wp-plugin/fitness-urgency/`): `node --check assets/js/fu-refresh.js`
Expected: no output (exit 0) = valid syntax.

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/fitness-urgency/assets/js/fu-refresh.js
git commit -m "feat: mirror multi-mode logic in fu-refresh.js (mode/Mondays/gate/ongoing)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Version bump → 2.1.0

**Files:**
- Modify: `wp-plugin/fitness-urgency/fitness-urgency.php`

- [ ] **Step 1: Bump the header version**

In `fitness-urgency.php`, change `* Version:           2.0.0` to:

```php
 * Version:           2.1.0
```

- [ ] **Step 2: Bump the constant**

Change `define( 'FU_VERSION',  '2.0.0' );` to:

```php
define( 'FU_VERSION',  '2.1.0' );
```

- [ ] **Step 3: Verify**

Run: `php -l wp-plugin/fitness-urgency/fitness-urgency.php && grep -nE 'Version:|FU_VERSION' wp-plugin/fitness-urgency/fitness-urgency.php`
Expected: no syntax errors; both lines show `2.1.0`.

- [ ] **Step 4: Commit**

```bash
git add wp-plugin/fitness-urgency/fitness-urgency.php
git commit -m "chore: bump version to 2.1.0

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Documentation — designer guides + CLAUDE.md

**Files:**
- Modify: `wp-plugin/DESIGNER-MANUAL.md`
- Modify: `wp-plugin/DESIGNER-INSTRUCTIONS.md`
- Modify: `CLAUDE.md`

- [ ] **Step 1: Add a "Date modes" section to DESIGNER-MANUAL.md**

In `wp-plugin/DESIGNER-MANUAL.md`, find the section header `## Who sets what (important)` and insert this NEW section immediately BEFORE it:

```markdown
## How many dates you enter = which mode (auto)

The plugin picks the behavior from what the super admin enters in **Network
Admin → Settings → DWC - Spots Left**:

| What's entered | Mode | What shows |
|---|---|---|
| **1 date** | Single | Urgency switches on **21 days** before the date, counts down, sells out, then "Start next Monday" |
| **2 or 3 dates** | Multi | The usual rolling cards, with that many dates |
| **4+ dates** | Standard | Exactly the classic cycle |
| Type **`ongoing`** in the **Schedule** field | Evergreen | Always shows the next two upcoming Mondays and rolls forward **forever** — no end date |

**Evergreen / ongoing:** in the Schedule text box, type the word `ongoing`
(the date pickers are then ignored). New Mondays appear automatically every
week — a date stays visible (selling out) through the Tuesday after it, and
the next Monday rolls in on Wednesday. Because there's no final date,
`[fu_countdown]` and `[fu_show_phase]` banners produce **nothing** in evergreen
mode — use them only on fixed-date campaigns.
```

- [ ] **Step 2: Add the same facts to the deep reference**

In `wp-plugin/DESIGNER-INSTRUCTIONS.md`, find the section `## Step 2 — Set up your programs (super admin, Network Admin only)`. Immediately after its intro paragraph (the line ending "…subsite editors only place shortcodes.") insert:

```markdown

### Date modes (auto-detected)

The number of dates you enter selects the behavior automatically:

| Entered | Mode | Behavior |
|---|---|---|
| 1 date | **single** | Hidden until 21 days before the date, then the normal spots countdown → SOLD OUT → high-price card. |
| 2 dates | **double** | Current rolling logic with two dates. |
| 3 dates | **triple** | Current rolling logic with three dates. |
| 4+ dates | **fixed** | The classic cycle (unchanged). |
| `ongoing` in the **Schedule** field | **evergreen** | Generates the next two upcoming Mondays from today and rolls forward indefinitely. The date pickers are ignored. |

In **evergreen** mode there is no final date, so `[fu_countdown]`,
`[fu_show_phase phase="final_week"]`, and `[fu_show_phase phase="high_demand"]`
all render nothing, and `[fu_finaldate]` is empty. Use those only on
fixed/multi-date campaigns.

> **Note (single mode):** because a single date is gated to a 21-day window,
> if a visitor loads the page when the date is more than 21 days away they'll
> see nothing for it; it appears on the next page load inside the window.
```

- [ ] **Step 3: Update CLAUDE.md status, spec, and version history**

In `CLAUDE.md`:

(a) Under "## Current status", change the version line to:

```markdown
- **Version**: 2.1.0 (plugin), pushed on branch `claude/fitness-urgency-script-RtPun`.
```

(b) Find the "### Spec (codified in `FU_Renderer`)" area (the section describing slot transitions). Add this subsection immediately after the "## The spec (codified in `FU_Renderer`)" heading's first paragraph (or at the end of that spec section, before "## Shortcodes"):

```markdown
### Date modes (v2.1.0)

`FU_Renderer::resolve_mode($dates, $evergreen)` selects the mode:
1 date → `single` (21-day lead gate via `compute_slots($dates,$today,21)`),
2 → `double`, 3 → `triple`, 4+ → `fixed`, and the `evergreen` flag → `ongoing`.
Ongoing ignores stored dates and uses `upcoming_mondays(5)` (Monday of the
current ISO week + the next four) fed into the same `compute_slots`, so there
are always two upcoming Mondays and never a high-price end. `[fu_countdown]`
and `[fu_show_phase]` output nothing in ongoing mode. The `evergreen` flag is
derived in `FU_Programs::sanitize()` from the admin "Schedule" field (the word
`ongoing`).
```

(c) Add a version-history entry above the `- **2.0.0**` entry:

```markdown
- **2.1.0** — Multi-mode start dates. `FU_Renderer::resolve_mode()` +
  `upcoming_mondays()`; `compute_slots()` gains an optional `max_lead_days`
  gate (single mode = 21 days). New "ongoing"/evergreen mode (admin "Schedule"
  field → `evergreen` flag) shows two rolling Mondays forever and suppresses
  countdown/phase. JS mirror updated. Additive/backward compatible.
```

- [ ] **Step 4: Commit**

```bash
git add wp-plugin/DESIGNER-MANUAL.md wp-plugin/DESIGNER-INSTRUCTIONS.md CLAUDE.md
git commit -m "docs: document multi-mode start dates (single/multi/fixed/ongoing)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Cleanup, rebuild ZIP, final verification

**Files:**
- Modify: `wp-plugin/dwc-spots-left.zip`

- [ ] **Step 1: Run the full suite one final time**

Run (from `wp-plugin/fitness-urgency/`): `./vendor/bin/phpunit tests/`
Expected: `OK` (all tests green — original + new mode/Monday/gate/ongoing/evergreen tests).

- [ ] **Step 2: Cleanup check — confirm no stray/temporary files**

Run from repo root:

```bash
git status --porcelain
git ls-files --others --exclude-standard
git clean -nxd
```

Expected: working tree clean; the only untracked path is `.claude/` (gitignored, so it must NOT appear in `git ls-files --others --exclude-standard`). `git clean -nxd` should list nothing inside `wp-plugin/fitness-urgency/` except `vendor/` and `.phpunit.result.cache` (both dev-only, both gitignored). If anything unexpected appears (editor temp files, stray `.zip`, `.DS_Store`), remove it before continuing.

- [ ] **Step 3: Rebuild the ZIP**

Run from repo root:

```bash
cd wp-plugin
rm -f dwc-spots-left.zip
zip -r dwc-spots-left.zip fitness-urgency/ \
  --exclude "fitness-urgency/vendor/*" \
  --exclude "fitness-urgency/tests/*" \
  --exclude "fitness-urgency/composer.json" \
  --exclude "fitness-urgency/composer.lock" \
  --exclude "fitness-urgency/phpunit.xml" \
  --exclude "fitness-urgency/.phpunit.result.cache"
cd ..
```

- [ ] **Step 4: Verify ZIP contents + version**

Run from repo root:

```bash
unzip -l wp-plugin/dwc-spots-left.zip | grep -cE 'fitness-urgency/(vendor|tests)/'
unzip -p wp-plugin/dwc-spots-left.zip fitness-urgency/fitness-urgency.php | grep -E 'Version:|Network:'
ls -la wp-plugin/dwc-spots-left.zip
```

Expected: the grep count is `0` (no vendor/tests), header shows `Version: 2.1.0` and `Network: true`, size ≤ ~25 KB.

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/dwc-spots-left.zip
git commit -m "build: rebuild dwc-spots-left.zip for v2.1.0

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Manual verification (for the user, post-merge)

1. **Single:** one date 25 days out → nothing shows; change to ≤21 days → card appears and counts down.
2. **Double/Triple:** enter 2 then 3 dates → the rolling cards show that many.
3. **Ongoing:** type `ongoing` in Schedule (leave dates blank) → two upcoming Mondays show; confirm a new Monday rolls in on Wednesday and `[fu_countdown]`/phase banners render nothing.
4. **Fixed:** an existing 4-date program is unchanged.

---

## Self-Review notes

- **Spec coverage:** mode resolution (Task 1), ongoing generation (Task 2), single 21-day gate (Task 3), evergreen storage (Task 4), mode-aware shortcodes + ongoing suppression + JS data (Task 5), admin Schedule field (Task 6), JS mirror (Task 7), version (Task 8), designer docs + CLAUDE.md (Task 9), cleanup + ZIP (Task 10). All spec deliverables mapped, including the user's explicit "update designer MDs" and "clean up files" requirements (Tasks 9 & 10).
- **Type/name consistency:** `resolve_mode`, `upcoming_mondays`, `max_lead_days`/`maxLeadDays`, `evergreen`, `mode_of`, `program_from_atts`, `effectiveDates`, `maxLeadFor` used consistently across PHP and JS. Mode strings `single|double|triple|fixed|ongoing` identical in PHP and JS.
- **No placeholders:** every code/command step is concrete.
- **Backward compatibility:** `compute_slots`' third arg defaults to `null` (existing calls and the 32-row provider unaffected); fixed mode path is byte-identical to today.
