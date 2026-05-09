# Fitness Program Urgency — Designer Setup Guide

Automatic "spots left" countdown for the funnel. Shows the next 1–2 Monday
start dates with a spot count that shrinks as each date approaches; after
the final date passes, switches to a "Start next Monday — 2 spots left"
high-price message.

You don't edit the script. You only:

1. Load the script once on the WP site (one-time).
2. Configure the four program start dates (per cycle).
3. Place the urgency content where you want it on the page (per page).

---

## Cheat Sheet

**Configure dates** — somewhere on the page (or globally in the loader):

```html
<div data-fu-dates="2026-06-08,2026-06-15,2026-06-22,2026-06-29" hidden></div>
```

**Named variables** — drop these inline anywhere in the funnel:

| Markup | Renders as |
|---|---|
| `<span data-fu-var="startdate1"></span>` | `Monday, June 8` (or `Start next Monday`) |
| `<span data-fu-var="startdate2"></span>` | `Monday, June 15` (or `Start next Monday`) |
| `<span data-fu-var="spotsleft1"></span>` | `9 spots left` / `1 spot left` / `SOLD OUT` / `2 spots left` |
| `<span data-fu-var="spotsleft2"></span>` | same |

**Hide a whole sentence** when there's no second date to show that day:

```html
<span data-fu-show-if-slot="2">
  Or join us <span data-fu-var="startdate2"></span>
  (<span data-fu-var="spotsleft2"></span>).
</span>
```

**Card-style block** — for the main "pick your start date" section, see
[`elementor-snippet.html`](./elementor-snippet.html).

**State classes** the script auto-applies for styling hooks:

- `.fu-sold-out` — added to a slot or variable when its date is sold out
- `.fu-high-price` — added when it's showing the post-cycle high-price message

---

## Files in this repo

- **`fitness-urgency.js`** — the script. You don't edit this; you load it.
- **`elementor-snippet.html`** — copy-paste card block for an Elementor HTML widget.
- **`demo.html`** — open in a browser to preview every day in the cycle.

---

## Step 1 — Load the script (once per WP site)

The script is hosted on GitHub and served via the **jsDelivr** public CDN.
Load it once with **WPCode** (or whatever code-injection plugin is
installed).

1. WP admin → **WPCode → Add Snippet → Add Your Custom Code**.
2. Snippet type: **HTML Snippet**.
3. Title: `Fitness Urgency — Loader`.
4. Paste this exact code:

   ```html
   <script
     src="https://cdn.jsdelivr.net/gh/jk33k/code@646af423ea923d69f3026f44cb8bb0cc098aa2db/fitness-urgency.js"
     integrity="sha384-xiA844lCqe5CpFfDT5v1HT6BtAFCoBYWD/3YMiB2QzLqdBkwZlV7gQZo0Wth9bIH"
     crossorigin="anonymous"
     defer></script>
   ```

5. **Insert Method:** Auto Insert.
6. **Location:** Site Wide Footer (safe — the script does nothing on
   pages without urgency markup).
7. Toggle **Active** and save.

> The URL is pinned to a specific commit, and `integrity="sha384-…"`
> tells the browser to refuse to run the file if its bytes have been
> tampered with. The URL is effectively immutable.

---

## Step 2 — Configure the program dates

Add this single line wherever the urgency content will appear. Format:
comma-separated `YYYY-MM-DD`, four Monday start dates.

```html
<div data-fu-dates="2026-06-08,2026-06-15,2026-06-22,2026-06-29" hidden></div>
```

**Two ways to do this:**

- **Per page** (recommended if different funnels have different cycles):
  paste it into a small Elementor HTML widget at the top of each page.
- **Globally** (recommended if the same dates apply across the whole site):
  paste it inside the WPCode loader snippet from Step 1, right above the
  `<script>` line. Then you only update one place per cycle.

---

## Step 3 — Place urgency content on the page

Three placement patterns. Mix and match freely.

### A. Inline named variables — most flexible

Drop the four variables anywhere on any page in the funnel — headlines,
sub-copy, banners, sticky bars, CTAs, follow-up pages.

```html
Doors close soon — next group starts
<span data-fu-var="startdate1"></span>,
only <span data-fu-var="spotsleft1"></span>!
```

