# Design: Network-Level Configuration for DWC - Spots Left (Multisite)

**Date:** 2026-05-22
**Plugin:** DWC - Spots Left (`wp-plugin/fitness-urgency/`)
**Target version:** 2.0.0 (breaking change — config storage relocates)
**Status:** Approved design, pending implementation plan

---

## Problem

The agency runs 40+ client funnels at once, all using the **same** weekly
Monday start dates. Today the plugin stores its program/date config in a
per-site WordPress option (`get_option('fu_programs')`). On a multisite
network that means every subsite has its own independent copy of the dates —
so configuring a cycle means editing 40+ sites, and the shortcodes can drift
out of sync.

The goal: **configure the dates once, at the network level, and have every
subsite's shortcodes render identically** — so funnels are trivially
replicable across subsites.

## Goals

- Date/program config is managed in **one place**: WordPress **Network Admin**.
- All subsites read that single shared config; shortcode output is identical
  everywhere (byte-for-byte).
- Subsites need **no configuration UI** — install/network-activate the plugin,
  drop in shortcodes, done.
- The existing `program="slug"` multi-program mechanism becomes the
  "multiple campaigns" model: every campaign is defined at the network level;
  a campaign for a single subsite is still defined network-side and simply
  referenced only on that subsite's pages.

## Non-Goals (YAGNI)

- **No per-subsite overrides.** Config is strictly shared (the user explicitly
  chose this). Subsites cannot change dates.
- **No single-site / non-multisite support.** This is a fresh install on an
  existing multisite network. The legacy single-site v1.1.0 install and the
  standalone CDN version are out of scope and not migrated.
- **No data migration.** Fresh install; dates are re-entered once at the
  network level. We do not read or migrate any existing per-subsite
  `fu_programs` option.
- **No subsite read-only "current dates" panel** (considered as Approach B,
  deferred — can be added later without rework).

## Decisions (locked during brainstorming)

| Decision | Choice |
|---|---|
| Config model | **Strict shared** — one network config, no overrides |
| Install type | **Already a multisite**; plugin network-activated |
| Single-site support | **Dropped** — multisite-only, fresh install |
| Migration | **None** — fresh start, re-enter dates once |
| Subsite UI | **None** — shortcodes only |
| Admin location | **Network Admin → Settings → DWC - Spots Left** |
| Edit capability | `manage_network_options` (super admins only) |
| Version | **2.0.0** |

---

## Architecture

The change is deliberately narrow. Two classes change behavior
(`FU_Programs`, `FU_Admin`), the bootstrap gains a multisite guard, and the
render path (`FU_Shortcodes`, `FU_Renderer`) is **untouched** — that's the
crux of "identical shortcodes everywhere."

### 1. Storage layer — `FU_Programs` (`includes/class-fu-programs.php`)

Relocate the store from per-site options to the network store:

| Current | New |
|---|---|
| `get_option( 'fu_programs', [] )` (line ~18) | `get_site_option( 'fu_programs', [] )` |
| `update_option( 'fu_programs', $all )` (save, line ~70) | `update_site_option( 'fu_programs', $all )` |
| `update_option( 'fu_programs', $all )` (delete, line ~83) | `update_site_option( 'fu_programs', $all )` |

`get_site_option`/`update_site_option` read/write a single row in
`wp_sitemeta`, shared by the whole network. Every subsite sees the same data.

All other `FU_Programs` logic is unchanged: slug sanitization, date
validation, default-program promotion on delete, etc.

**Per-request cache (audit win):** add a static `$cache` so repeated
`FU_Programs::all()` calls within one page request hit the option store once.
Invalidate the static cache in `save()` and `delete()`.

The `fu_programs` option **key name is unchanged** — only the storage
function changes. (Per CLAUDE.md naming conventions; the key is internal.)

### 2. Admin — `FU_Admin` (`includes/class-fu-admin.php`)

- **Menu registration:** hook `network_admin_menu` instead of `admin_menu`.
  Register under the Network Admin **Settings** menu →
  page title "DWC - Spots Left".
- **Capability:** `manage_network_options` (super admins) replaces the
  single-site `manage_options`.
- **Form submission:** network admin pages cannot post to
  `options.php`/`admin-post.php` the single-site way. The form posts to
  `edit.php?action=fu_save_programs` and the handler is hooked on
  `network_admin_edit_fu_save_programs`, validates a nonce + capability,
  writes via `FU_Programs`, then `wp_safe_redirect()`s back to the settings
  page with a status query arg.
- **Nonces:** add nonce verification to the save handler **and** to the
  `?edit=<slug>` link read in `render_page()` (closes the audit nonce gap at
  `class-fu-admin.php:35`).
- **View:** `views/admin-settings.php` is reused. Only the `<form action>`
  target and any URLs change to network-admin equivalents
  (`network_admin_url()`). The program/date editing fields are unchanged.

### 3. Guards & activation — `fitness-urgency.php` / `FU_Plugin`

