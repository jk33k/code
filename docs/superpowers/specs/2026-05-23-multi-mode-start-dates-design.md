# Design: Multi-Mode Start Dates (single / double / triple / fixed / ongoing)

**Date:** 2026-05-23
**Plugin:** DWC - Spots Left (`wp-plugin/fitness-urgency/`)
**Target version:** 2.1.0 (additive feature)
**Status:** Approved design, pending implementation plan

---

## Problem

Today the plugin assumes a fixed list of weekly Monday start dates (typically
4). Operators want more shapes of campaign:

1. **Single start date** — one date, with the spots countdown beginning a
   bounded number of days before it.
2. **Two / three start dates** — the same rolling logic as today, just with
   fewer dates.
3. **Ongoing (evergreen)** — no final date; new Mondays keep appearing
   indefinitely so a funnel can run forever without anyone updating dates.

The mode should be inferred automatically from what the operator enters, with
a dedicated keyword (`ongoing`) for the evergreen case.

## Key insight (current engine is already generic)

`FU_Renderer::compute_slots()` already works for any number of dates: it picks
slot 1 as the first date not yet more than 1 day past, slot 2 as the next, and
falls back to the high-price card when the list runs out. So **single / double
/ triple / fixed already behave correctly today** — the only genuinely new
behavior is *ongoing* (generated dates, no end) and a *21-day visibility gate*
for single mode. This keeps the change small and low-risk.

## Decisions (locked during brainstorming)

| Decision | Choice |
|---|---|
| Mode detection | By date count (1/2/3/4+), plus the keyword `ongoing` for evergreen |
| Evergreen entry in admin | A dedicated **"Schedule" text field**; typing `ongoing` enables evergreen; otherwise blank and use the date pickers |
| Spots formula | **Unchanged, uncapped** (current `spots_for_days_until`) |
| Single-date lead | **21-day gate**: hidden until 21 days out, then normal countdown |
| When single date > 21 days out | **Show nothing** (no card, no date, no spots) |
| 21-day gate scope | **Single-date mode only**; double/triple/fixed unchanged |
| Ongoing anchor | **Today** — generate the upcoming Mondays on the fly |
| Ongoing slots | Always **two** upcoming Mondays; **never** the high-price/sold-out-final end |
| Ongoing countdown/phases | `[fu_countdown]`, `[fu_show_phase]` render **nothing** in ongoing mode |
| Version | **2.1.0** |

## Non-Goals (YAGNI)

- No change to the spots table / numbers.
- No 21-day (or any) gate for double/triple/fixed modes.
- No per-mode custom copy beyond the existing `high_price_label` / spots.
- No new shortcodes. All existing shortcodes keep working in every mode.
- Ongoing cadence is weekly Mondays only (same cadence assumption as today).

---

## Mode resolution

A pure, testable function decides the mode from stored config:

```
resolve_mode(dates[], evergreen):
    if evergreen            -> 'ongoing'
    elif count(dates) == 1  -> 'single'
    elif count(dates) == 2  -> 'double'
    elif count(dates) == 3  -> 'triple'
    else                    -> 'fixed'   // 4+ (or 0 -> nothing renders)
```

`double`, `triple`, and `fixed` are the *same code path* (current
`compute_slots`); the mode name exists only for clarity/JS parity. The only
behavioral branches are **single** (21-day gate) and **ongoing** (generated
dates + suppressed countdown/phases).

## Behavior per mode

| Mode | Dates used | Slot behavior | Countdown / phases | High-price end card |
|---|---|---|---|---|
| single | the 1 date | hidden until ≤21 days out, then current curve → SOLD OUT | work (off the single date) | yes (as today) |
| double / triple | the 2–3 dates | current rolling logic | work | yes |
| fixed | 4+ dates | **unchanged — exactly today** | work | yes |
| ongoing | generated from today | always 2 upcoming Mondays, rolls weekly forever | **suppressed (output nothing)** | never |

### Single-date 21-day gate

With the uncapped formula, day 21 starts at *"12 spots left"* and reaches 9 by
day 14 — accepted by the operator. Mechanically the gate is an optional
parameter on `compute_slots`:

```
compute_slots(date_strings, today, max_lead_days = null)
```

- `max_lead_days = null` → today's behavior (no gate). Used by
  double/triple/fixed/ongoing.
- `max_lead_days = 21` → if the resolved slot 1 is a `date` with
  `days_until > 21`, return `[]` (renders nothing). Used by single mode.

The gate only suppresses the *lead* window. Once inside 21 days, the SOLD-OUT
and high-price end behavior is identical to today.

### Ongoing generation

New helper:

```
upcoming_mondays(count, today) -> string[]   // ISO 'YYYY-MM-DD'
```

- Anchor = the Monday on or before `today` (i.e. Monday of the current ISO
  week). Then anchor, anchor+7, anchor+14, … for `count` entries.
- The renderer calls it with `count = 5` and feeds the result into the
  **same** `compute_slots` (no `max_lead_days`).
- Because the most-recent Monday is included, a Monday stays slot 1 through the
  Tuesday after it (days_until = −1) and drops on Wednesday, when the next
  Monday rolls into slot 1 and a further Monday appears in slot 2 — matching
  the existing weekly rollover. 5 Mondays guarantee slot 1 and slot 2 always
  resolve, so ongoing **never** hits the high-price branch.
- Worked example (today Fri 2026-05-22): slot 1 = Mon 05-25 (*1 spot left*,
  T=3), slot 2 = Mon 06-01 (*7 spots left*). Sun 05-24: 05-25 SOLD OUT (T=1).
  Wed 05-27: 06-01 → slot 1, 06-08 appears in slot 2.

