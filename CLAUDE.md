# CLAUDE.md — Project Handoff

This file is the entry point for any Claude Code (or human) session picking
up this project. Read it first; it's the single source of truth for what
the project is, where it stands, and how to develop it further.

---

## What this repository is

A "spots left" urgency countdown for landing pages selling a recurring
fitness program with weekly Monday start dates. Two implementations share
the same spec:

1. **WordPress plugin** at `wp-plugin/fitness-urgency/` — production target.
   Plugin display name: **DWC - Spots Left**. Folder/file names retain the
   legacy `fitness-urgency` slug so existing installs aren't broken.
2. **Standalone JS at the repo root** (`fitness-urgency.js`, `demo.html`,
   `elementor-snippet.html`) — the original CDN-hosted version, kept for
   reference. The WP plugin is the active codebase; new work should go
   there unless the user asks otherwise.

The user is a non-technical operator. Their designer drops shortcodes into
Elementor / Gutenberg pages; the user manages dates in WP admin. Nobody
edits code per cycle.

---

## Current status

- **Version**: 1.1.0 (plugin), pushed on branch `claude/fitness-urgency-script-RtPun`.
- **Tests**: 53 PHPUnit tests pass against `FU_Renderer`.
- **Deploy artifact**: `wp-plugin/dwc-spots-left.zip` (rebuilt on every
  release).
- **Installed**: User has installed and confirmed v1.1.0 works on their
  live WordPress site.

---

## Repository layout

```
/                                         ← repo root
├── CLAUDE.md                             (this file)
├── DESIGNER-INSTRUCTIONS.md              (legacy CDN-version designer guide)
├── demo.html                             (legacy CDN demo)
├── elementor-snippet.html                (legacy CDN drop-in)
├── fitness-urgency.js                    (legacy CDN script)
├── test-fitness-urgency.js               (legacy 32-case spec test, Node)
├── test-fitness-urgency-vars.js          (legacy 6-case variable test)
└── wp-plugin/
    ├── DESIGNER-INSTRUCTIONS.md          (active designer guide — keep in sync with code)
    ├── dwc-spots-left.zip                (installable plugin ZIP)
    └── fitness-urgency/
        ├── fitness-urgency.php           (plugin header, autoloader, bootstrap)
        ├── composer.json, phpunit.xml
        ├── includes/
        │   ├── class-fu-plugin.php       (top-level init)
        │   ├── class-fu-programs.php     (CRUD on the fu_programs option)
        │   ├── class-fu-renderer.php     (pure-PHP date/spots/phase logic — no WP deps; testable)
        │   ├── class-fu-admin.php        (admin menu + form handlers)
        │   └── class-fu-shortcodes.php   (registers all shortcodes; enqueues frontend assets)
        ├── views/
        │   ├── admin-settings.php       (settings page template)
        │   └── card.php                  ([fu_card] template)
        ├── assets/
        │   ├── css/fitness-urgency.css   (frontend card styles, scoped to .fu-urgency)
        │   ├── css/fu-admin.css          (settings-page styles)
        │   └── js/fu-refresh.js          (midnight rollover + countdown ticker)
        └── tests/
            ├── bootstrap.php
            └── RendererTest.php          (53 tests)
```

---

## Naming conventions (intentional, do not "fix" these)

Internal identifiers use the original `fu` / `FU_` prefix; the rebrand was
display-only.

| Layer | Identifier | Rationale |
|---|---|---|
| Plugin display name | `DWC - Spots Left` | What the user wants people to see |
| Folder / main file | `fitness-urgency/fitness-urgency.php` | WP plugin slug; renaming would orphan the install |
| WP option key | `fu_programs` | Renaming would lose saved program data |
| PHP classes | `FU_Renderer`, `FU_Programs`, … | Internal |
| Shortcodes | `fu_card`, `fu_startdate`, … | Already in customer pages |
| CSS classes | `.fu-urgency`, `.fu-card`, `.fu-cd-days`, … | Designer's custom CSS targets these |

If a future task involves "rename everything", flag it: changing shortcodes
or CSS classes silently breaks live pages and will require a coordinated
designer update.

---

## The spec (codified in `FU_Renderer`)

### Spots-left table

`T` is the integer days from today (site-local midnight) until a given
start date.

| T              | Display       |
|----------------|---------------|
| ≥ 14           | 9 spots left  |
| 13–12          | 8 spots left  |
| 11–10          | 7 spots left  |
| 9–8            | 6 spots left  |
| 7              | 5 spots left  |
| 6              | 4 spots left  |
| 5              | 3 spots left  |
| 4              | 2 spots left  |
| 3–2            | 1 spot left   |
| 1, 0, −1       | SOLD OUT      |
| ≤ −2           | (past — date drops out) |

Closed-form for T ≥ 8: `5 + ceil((T − 7) / 2)`.

### Slot transitions

