# Multisite Network-Level Config Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Relocate DWC - Spots Left's program/date config from per-site WordPress options to a single network-wide store, managed in Network Admin, so all subsites render identical shortcodes.

**Architecture:** Strict-shared, multisite-only. `FU_Programs` swaps `get_option`/`update_option` for `get_site_option`/`update_site_option` (one row in `wp_sitemeta`). `FU_Admin` moves its page to `network_admin_menu` (super admins only). The render path (`FU_Shortcodes`, `FU_Renderer`) is behaviorally unchanged so shortcode output stays byte-for-byte identical across subsites. Audit hygiene fixes (output escaping, date/timezone try-catch) are folded in. Version → 2.0.0.

**Tech Stack:** PHP 7.4+, WordPress Multisite APIs (`get_site_option`, `update_site_option`, `network_admin_menu`, `network_admin_edit_*`), PHPUnit 10.

**Spec:** `docs/superpowers/specs/2026-05-22-multisite-network-config-design.md`

---

## File Map

| File | Change |
|---|---|
| `wp-plugin/fitness-urgency/tests/bootstrap.php` | Add WP-function stubs + load `FU_Programs` for testing |
| `wp-plugin/fitness-urgency/tests/ProgramsTest.php` | **New** — unit tests for network storage + cache |
| `wp-plugin/fitness-urgency/includes/class-fu-programs.php` | `get_site_option`/`update_site_option` + per-request static cache |
| `wp-plugin/fitness-urgency/includes/class-fu-plugin.php` | Activation: multisite guard + seed via `update_site_option` |
| `wp-plugin/fitness-urgency/fitness-urgency.php` | `Network: true` header, version → 2.0.0 |
| `wp-plugin/fitness-urgency/includes/class-fu-admin.php` | Network admin menu, network form handlers, nonces |
| `wp-plugin/fitness-urgency/views/admin-settings.php` | Network-admin URLs for form/edit/delete/cancel |
| `wp-plugin/fitness-urgency/views/card.php` | Escape `SOLD OUT` |
| `wp-plugin/fitness-urgency/includes/class-fu-shortcodes.php` | Escape countdown timestamp attr |
| `wp-plugin/fitness-urgency/includes/class-fu-renderer.php` | try/catch on timezone + date parse |
| `wp-plugin/fitness-urgency/tests/RendererTest.php` | Tests for invalid timezone/date fallback |
| `wp-plugin/DESIGNER-INSTRUCTIONS.md` | "dates managed in Network Admin" |
| `CLAUDE.md` | Status + version history → 2.0.0 |
| `wp-plugin/dwc-spots-left.zip` | Rebuilt |

**Working directory for all test commands:** `wp-plugin/fitness-urgency/`

---

## Task 1: Test harness — stub WP functions and load FU_Programs

`FU_Programs` calls WordPress functions (`get_site_option`, `update_site_option`, `sanitize_key`, `sanitize_text_field`, `is_wp_error`, `WP_Error`). The current bootstrap only loads `FU_Renderer` (no WP needed). Add lightweight stubs so `FU_Programs` is unit-testable, backed by an in-memory site-option store.

**Files:**
- Modify: `wp-plugin/fitness-urgency/tests/bootstrap.php`

- [ ] **Step 1: Replace the bootstrap with stubs + FU_Programs load**

Replace the entire contents of `tests/bootstrap.php` with:

```php
<?php
define( 'ABSPATH', true );       // satisfy the ABSPATH guard in class files
define( 'FU_TESTING', true );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/class-fu-renderer.php';

// ---------------------------------------------------------------------
// Minimal WordPress stubs for unit-testing FU_Programs in isolation.
// Backed by an in-memory network-option store.
// ---------------------------------------------------------------------
$GLOBALS['fu_test_site_options'] = [];

if ( ! function_exists( 'get_site_option' ) ) {
    function get_site_option( $key, $default = false ) {
        return $GLOBALS['fu_test_site_options'][ $key ] ?? $default;
    }
}
if ( ! function_exists( 'update_site_option' ) ) {
    function update_site_option( $key, $value ) {
        $GLOBALS['fu_test_site_options'][ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $message;
        public function __construct( $code = '', $message = '' ) { $this->message = $message; }
        public function get_error_message() { return $this->message; }
    }
}

require_once __DIR__ . '/../includes/class-fu-programs.php';
```

- [ ] **Step 2: Verify the existing suite still loads and passes**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK (53 tests, 220 assertions)` — stubs must not break `FU_Renderer` tests.

- [ ] **Step 3: Commit**

```bash
git add wp-plugin/fitness-urgency/tests/bootstrap.php
git commit -m "test: add WP stubs and load FU_Programs in test bootstrap

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: FU_Programs — network storage + per-request cache (TDD)

