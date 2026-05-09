# DWC – Spots Left — Designer Guide

Automatic "spots left" countdown for DWC program landing pages. The
plugin shows the next 1–2 Monday start dates with a spot count that
shrinks as each date approaches. After the final date passes it switches
to a "Start next Monday — 2 spots left" high-price message.

**No code editing required.** You manage program dates in the WP admin,
then drop shortcodes wherever you want the urgency content to appear.

---

## Cheat Sheet

```
[fu_card]                          ← full 2-card block (easiest)

[fu_startdate slot="1"]            ← first start date text
[fu_startdate slot="2"]            ← second start date text
[fu_spotsleft slot="1"]            ← first spots-left text
[fu_spotsleft slot="2"]            ← second spots-left text
[fu_finaldate]                     ← last program date, e.g. "June 29th"

[fu_show_if_slot slot="2"]
  …content only shown when a second date exists…
[/fu_show_if_slot]

[fu_show_phase phase="high_demand"]
  …content only shown during the high-demand window…
[/fu_show_phase]

[fu_show_phase phase="final_week"]
  …content only shown during the final-week window…
  [fu_countdown]                   ← live D/H/M/S countdown
[/fu_show_phase]
```

Add `program="your-slug"` to any shortcode to target a specific program.
Omit it and the default program is used.

**Date format.** Dates render with ordinal suffixes — `"Monday, June 8th"`
in card/inline contexts, `"June 29th"` from `[fu_finaldate]`.

---

## Step 1 — Install the plugin (once per WP site)

1. Download **`dwc-spots-left.zip`** from this repo.
2. WP Admin → **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP file → **Install Now → Activate**.

The plugin is now active. You will see **DWC - Spots Left** under the
Settings menu.

---

## Step 2 — Set up your programs

Each "program" is a named set of four Monday start dates.

### Add a program

1. WP Admin → **Settings → DWC - Spots Left**.
2. Fill in the **Add Program** form on the right:

   | Field | What to enter |
   |---|---|
   | **Program name** | Anything you like — shown in the admin list only. E.g. `Summer Strength Cycle` |
   | **Slug** | Short identifier used in shortcodes. Lowercase, no spaces. E.g. `summer-strength`. Cannot be changed after saving. |
   | **Start dates** | The four Monday dates for the cycle. Use the date pickers. |
   | **High-price label** | Text shown as the date after the final Monday sells out. Default: `Start next Monday` |
   | **High-price spots** | Number shown with the high-price label. Default: `2` |
   | **Default program** | Tick this if you want shortcodes without a `program=""` attribute to use this program. |

3. Click **Add program**. It appears in the list on the left.

### Edit or delete a program

- Click **Edit** next to a program to load it into the form. Make changes
  and click **Save changes**.
- Click **Delete** to remove it (a confirmation dialog appears first).

### Updating for a new cycle

Edit the program, replace the four dates with the new Monday start dates,
save. Every shortcode on every page using that program updates immediately.

---

## Step 3 — Place urgency content on the page

You have two approaches. Mix them freely on the same page or across
different pages in the funnel.

---

### Approach A — Card block `[fu_card]`

Best for the main "pick your start date" section. Renders two styled
cards side by side (or stacked). The second card hides automatically
on days when only one date is relevant, and switches to a yellow
high-price card when the final date sells out.

**In Elementor:**

1. Drag a **Shortcode** widget (or an **HTML** widget) into your layout.
2. Type or paste:

   ```
   [fu_card]
   ```

3. To target a specific program:

   ```
   [fu_card program="summer-strength"]
   ```

The plugin loads the required CSS automatically — no extra steps.

---

### Approach B — Inline shortcodes (use anywhere)

Drop `[fu_startdate]` and `[fu_spotsleft]` inside any text, heading,
button label, or HTML block. Use them in headlines, sub-copy, sticky
bars, checkout pages, thank-you pages — anywhere in the funnel.

**Examples:**

```
Doors close soon — next group starts [fu_startdate slot="1"],
only [fu_spotsleft slot="1"]!
```