- **Slot 1** = the earliest program date `D` where `today ≤ D + 1` (a
  past Monday stays as slot 1 / SOLD OUT through Tuesday, then on
  Wednesday it drops out and the next pair rolls in).
- **Slot 2** = the next program date after slot 1, if any.
- **Single-date days** (Wed–Sat before final Monday): only slot 1, slot 2
  hidden.
- **Final-Monday sell-out window** (Sun–Tue around final Monday): slot 1
  is the SOLD OUT final, slot 2 becomes the configurable high-price
  message ("Start next Monday — 2 spots left", yellow card).
- **After cycle fully ends** (Wed after last Tuesday): slot 1 itself
  shows the high-price message; slot 2 hidden.

### Phase windows (added in v1.1.0)

For a banner above the cards. Let `D1` = first program date,
`Dlast` = last program date.

| Phase         | First visible | Last visible   |
|---------------|---------------|----------------|
| `high_demand` | `D1 + 2` (Wed after first Monday) | `Dlast − 9` (Sat before final week) |
| `final_week`  | `Dlast − 8`   | `Dlast − 2` (Sat before final Monday) |

Live `[fu_countdown]` ticks down to **midnight site-local on `Dlast − 1`**
(end of the final-week window). After `Dlast − 2`, both phases are off
until the user updates the dates for the next cycle.

For `Jun 8 / 15 / 22 / 29` example: `high_demand` = Jun 10–20,
`final_week` = Jun 21–27, countdown target = Jun 28 00:00.

### Date format

Ordinal suffix: `Monday, June 8th`, `June 29th`. Implemented in
`FU_Renderer::format_date()` and `format_date_short()`. JS mirror:
`formatDate()` and `formatDateShort()` in `fu-refresh.js`.

---

## Shortcodes (full reference)

| Shortcode | Required | Optional | Output |
|---|---|---|---|
| `[fu_card]` | — | `program`, `class` | Two-card urgency block |
| `[fu_startdate slot="1\|2"]` | `slot` | `program`, `class` | `<span>` with date text |
| `[fu_spotsleft slot="1\|2"]` | `slot` | `program`, `class` | `<span>` with spots text |
| `[fu_finaldate]` | — | `program`, `class` | `<span>` with last date as "June 29th" |
| `[fu_show_if_slot slot="1\|2"]…[/]` | `slot` | `program` | Wrapper, hidden when slot has no data |
| `[fu_show_phase phase="high_demand\|final_week"]…[/]` | `phase` | `program` | Wrapper, hidden when phase inactive |
| `[fu_countdown]` | — | `program`, `class` | Live D/H/M/S countdown |

`program` defaults to whichever program is flagged "default" in WP admin.

---

## Architecture

**Hybrid render: PHP first, JS rolls over.**

1. **PHP server-side** (every page request): `FU_Shortcodes` resolves the
   active program, instantiates `FU_Renderer` with the site's WP timezone,
   and outputs the correct HTML — no flash of empty content, SEO-safe,
   cache-safe at the WP level.
2. **JS client-side** (`fu-refresh.js`):
   - Reads `fuRefreshData` (inlined by `FU_Shortcodes::enqueue_frontend()` —
     `{ programs: { slug: { dates, highPriceMessage, highPriceSpots } }, tzOffset }`).
   - Schedules a single `setTimeout` to fire 5 s after the next site-local
     midnight, runs `refresh()`, then schedules the next.
   - `refresh()` re-runs the full slot/phase/variable computation and
     updates: `.fu-urgency .fu-card` (full cards), `[data-fu-var]` spans
     (inline shortcodes incl. `finaldate`), `[data-fu-show-if-slot]`
     wrappers, `[data-fu-phase]` wrappers.
   - `tickCountdowns()` runs every second via `setInterval` to update
     `.fu-cd-days/hours/minutes/seconds` inside `[data-fu-countdown]`. The
     target Unix timestamp is in the DOM (computed by PHP).
3. **Test suite** (`tests/RendererTest.php`): exercises `FU_Renderer` in
   isolation with `FU_TESTING` defined so `class-fu-renderer.php` doesn't
   require WP. 53 tests, 220 assertions.

`FU_Renderer` has zero WP dependencies — that's intentional. All WP-aware
code (options, timezone, shortcode atts, enqueueing) lives in the other
classes.

---

## Development workflow

### Setup

```bash
cd wp-plugin/fitness-urgency
composer install              # installs phpunit
```

### Run the test suite

```bash
cd wp-plugin/fitness-urgency
./vendor/bin/phpunit tests/
```

Should print `OK (53 tests, 220 assertions)`. **Update tests whenever
`FU_Renderer` behavior changes.** The slot data provider in
`RendererTest::slotProvider()` is the canonical day-by-day spec.