Switch the three option calls to network-wide equivalents and add a static per-request cache. Write the tests first.

**Files:**
- Create: `wp-plugin/fitness-urgency/tests/ProgramsTest.php`
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-programs.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/ProgramsTest.php`:

```php
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
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/ProgramsTest.php`
Expected: FAIL — `Error: Call to undefined method FU_Programs::flush_cache()` and `get_option`-based reads won't see the `fu_test_site_options` store.

- [ ] **Step 3: Implement network storage + cache**

In `includes/class-fu-programs.php`, change line 6 to add a cache property:

```php
    private static string $option = 'fu_programs';
    private static ?array $cache  = null;
```

Replace `all()` (lines 16-19) with:

```php
    /** @return array<int, array> */
    public static function all(): array {
        if ( self::$cache !== null ) {
            return self::$cache;
        }
        self::$cache = get_site_option( self::$option, [] );
        return self::$cache;
    }

    /** Reset the per-request cache (call after external writes / in tests). */
    public static function flush_cache(): void {
        self::$cache = null;
    }
```

In `save()`, replace the return line (line 70) `return update_option( self::$option, $all );` with:

```php
        $ok = update_site_option( self::$option, $all );
        self::$cache = $all;
        return $ok;
```

In `delete()`, replace the return line (line 83) `return update_option( self::$option, $all );` with:

```php
        $ok = update_site_option( self::$option, $all );
        self::$cache = $all;
        return $ok;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK (58 tests, ...)` — 53 renderer + 5 new programs tests, all green.

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-programs.php wp-plugin/fitness-urgency/tests/ProgramsTest.php
git commit -m "feat: store programs in network options with per-request cache

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: FU_Plugin — multisite activation guard + network seed

**Files:**
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-plugin.php`

- [ ] **Step 1: Replace the `activate()` method**

Replace `activate()` (lines 14-26) with:

```php
    public static function activate() {
        // This plugin is multisite-only.
        if ( ! is_multisite() ) {
            deactivate_plugins( FU_BASENAME );
            wp_die(
                esc_html__( 'DWC - Spots Left requires WordPress Multisite. Please network-activate it on a multisite install.', 'fitness-urgency' ),
                esc_html__( 'Multisite required', 'fitness-urgency' ),
                [ 'back_link' => true ]
            );
        }

        // Seed default program network-wide if none exist yet.
        if ( ! get_site_option( 'fu_programs' ) ) {
            update_site_option( 'fu_programs', [
                [
                    'slug'    => 'default',
                    'name'    => 'My Fitness Program',
                    'dates'   => [],
                    'default' => true,
                ],
            ] );
        }
    }
```

- [ ] **Step 2: Add a runtime multisite notice in `init()`**

Replace `init()` (lines 6-12) with:

```php
    public static function init() {
        if ( ! is_multisite() ) {
            add_action( 'admin_notices', [ __CLASS__, 'multisite_notice' ] );
            add_action( 'network_admin_notices', [ __CLASS__, 'multisite_notice' ] );
            return;
        }
        FU_Programs::init();
        FU_Shortcodes::init();
        if ( is_admin() ) {
            FU_Admin::init();
        }
    }

    public static function multisite_notice() {
        echo '<div class="notice notice-error"><p>'
            . esc_html__( 'DWC - Spots Left requires WordPress Multisite and is inactive.', 'fitness-urgency' )
            . '</p></div>';
    }
```

- [ ] **Step 3: Verify the test suite still passes (no WP-runtime code is unit-tested here)**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK (58 tests, ...)`.

- [ ] **Step 4: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-plugin.php
git commit -m "feat: require multisite — guard activation, seed network option

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Plugin header — Network: true + version 2.0.0

`Network: true` makes the plugin network-activate-only (the activation link appears only at network level). Bump the version in both places.

**Files:**
- Modify: `wp-plugin/fitness-urgency/fitness-urgency.php`

- [ ] **Step 1: Add `Network: true` and bump header version**

In the plugin header, change line 5 from `* Version:           1.1.0` to:

```php
 * Version:           2.0.0
```

And add a `Network` line immediately after the `Text Domain` line (line 10), so the header block reads:

```php
 * Text Domain:       fitness-urgency
 * Network:           true
 */
```

- [ ] **Step 2: Bump the FU_VERSION constant**

Change line 15 from `define( 'FU_VERSION',  '1.1.0' );` to:

```php
define( 'FU_VERSION',  '2.0.0' );
```

- [ ] **Step 3: Verify tests still pass**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK (58 tests, ...)`.