When a slot has no data (e.g. the second date isn't applicable that day),
the matching `<span>` is hidden via `display:none`. To stop orphaned
copy ("or join us " followed by nothing), wrap the whole sentence:

```html
<span data-fu-show-if-slot="2">
  Or grab a spot for <span data-fu-var="startdate2"></span>
  (<span data-fu-var="spotsleft2"></span>).
</span>
```

**In Elementor:** use a **Text Editor** widget (Text tab, not Visual)
or a small **HTML** widget — both let you paste the markup with the
surrounding copy intact.

### B. Card-style block — for the main "pick your date" section

Drag in an **Elementor HTML widget**, paste the entire contents of
**`elementor-snippet.html`**, and you're done. It handles the layout,
the sold-out state, and the high-price state, all styled out of the box.

### C. Mix of both

The card block can live in the main hero while named variables sprinkle
the same data through testimonials, CTAs, and the checkout. Both pull
from the same dates.

---

## Updating each cycle

Edit only the dates string. Save. Done.

```html
<div data-fu-dates="2026-06-08,2026-06-15,2026-06-22,2026-06-29" hidden></div>
```

If you put this in the WPCode loader (the global option in Step 2),
update it there once. If you put it per-page, update it on each page
that uses urgency content.

---

## How the numbers behave

For each start date, spots-left follows this fixed pattern based on `T`
(days until that start date):

| T | Display |
|---|---|
| ≥14 | 9 spots left |
| 12–13 | 8 spots left |
| 10–11 | 7 spots left |
| 8–9 | 6 spots left |
| 7 | 5 spots left |
| 6 | 4 spots left |
| 5 | 3 spots left |
| 4 | 2 spots left |
| 2–3 | 1 spot left |
| 1, 0, −1 | SOLD OUT |

**Slot transitions:**

- The page shows the next two upcoming Monday start dates.
- A passed Monday stays visible (as SOLD OUT) through the Tuesday after,
  then on Wednesday the next pair rolls into view.
- Between the second-to-last and last cycles, only one date is shown
  (slot 2 hidden).
- After the final start date sells out (Sun–Tue around it), slot 2
  becomes the high-price "Start next Monday" message.
- After the final cycle's window passes, slot 1 becomes the high-price
  message and slot 2 stays hidden.

The page **auto-refreshes at local midnight** so visitors who sit on the
page overnight see the correct numbers the next morning.

---

## Styling

State hooks the script toggles for you:

```css
/* Inline variables */
[data-fu-var].fu-sold-out  { color: #888; letter-spacing: 0.5px; }
[data-fu-var].fu-high-price { color: #b26a00; font-weight: 700; }

/* Card-style slots — the wrapping element gets these classes */
[data-fu-slot].fu-sold-out  { opacity: 0.55; }
[data-fu-slot].fu-high-price { background: #fff8e1; }
```

The card snippet ships with sensible default styling in its own `<style>`
block — edit that to match the brand, or strip it and use Elementor's
own widget styling instead. The script only needs to find:

- One element with `data-fu-dates="…"` (anywhere on the page)
- For card-style: one element with `data-fu-slot="1"` and one with
  `data-fu-slot="2"`, each containing children with `data-fu-date` and
  `data-fu-spots`

In Elementor, custom HTML attributes can be added to any
Section / Column / Widget via **Advanced → Attributes** (or "HTML
attributes" depending on Elementor version).

---

## Previewing locally

The repo includes **`demo.html`**. Download it and `fitness-urgency.js`
into the same folder on your computer, open `demo.html` in a browser.
You'll see both placement patterns rendered live, plus a date-range
walker that shows what visitors will see on each day from May 31 through
July 2.

---

## Updating the script (rare)

The loader URL is pinned to a specific commit on purpose, so a bad push
can't silently break every site. To pick up an updated script, replace
the loader snippet in WPCode with the new URL + integrity hash that the
repo owner provides — two strings change, nothing else.

---

## Troubleshooting

- **Variables / cards show empty.** The script didn't load or didn't
  find the markup.
  - Open browser DevTools → Console; look for errors.
  - Confirm the WPCode loader snippet is **Active**.
  - Confirm the page has a `data-fu-dates="…"` line (or that it's in the
    global loader).
- **Dates are wrong.** The date string must be `YYYY-MM-DD`,
  comma-separated, no spaces.
- **Console shows an integrity error.** The integrity hash doesn't match.
  Re-copy the loader snippet from Step 1 *exactly*, including the full
  `integrity="sha384-…"` value.
- **Old data after a new push.** Browser cache. Hard-reload with
  Cmd/Ctrl + Shift + R.
- **A `data-fu-show-if-slot="2"` block stays visible on a single-date
  day.** Check the attribute spelling and quotes — typos silently fall
  back to "always show".