### Rebuild the installable ZIP

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
```

The ZIP must:
- Contain a top-level `fitness-urgency/` folder (plugin slug).
- Exclude dev dependencies (`vendor/`, tests, composer files).
- Be ≤ ~25 KB (currently 20 KB).

### Bumping the version

Update both:
- `wp-plugin/fitness-urgency/fitness-urgency.php` header `Version:`
- The same file's `define( 'FU_VERSION', '...' )`

…then rebuild the ZIP.

### Branch & commit conventions

- Active branch: `claude/fitness-urgency-script-RtPun`.
- One commit per logical change. Don't squash unrelated work.
- Commit messages: short subject line, blank line, bullet-list body
  describing the *why*. End with the
  `https://claude.ai/code/session_…` trailer when applicable.
- Don't push to `main` directly; if a merge is needed, open a PR.
- `git status` should be clean at the end of every task — there's a stop
  hook that complains about uncommitted changes.

---

## Cross-cutting caveats

- **Page caching breaks urgency.** Tell the user to either exclude landing
  pages from cache or set lifetime ≤ 24 h. Documented in
  `wp-plugin/DESIGNER-INSTRUCTIONS.md`. JS midnight refresh fixes the
  for-open-tabs case but not the cached-HTML case.
- **Browser timezone vs site timezone.** `fu-refresh.js::siteToday()`
  rebuilds today's date using `data.tzOffset` from PHP. Subtle but the
  existing code is correct; don't "simplify" it without re-reading the
  comments.
- **WP multisite installs** require Network Admin → Plugins → Add New.
  Site-level admins won't see "Add New". Documented in the designer guide.
- **Shortcodes on raw HTML pages** (Elementor HTML widget, etc.) need to
  be inside a context where `do_shortcode()` runs. WP normally does this
  for post content; for custom widgets, designers may need to use
  Elementor's "Shortcode" widget, not "HTML".

---

## Things to keep in mind when extending

- Anything that adds a new piece of urgency text should follow the same
  pattern: PHP renders the initial HTML with state classes, JS in
  `refresh()` re-computes after midnight.
- New shortcodes need to be added to **two** lists:
  - `FU_Shortcodes::init()` (the `add_shortcode` calls)
  - `FU_Shortcodes::enqueue_frontend()` (the `has_shortcode` loop) —
    otherwise the JS / CSS won't load on pages that only use the new
    shortcode.
- Tests live in `tests/RendererTest.php`. Add data-provider rows for new
  spec cases rather than new test methods when possible.
- Keep the `dwc-spots-left.zip` artifact in git — the user's designer
  pulls it directly from the repo to install.

---

## Open ideas / possible next steps

These have been discussed but not implemented. The user has not
prioritized them; ask before starting any of these.

- **Per-program message overrides** for the high-demand and final-week
  banners (currently the designer hard-codes the copy in the page).
- **Multiple time-zone support** for selling to international audiences
  (currently uses WP's site timezone for everything).
- **Scheduled cycle data** — admin UI to define multiple future cycles in
  advance so the user doesn't have to log in to update dates each cycle.
- **Analytics hooks** — fire a `dataLayer.push` when a phase becomes
  active or the countdown crosses a threshold.
- **Email/SMS notifications** when a spot count hits a threshold (would
  need WP-Cron or external integration).
- **Block editor (Gutenberg) blocks** wrapping each shortcode for nicer
  in-editor UX. Currently designers use the Shortcode block.

---

## Version history

- **1.1.0** — Ordinal date format ("Monday, June 8th"). New shortcodes:
  `[fu_finaldate]`, `[fu_show_phase]`, `[fu_countdown]`. Phase logic in
  `FU_Renderer` (`compute_phase`, `countdown_target_timestamp`). Live
  countdown ticker. Fixed `[fu_show_if_slot]` to render a wrapper so JS
  midnight refresh works. 38 → 53 tests. Display name: "DWC - Spots Left".
- **1.0.0** — Initial WP plugin port of the standalone JS. Multi-program
  support via `program="slug"` shortcode attr. Admin UI for managing
  programs. PHP server render + JS midnight refresh. 38 tests.

---

## How to take this to the next level

Possible "next-level" projects (in rough order of value):

1. **Add programs in bulk via CSV upload** so a year of cycles can be
   loaded in one go.
2. **Per-program A/B copy testing** — admin UI lets the user define two
   variants of the high-demand / final-week banner copy and the plugin
   serves them 50/50, fires a custom event for analytics.
3. **GitHub Action that publishes new release ZIPs** when a tag is
   pushed, so the designer always pulls the latest from a stable URL.
4. **Convert to a "DWC Marketing Toolkit" mono-plugin** — same admin
   container, multiple modules (urgency, social proof, exit intent, etc.).
5. **Replace the Composer/PHPUnit setup with `wp-env`** for full WP
   integration tests (currently only `FU_Renderer` is unit-tested).

When picking one of these up: re-read this file, then `wp-plugin/
DESIGNER-INSTRUCTIONS.md`, then `tests/RendererTest.php` to absorb the
spec. Run the test suite as a sanity check before changing anything.