- [ ] **Step 4: Commit**

```bash
git add wp-plugin/fitness-urgency/fitness-urgency.php
git commit -m "chore: mark plugin Network-only and bump to 2.0.0

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: FU_Admin — network admin menu + handlers + nonces

Move the settings page to Network Admin → Settings, gate it behind `manage_network_options`, route form submissions through `edit.php?action=...` (the network-admin POST target), and close the `?edit=` nonce gap.

**Files:**
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-admin.php`

- [ ] **Step 1: Replace `init()` and `add_menu()` (lines 6-21)**

```php
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
```

- [ ] **Step 2: Replace `enqueue_scripts()` (lines 23-26)**

Match the dynamic network hook suffix instead of a hardcoded single-site one:

```php
    public static function enqueue_scripts( string $hook ): void {
        if ( $hook !== self::$hook ) return;
        wp_enqueue_style( 'fu-admin', FU_URL . 'assets/css/fu-admin.css', [], FU_VERSION );
    }
```

- [ ] **Step 3: Replace `render_page()` (lines 32-38)**

Add the nonce check on the `?edit=` link and the network capability:

```php
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) return;

        $edit_slug = sanitize_key( $_GET['edit'] ?? '' );
        if ( $edit_slug && ! wp_verify_nonce( $_GET['_fu_edit'] ?? '', 'fu_edit_' . $edit_slug ) ) {
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
```

- [ ] **Step 4: Replace `handle_save()` (lines 44-66)**

```php
    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) wp_die( 'Unauthorised' );
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
            wp_safe_redirect( self::page_url( [ 'error' => rawurlencode( $result->get_error_message() ) ] ) );
            exit;
        }

        FU_Programs::save( $result );
        wp_safe_redirect( self::page_url( [ 'saved' => 1 ] ) );
        exit;
    }
```

- [ ] **Step 5: Replace `handle_delete()` (lines 68-75)**

```php
    public static function handle_delete(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) wp_die( 'Unauthorised' );
        $slug = sanitize_key( $_GET['slug'] ?? '' );
        check_admin_referer( 'fu_delete_' . $slug );
        FU_Programs::delete( $slug );
        wp_safe_redirect( self::page_url( [ 'deleted' => 1 ] ) );
        exit;
    }
```

- [ ] **Step 6: Verify tests still pass**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK (58 tests, ...)`. (Admin is WP-runtime, not unit-tested; this confirms no syntax/load regressions via the autoloader.)

- [ ] **Step 7: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-admin.php
git commit -m "feat: move settings to Network Admin with super-admin gating + nonces

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: admin-settings.php — network-admin URLs

The view's form action, edit link, delete link, and cancel link all point at single-site `admin_url('options-general.php')` / `admin_url('admin-post.php')`. Repoint them at the network-admin equivalents. In network admin, `edit.php` routes on `$_GET['action']`, so the action goes in the form's URL query string (not a hidden field).

**Files:**
- Modify: `wp-plugin/fitness-urgency/views/admin-settings.php`

- [ ] **Step 1: Fix the Edit link (line 31)**

Replace line 31:

```php
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'fitness-urgency', 'edit' => $p['slug'] ], admin_url( 'options-general.php' ) ) ); ?>">Edit</a>
```

with (adds an edit nonce that `render_page()` now verifies):

```php
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'page' => 'fitness-urgency', 'edit' => $p['slug'] ], network_admin_url( 'settings.php' ) ), 'fu_edit_' . $p['slug'], '_fu_edit' ) ); ?>">Edit</a>
```

- [ ] **Step 2: Fix the Delete link (line 33)**

Replace line 33:

```php
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'fu_delete', 'slug' => $p['slug'] ], admin_url( 'admin-post.php' ) ), 'fu_delete_' . $p['slug'] ) ); ?>"
```

with:

```php
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'fu_delete', 'slug' => $p['slug'] ], network_admin_url( 'edit.php' ) ), 'fu_delete_' . $p['slug'] ) ); ?>"
```

- [ ] **Step 3: Fix the form action + drop the now-redundant hidden action field (lines 48-50)**

Replace lines 48-50:

```php
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'fu_save_program' ); ?>
        <input type="hidden" name="action" value="fu_save">
```

with (network `edit.php` reads `action` from the URL query):

```php
      <form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=fu_save' ) ); ?>">
        <?php wp_nonce_field( 'fu_save_program' ); ?>