- **Activation guard:** in the `register_activation_hook` callback, if
  `! is_multisite()`, call `deactivate_plugins()` and `wp_die()` with a clear
  message: *"DWC - Spots Left requires WordPress Multisite."*
- **Seed default program** on activation via `update_site_option` (not
  `update_option`), only if the network option does not already exist.
- **Runtime notice:** if the plugin somehow runs on a non-multisite install,
  show an admin notice rather than misbehaving silently.

### 4. Render path — `FU_Shortcodes` + `FU_Renderer` (NO behavioral change)

These read program data exclusively through `FU_Programs::all()` / `get()`,
so they transparently pick up the network config. No shortcode names,
attributes, CSS classes, or output change. The designer's existing shortcodes
keep working identically across all subsites — the central requirement.

While editing these files for the version bump / fixes, fold in the cheap
audit hygiene fixes (see below).

### 5. Audit fixes folded in (touching these files anyway)

- Escape hardcoded output: `views/card.php:33` (`SOLD OUT`) and the countdown
  `data-fu-countdown` timestamp attribute (`class-fu-shortcodes.php:194`) via
  `esc_html` / `esc_attr`.
- Wrap `DateTimeZone` construction (`class-fu-renderer.php:27`) and
  `parse_iso_date()` (`class-fu-renderer.php:140`) in try/catch with a safe
  fallback (UTC / skip the bad date) so a misconfigured timezone or corrupt
  date string degrades gracefully instead of throwing a fatal.

Backlog (NOT in this change): PHP↔JS ordinal duplication, dead `$first` in
`compute_phase()`. Tracked, not blocking.

---

## Data flow

```
Super Admin (Network Admin → Settings → DWC - Spots Left)
        │  save (nonce + manage_network_options)
        ▼
FU_Admin::handle_save()  ── network_admin_edit_fu_save_programs
        │
        ▼
FU_Programs::save()  ──►  update_site_option('fu_programs', …)   [wp_sitemeta]
                                        │
        ┌───────────────────────────────┴───────────────────────────┐
        ▼ (any subsite, any page request)                            ▼
FU_Shortcodes::resolve()                                   fu-refresh.js
        │  FU_Programs::all() → get_site_option('fu_programs')  (reads same
        ▼  (static-cached per request)                          inlined data)
FU_Renderer (unchanged date/spots/phase logic)
        ▼
Identical HTML on every subsite
```

## Error handling

- Activated on non-multisite → blocked at activation with a clear message.
- Save without valid nonce / insufficient capability → request rejected.
- Invalid timezone string → `FU_Renderer` falls back to UTC.
- Date validation boundary is the storage layer: `FU_Programs::sanitize()`
  only persists strings matching `^\d{4}-\d{2}-\d{2}$` (and the admin uses an
  `<input type="date">` picker), so malformed dates cannot reach the renderer
  in normal operation. As a fail-fast internal contract, `parse_iso_date()`
  throws `InvalidArgumentException` on a non-matching or unparseable string
  rather than silently producing a bad value — surfacing programmer error or
  direct-DB tampering at the point of failure. (Backlog: the regex accepts
  numerically-impossible dates like `2026-13-01`, which `createFromFormat`
  rolls over rather than rejecting; a `checkdate()` guard would close this, but
  the picker + sanitizer make it non-reachable today.)
- Empty network config → shortcodes render nothing (existing behavior),
  no fatals.

## Testing

- **`FU_Renderer` (53 existing tests):** unchanged behavior; must stay green
  (`OK (53 tests, 220 assertions)`).
- **`FU_Programs` (new):** add lightweight unit tests that stub
  `get_site_option`/`update_site_option` (define them in the test bootstrap
  under `FU_TESTING`) and assert `all()`/`save()`/`delete()` read and write
  the network store, that the per-request static cache works and is
  invalidated on save/delete, and that default-promotion-on-delete still
  holds.
- **Manual multisite smoke test (documented for the user):** network-activate,
  set dates once in Network Admin, confirm two different subsites render
  identical `[fu_card]` output.

## Deliverables

- Code changes in `class-fu-programs.php`, `class-fu-admin.php`,
  `fitness-urgency.php` (+ minor escaping/try-catch in `card.php`,
  `class-fu-shortcodes.php`, `class-fu-renderer.php`).
- New `FU_Programs` tests + bootstrap stubs.
- Version bump to 2.0.0 (header + `FU_VERSION`).
- Rebuilt `wp-plugin/dwc-spots-left.zip`.
- Updated `wp-plugin/DESIGNER-INSTRUCTIONS.md` ("dates are managed in Network
  Admin; subsites only place shortcodes").
- Updated `CLAUDE.md` status/version history.

## Rollout notes

- This is **2.0.0**, a breaking change: config moves from per-site to network
  store. Because it's a documented fresh install, there is no in-place upgrade
  path and none is required.
- Page caching caveat still applies (documented): exclude landing pages from
  cache or keep lifetime ≤ 24h.