```
Join us [fu_startdate slot="1"] ([fu_spotsleft slot="1"]).
```

Each shortcode renders as a `<span>` with the right text already filled
in. When a slot has no data (e.g. only one date is being shown that day),
the shortcode outputs nothing and takes up no space.

**Targeting a specific program:**

```
[fu_startdate slot="1" program="summer-strength"]
[fu_spotsleft slot="1" program="summer-strength"]
```

---

### Hiding a whole sentence when slot 2 has no data

If you write surrounding copy for the second date, wrap it in
`[fu_show_if_slot]` so the whole sentence disappears on single-date days:

```
[fu_show_if_slot slot="2"]
  Or join the following week: [fu_startdate slot="2"]
  ([fu_spotsleft slot="2"]).
[/fu_show_if_slot]
```

Without the wrapper, orphaned text like "Or join the following week:"
would remain visible even when the shortcodes inside produce nothing.

---

### Approach C — Phase banners and live countdown

Two extra shortcodes let you place messaging at the **top of the funnel**
that automatically appears and disappears as the cycle progresses — no edits
needed once the dates are saved.

| Shortcode | Visible window |
|---|---|
| `[fu_show_phase phase="high_demand"]…[/fu_show_phase]` | Wed after the first Monday rolls over → Saturday before the final week starts |
| `[fu_show_phase phase="final_week"]…[/fu_show_phase]` | Sunday 8 days before the final Monday → Saturday before the final Monday |
| `[fu_countdown]` | Live D/H/M/S countdown to midnight before the final Monday |
| `[fu_finaldate]` | Last program date, formatted as "June 29th" |

**Worked example** — for program dates `Jun 8 / 15 / 22 / 29`:

| Date | What's visible at the top |
|---|---|
| Wed Jun 10 → Sat Jun 20 | `high_demand` banner |
| Sun Jun 21 → Sat Jun 27 | `final_week` banner with live countdown |
| Sun Jun 28 onwards | (nothing extra above the cards) |

**Drop-in template** for the top of the landing page (write once, never edit):

```
[fu_show_phase phase="high_demand"]
  <h2 class="banner">New start date added due to high demand</h2>
[/fu_show_phase]

[fu_show_phase phase="final_week"]
  <h2 class="banner">[fu_finaldate] is the final start before the price increases.</h2>
  [fu_countdown]
[/fu_show_phase]
```

**Why two wrappers instead of one?** Each phase has its own copy and visibility
window. If neither phase is active (e.g., the day after the cycle ends, or
before it starts), nothing shows above the cards.

#### Styling the countdown

`[fu_countdown]` renders this structure (whitespace added for clarity):

```html
<span class="fu-countdown" data-fu-countdown="1751083200" data-fu-program="…">
  <span class="fu-cd-days">06</span><span class="fu-cd-label">d </span>
  <span class="fu-cd-hours">23</span><span class="fu-cd-label">h </span>
  <span class="fu-cd-minutes">59</span><span class="fu-cd-label">m </span>
  <span class="fu-cd-seconds">59</span><span class="fu-cd-label">s</span>
</span>
```

Every part is independently stylable — no plugin CSS is loaded for the
countdown by default, so you start from a blank slate:

```css
.fu-countdown { font-family: monospace; font-size: 2rem; }
.fu-cd-days,
.fu-cd-hours,
.fu-cd-minutes,
.fu-cd-seconds {
  background: #111; color: #fff;
  padding: 4px 8px; border-radius: 4px; min-width: 2ch;
  display: inline-block; text-align: center;
}
.fu-cd-label { color: #888; margin: 0 6px; }

/* Hide the d/h/m/s labels and add your own with ::after if you prefer */
.fu-cd-label { display: none; }
.fu-cd-days::after    { content: " days "; }
.fu-cd-hours::after   { content: " hrs ";  }
.fu-cd-minutes::after { content: " min ";  }
.fu-cd-seconds::after { content: " sec";   }
```

The numbers update every second via JavaScript. The PHP-rendered initial
values are correct on first paint (no flash), so the countdown looks live
even before JS loads.