### Ongoing suppresses countdown & phases

`[fu_countdown]` and `[fu_show_phase]` depend on a fixed last date, which does
not exist in ongoing mode. In ongoing mode both render an empty string. (The
phase/countdown logic is otherwise unchanged and still works for
single/double/triple/fixed, computed off the first/last configured date.)

---

## Architecture & components

`FU_Renderer` stays the pure, WP-free core. New/changed units:

- **`FU_Renderer::resolve_mode(array $dates, bool $evergreen): string`** — pure
  mode resolver (above).
- **`FU_Renderer::upcoming_mondays(int $count, ?\DateTimeInterface $today): array`**
  — generates ISO Monday strings from today.
- **`FU_Renderer::compute_slots(..., ?int $max_lead_days = null)`** — add the
  optional gate parameter (backward compatible; existing calls unchanged).

`FU_Shortcodes` becomes mode-aware in one place — the program/date resolution:

- Resolve `mode` from the program (`dates` + `evergreen`).
- Pick the date list: ongoing → `upcoming_mondays(5, today)`; otherwise the
  configured dates.
- Pick `max_lead_days`: single → 21; otherwise null.
- For `[fu_countdown]` and `[fu_show_phase]`: if mode is ongoing, return `''`.

`FU_Programs`:

- **`sanitize()`** gains an `evergreen` boolean (derived from the Schedule text
  field: trimmed, lower-cased value equals `ongoing`). Dates remain optional
  when evergreen is on.

Admin view (`views/admin-settings.php`):

- Add a **"Schedule" text input** above the date rows with the help text and a
  note that evergreen ignores the date pickers. Pre-fills with `ongoing` when
  the saved program is evergreen.

Frontend JS (`assets/js/fu-refresh.js`) mirrors the PHP for the midnight
refresh: mode resolution, `upcomingMondays()`, the 21-day gate, and
countdown/phase suppression for ongoing. The inlined `fuRefreshData` per
program gains an `evergreen` boolean.

## Data flow

```
Network Admin form (Schedule text + dates)
        │  sanitize(): evergreen = (lower(trim(schedule)) === 'ongoing')
        ▼
fu_programs (network option): { …, dates[], evergreen }
        │
        ▼  (page request, per program)
FU_Shortcodes::resolve()
   mode = FU_Renderer::resolve_mode(dates, evergreen)
   dates = (mode==ongoing) ? upcoming_mondays(5, today) : dates
   maxLead = (mode==single) ? 21 : null
   slots = compute_slots(dates, today, maxLead)
   countdown/phase shortcodes: '' when mode==ongoing
        ▼
PHP-rendered HTML  ──►  fu-refresh.js re-runs the same at midnight
                        (reads evergreen flag from fuRefreshData)
```

## Error handling

- Evergreen on with no dates → valid (dates ignored; generated from today).
- Single mode, date > 21 days out → renders nothing (by design), no error.
- 0 dates and not evergreen → renders nothing (existing behavior).
- Malformed dates still rejected at the storage boundary by
  `FU_Programs::sanitize()` (unchanged regex).

## Testing

`FU_Renderer` is WP-free; cover the new logic with unit tests:

- **`resolve_mode`**: 0/1/2/3/4 dates and the evergreen flag → expected mode.
- **`upcoming_mondays`**: from a known `today`, returns the right Monday
  sequence; verify the Mon→Tue→Wed rollover and the Sunday edge (next Monday is
  T=1 / SOLD-OUT) match the existing curve.
- **single 21-day gate** via `compute_slots(..., 21)`: T=22 → `[]`; T=21 →
  shows and counts down through SOLD OUT and the high-price card.
- **ongoing**: across several reference days (incl. a Wednesday rollover),
  always exactly 2 date slots, never a high-price slot.
- **regression**: the existing 4-date `slotProvider`, phase, and countdown
  tests must stay green (the `max_lead_days` default keeps `compute_slots`
  behavior identical).
- **`FU_Programs::sanitize` evergreen** flag derivation.

Manual (documented for the user): set a single date 25 days out (nothing
shows) then ≤21 (appears); set `ongoing` and confirm two rolling Mondays with
no end and no countdown/phase banner.

## Deliverables

- Renderer: `resolve_mode()`, `upcoming_mondays()`, `compute_slots` gate param.
- Shortcodes: mode-aware date resolution + ongoing suppression of
  countdown/phase.
- `FU_Programs::sanitize()` + `evergreen` storage.
- Admin view: Schedule text field.
- `fu-refresh.js`: mirror all of the above; add `evergreen` to inlined data.
- Tests for every mode; existing suite stays green.
- **Designer docs updated** — both `wp-plugin/DESIGNER-MANUAL.md` and
  `wp-plugin/DESIGNER-INSTRUCTIONS.md`: explain the modes, how to enter dates
  for each (1/2/3/4+), and how to type `ongoing` for evergreen; note that
  countdown/phase banners don't apply in ongoing mode.
- `CLAUDE.md` status + version history → 2.1.0; spec table updated.
- **Repository cleanup before push**: remove any build cruft / temporary or
  now-orphaned files; ensure `git status` is clean and the committed tree
  contains only intended files. Keep `.claude/` gitignored.
- Rebuilt `wp-plugin/dwc-spots-left.zip` (v2.1.0, no dev deps).
- Version bump in both spots of `fitness-urgency.php`.

## Rollout notes

- Additive and backward compatible: existing 4-date programs keep working
  unchanged (mode = fixed; `max_lead_days` default preserves behavior).
- Page-cache caveat still applies (≤24 h or exclude landing pages). Especially
  relevant for ongoing/single, whose visible content changes daily.