```

- [ ] **Step 4: Fix the Cancel link (line 127)**

Replace line 127:

```php
          <a href="<?php echo esc_url( add_query_arg( 'page', 'fitness-urgency', admin_url( 'options-general.php' ) ) ); ?>"
```

with:

```php
          <a href="<?php echo esc_url( add_query_arg( 'page', 'fitness-urgency', network_admin_url( 'settings.php' ) ) ); ?>"
```

- [ ] **Step 5: Verify tests still pass**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK (58 tests, ...)`.

- [ ] **Step 6: Commit**

```bash
git add wp-plugin/fitness-urgency/views/admin-settings.php
git commit -m "feat: point settings view at network-admin URLs and nonce edit links

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Audit hygiene — escaping + date/timezone try-catch (TDD for renderer)

Fold in the small audit fixes while we're in these files. The renderer fallbacks are unit-testable; write those tests first.

**Files:**
- Modify: `wp-plugin/fitness-urgency/views/card.php:33`
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-shortcodes.php` (countdown attr)
- Modify: `wp-plugin/fitness-urgency/includes/class-fu-renderer.php` (constructor + `parse_iso_date`)
- Modify: `wp-plugin/fitness-urgency/tests/RendererTest.php` (new fallback tests)

- [ ] **Step 1: Write failing renderer tests**

Append these two test methods inside the `RendererTest` class (before its closing `}`):

```php
    public function test_invalid_timezone_falls_back_to_utc(): void {
        $r = new FU_Renderer( [ 'timezone' => 'Not/AZone' ] );
        // Should not throw; a valid date should still parse.
        $d = $r->parse_iso_date( '2026-06-08' );
        $this->assertSame( '2026-06-08', $d->format( 'Y-m-d' ) );
    }

    public function test_malformed_date_throws_invalid_argument(): void {
        $r = new FU_Renderer( [ 'timezone' => 'UTC' ] );
        $this->expectException( \InvalidArgumentException::class );
        $r->parse_iso_date( 'garbage' );
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/RendererTest.php`
Expected: FAIL — the invalid timezone currently throws `Exception` from the constructor (not caught), and `parse_iso_date('garbage')` currently triggers a PHP `explode`/`createFromFormat` error rather than a clean `InvalidArgumentException`.

- [ ] **Step 3: Add try/catch to the constructor**

In `includes/class-fu-renderer.php`, replace line 28 `$this->tz = new \DateTimeZone( $tz_string );` with:

```php
        try {
            $this->tz = new \DateTimeZone( $tz_string );
        } catch ( \Exception $e ) {
            $this->tz = new \DateTimeZone( 'UTC' );
        }
```

- [ ] **Step 4: Validate input in `parse_iso_date()`**

Replace `parse_iso_date()` (lines 140-147) with:

```php
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
```

- [ ] **Step 5: Run renderer tests to verify they pass**

Run: `./vendor/bin/phpunit tests/RendererTest.php`
Expected: PASS — all renderer tests (55 now) green.

- [ ] **Step 6: Escape `SOLD OUT` in the card view**

In `views/card.php`, replace line 33 `echo 'SOLD OUT';` with:

```php
        echo esc_html( 'SOLD OUT' );
```

- [ ] **Step 7: Escape the countdown timestamp attribute**

In `includes/class-fu-shortcodes.php`, in the `sprintf` argument list, replace the `$target_ts,` argument (line 201) with:

```php
            esc_attr( (string) $target_ts ),
```

and change the format specifier for that attribute (line 194) from `data-fu-countdown="%d"` to `data-fu-countdown="%s"`:

```php
            '<span class="%s" data-fu-countdown="%s" data-fu-program="%s">'
```

- [ ] **Step 8: Run the full suite**

Run: `./vendor/bin/phpunit tests/`
Expected: `OK (60 tests, ...)` — 55 renderer + 5 programs.

- [ ] **Step 9: Commit**

```bash
git add wp-plugin/fitness-urgency/includes/class-fu-renderer.php wp-plugin/fitness-urgency/tests/RendererTest.php wp-plugin/fitness-urgency/views/card.php wp-plugin/fitness-urgency/includes/class-fu-shortcodes.php
git commit -m "fix: escape outputs and guard timezone/date parsing

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Documentation — designer guide + CLAUDE.md

**Files:**
- Modify: `wp-plugin/DESIGNER-INSTRUCTIONS.md`
- Modify: `CLAUDE.md`

- [ ] **Step 1: Update the designer guide**

In `wp-plugin/DESIGNER-INSTRUCTIONS.md`, find the install/usage section and ensure it states the multisite workflow. Add (or adapt) a section near the top titled "Where dates are managed":

```markdown
## Where dates are managed (Multisite)