#### Targeting a specific program

```
[fu_show_phase phase="final_week" program="summer-strength"]…[/fu_show_phase]
[fu_countdown program="summer-strength"]
[fu_finaldate program="summer-strength"]
```

---

## How the numbers work

For each start date, the spots-left count follows this fixed schedule
based on `T` — the number of days until that start date:

| Days until start (T) | Display |
|---|---|
| 14 or more | 9 spots left |
| 12–13 | 8 spots left |
| 10–11 | 7 spots left |
| 8–9 | 6 spots left |
| 7 | 5 spots left |
| 6 | 4 spots left |
| 5 | 3 spots left |
| 4 | 2 spots left |
| 2–3 | 1 spot left |
| 1, 0, or −1 | SOLD OUT |

### Slot transitions

- **Two slots are shown** most of the time: the next upcoming Monday and
  the one after it.
- A Monday that has passed stays visible as **SOLD OUT** through the
  Tuesday after it, then disappears on Wednesday and the next pair
  rolls in.
- **Single-date days** occur Wed–Sat before the final Monday: only slot 1
  is shown. Slot 2 cards and `[fu_show_if_slot slot="2"]` blocks hide
  automatically.
- When the **final Monday sells out** (Sun–Tue around it), slot 2 becomes
  the high-price "Start next Monday" message with a yellow highlight.
- After the **final cycle's window fully passes** (Wed after the last
  Tuesday), slot 1 itself shows the high-price message and slot 2 stays
  hidden.

### Phase transitions

`[fu_show_phase]` toggles the top-of-page banner using two date-driven
phases. Let `D1` = first start date and `Dlast` = last start date:

| Phase | Visible from | Visible until |
|---|---|---|
| `high_demand` | `D1 + 2` (Wed after first Monday) | `Dlast − 9` (Sat before final week) |
| `final_week`  | `Dlast − 8` (Sun before final week) | `Dlast − 2` (Sat before final Monday) |

Outside both windows nothing renders. The live `[fu_countdown]` ticks down
to **midnight site-local on `Dlast − 1`** — i.e., it hits zero exactly when
the `final_week` banner disappears.

### Midnight rollover

The plugin renders the correct values on the server (using WP's own
timezone setting), so visitors see the right numbers the instant the page
loads — no flash of empty content.

A small JavaScript file then watches for the site's local midnight and
re-renders without a page reload, so visitors who keep the page open
overnight see fresh numbers automatically.

---

## Styling

### Card block (Approach A)

The plugin ships with default card styles scoped to `.fu-urgency`. All
rules are easy to override in your theme's CSS.

**Selector map:**

| Selector | What it targets |
|---|---|
| `.fu-urgency` | Outer wrapper (grid layout) |
| `.fu-urgency .fu-card` | Each individual card |
| `.fu-urgency .fu-card-date` | The date text inside a card |
| `.fu-urgency .fu-card-spots` | The spots text inside a card (red) |
| `.fu-urgency .fu-card.fu-sold-out` | Card state when the date is sold out (greyed) |
| `.fu-urgency .fu-card.fu-high-price` | Card state for the post-cycle high-price message (yellow) |

**Example override in Elementor (Custom CSS on the section):**

```css
.fu-urgency .fu-card {
  border-radius: 8px;
  border-color: #your-brand-color;
}
.fu-urgency .fu-card-spots {
  color: #your-brand-color;
}
```

### Inline shortcodes (Approach B)

Each `[fu_startdate]` and `[fu_spotsleft]` renders as a `<span>` with
these classes:

| Class always present | Meaning |
|---|---|
| `.fu-var` | On every variable span |
| `.fu-startdate` | On date spans only |
| `.fu-spotsleft` | On spots spans only |

| State class (added automatically) | When applied |
|---|---|
| `.fu-sold-out` | The date is sold out |
| `.fu-high-price` | The high-price message is showing |

**Example:**

```css
.fu-var.fu-sold-out  { color: #888; letter-spacing: 0.5px; }
.fu-var.fu-high-price { color: #b26a00; font-weight: 700; }
```

You can also pass a custom class via the `class=""` attribute:

```
[fu_spotsleft slot="1" class="my-red-text"]
```

### Using Elementor's own widgets instead of the card CSS

If you prefer to build the card layout natively in Elementor:

1. Build your two date sections using Elementor widgets (columns,
   headings, text, etc.).
2. Add the shortcodes inline inside Text Editor widgets (use the **Text**
   tab, not Visual):

   ```
   [fu_startdate slot="1"] — [fu_spotsleft slot="1"]
   ```

3. To show/hide the second section automatically, wrap the Elementor
   widget or column content with `[fu_show_if_slot slot="2"]…[/fu_show_if_slot]`
   inside an HTML widget. Note: Elementor's column-level show/hide
   requires an HTML/Shortcode widget wrapper.

---

## Troubleshooting

**Shortcode shows as raw text like `[fu_card]`.**
The plugin is not active. Go to WP Admin → Plugins and confirm
DWC - Spots Left is active.

**Shortcode outputs nothing (blank space).**
The program has no dates saved. Go to Settings → DWC - Spots Left,
edit the program, and add the four Monday start dates.

**Dates are showing but they look wrong.**
Check that WP's timezone is set correctly: WP Admin → Settings → General
→ Timezone. The plugin uses this timezone for all date arithmetic.

**The card renders but the spots count didn't update after midnight.**
Reload the page. If it still shows yesterday's numbers, check the browser
console for JS errors. The script needs to load — confirm the page
contains a `[fu_card]`, `[fu_startdate]`, `[fu_spotsleft]`,
`[fu_show_if_slot]`, `[fu_show_phase]`, `[fu_countdown]`, or
`[fu_finaldate]` shortcode so the plugin knows to enqueue the script.

**The countdown shows the same numbers and isn't ticking.**
Check the browser console for JS errors. The countdown updates every second
via JavaScript; if the script failed to load, the PHP-rendered initial values
will appear frozen.

**The phase banner is showing on the wrong day.**
Almost always a caching issue — the page was rendered yesterday (when the
phase was active) and is being served from cache. Either exclude the page
from caching or set a cache lifetime ≤ 24 h.

**Slot 2 card is visible on a day when it should be hidden.**
This can happen if the page is cached (e.g. by a caching plugin like
WP Rocket or W3 Total Cache). Configure the cache to either:
- Exclude the landing page from caching, or
- Set a cache lifetime of 24 hours or less so it refreshes daily.

**The high-price card doesn't appear after the last date sells out.**
Same cache issue as above. The server-rendered HTML is cached with
the wrong date. Purge the cache or shorten the cache lifetime.

**I need the same urgency block on multiple pages with different programs.**
Use the `program="slug"` attribute on every shortcode and card. Each
shortcode can point to a different program independently.

---

## Full shortcode reference

| Shortcode | Required attrs | Optional attrs | Output |
|---|---|---|---|
| `[fu_card]` | — | `program`, `class` | Full 2-card block |
| `[fu_startdate]` | `slot` | `program`, `class` | Date text span (`Monday, June 8th`) |
| `[fu_spotsleft]` | `slot` | `program`, `class` | Spots-left text span |
| `[fu_finaldate]` | — | `program`, `class` | Last program date (`June 29th`) |
| `[fu_show_if_slot]…[/fu_show_if_slot]` | `slot` | `program` | Conditional wrapper (slot has data) |
| `[fu_show_phase]…[/fu_show_phase]` | `phase` | `program` | Conditional wrapper (phase active) |
| `[fu_countdown]` | — | `program`, `class` | Live D/H/M/S countdown |

**`slot`** — `"1"` or `"2"`. Slot 1 is the next upcoming Monday; slot 2
is the one after it.

**`phase`** — `"high_demand"` or `"final_week"`. See *Phase transitions*
above for the exact day-by-day windows.

**`program`** — the slug of the program configured in Settings → Fitness
Urgency. Omit to use the default program.

**`class`** — extra CSS class(es) added to the rendered element. Useful
for targeted styling without touching the plugin's own CSS.