DWC - Spots Left is a **network-activated, multisite-only** plugin. All
campaign dates are configured **once** in **Network Admin → Settings →
DWC - Spots Left** by a super admin. Every subsite reads that single shared
configuration, so the same shortcode renders identically on every funnel.

Subsites have **no settings page**. To use a campaign on a subsite, just place
its shortcode in the page, e.g. `[fu_card]` for the default campaign or
`[fu_card program="spring-2026"]` for a specific one. A campaign intended for a
single subsite is still created at the network level; you simply place its
shortcode only on that subsite.
```

(If an older single-site "manage dates in Settings → DWC" instruction exists, remove or replace it so the guide is consistent.)

- [ ] **Step 2: Update CLAUDE.md current status + version history**

In `CLAUDE.md`, under "Current status", change the version line to:

```markdown
- **Version**: 2.0.0 (plugin), pushed on branch `claude/fitness-urgency-script-RtPun`.
```

Update the test count line to reflect the new total:

```markdown
- **Tests**: 60 PHPUnit tests pass (`FU_Renderer` + `FU_Programs`).
```

Add a new entry at the top of the "Version history" section:

```markdown
- **2.0.0** — Multisite-only. Program/date config moved from per-site
  `get_option` to network-wide `get_site_option`/`update_site_option`, managed
  in Network Admin → Settings (super admins, `manage_network_options`). Plugin
  header marked `Network: true`; activation blocked on non-multisite. Render
  path unchanged so shortcodes stay identical across subsites. Added
  `FU_Programs` per-request cache + unit tests; folded in audit fixes (output
  escaping, timezone/date try-catch). 53 → 60 tests.
```

Also update the architecture/storage note in CLAUDE.md where it mentions
`fu_programs` option storage to say it is now a **network** option
(`get_site_option`).

- [ ] **Step 3: Commit**

```bash
git add wp-plugin/DESIGNER-INSTRUCTIONS.md CLAUDE.md
git commit -m "docs: document multisite network-config workflow and v2.0.0

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Rebuild the installable ZIP + final verification

**Files:**
- Modify: `wp-plugin/dwc-spots-left.zip`

- [ ] **Step 1: Run the full test suite one final time**

Run (from `wp-plugin/fitness-urgency/`): `./vendor/bin/phpunit tests/`
Expected: `OK (60 tests, ...)`.

- [ ] **Step 2: Rebuild the ZIP**

Run from the repo root:

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

- [ ] **Step 3: Verify the ZIP contents and size**

Run: `unzip -l wp-plugin/dwc-spots-left.zip | grep -E 'fitness-urgency.php|vendor|tests' ; ls -la wp-plugin/dwc-spots-left.zip`
Expected: top-level `fitness-urgency/` present, **no** `vendor/` or `tests/` entries, size ≤ ~25 KB. Confirm `fitness-urgency/fitness-urgency.php` is present.

- [ ] **Step 4: Confirm the header version inside the ZIP**

Run: `unzip -p wp-plugin/dwc-spots-left.zip fitness-urgency/fitness-urgency.php | grep -E 'Version:|Network:'`
Expected: `Version: 2.0.0` and `Network: true`.

- [ ] **Step 5: Commit**

```bash
git add wp-plugin/dwc-spots-left.zip
git commit -m "build: rebuild dwc-spots-left.zip for v2.0.0

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Manual verification (for the user, post-merge)

These cannot be unit-tested; the user should confirm on the live network:

1. Network-activate the plugin. Confirm **Network Admin → Settings → DWC - Spots Left** appears and a regular site admin sees **no** settings page.
2. Set dates once in Network Admin; save.
3. Open `[fu_card]` on two different subsites — confirm identical output.
4. Confirm a `program="slug"`-targeted campaign shows only where its shortcode is placed.
5. Confirm activating on a non-multisite install is blocked with the multisite message.

---

## Self-Review notes

- **Spec coverage:** storage swap (Task 2), network admin + capability + nonces (Tasks 5-6), activation guard + seed (Task 3), `Network: true` + version (Task 4), render path untouched (verified — no edits to slot/phase logic), audit fixes (Task 7), tests (Tasks 1-2,7), docs + ZIP (Tasks 8-9). All spec sections mapped.
- **Type consistency:** `flush_cache()`, `page_url()`, `self::$hook`, `self::$cache` are defined where first used and referenced consistently. Option key `fu_programs` unchanged throughout.
- **No placeholders:** every code/command step contains concrete content.
