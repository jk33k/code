# Fitness Program Urgency — Designer Setup Guide

This adds an automatic "spots left" countdown to the funnel. The script
shows the next 1–2 Monday start dates and shrinks the spot count as each
date approaches. After the final date passes, it switches to a "Start next
Monday — 2 spots left" message that flags the price bump.

You don't need to understand or edit the script itself. You only:
1. Load the script once on the WP site (one-time setup).
2. Either drop a ready-made block onto a page, or sprinkle named variables
   anywhere you want them to appear in the funnel.
3. Update one line each new program cycle.

---

## Files in this repo

- **`fitness-urgency.js`** — the script. You don't edit this; you just load it.
- **`elementor-snippet.html`** — copy-paste HTML for a card-style block in
  an Elementor HTML widget.
- **`demo.html`** — open in a browser to preview every day in the cycle and
  see both placement styles in action.

---

## Part 1 — One-time WordPress setup (per WP install)

The script is hosted on GitHub and served via the **jsDelivr** public CDN.
You will load it once site-wide (or scoped to the funnel pages) using
**WPCode** (or whatever code-injection plugin is already installed).

### Steps

1. In WP admin, open **WPCode → Add Snippet → Add Your Custom Code**.
2. Snippet type: **HTML Snippet**.
3. Title: `Fitness Urgency — Loader`.
4. Paste this exact code into the snippet:

   ```html
   <script
     src="https://cdn.jsdelivr.net/gh/jk33k/code@646af423ea923d69f3026f44cb8bb0cc098aa2db/fitness-urgency.js"
     integrity="sha384-xiA844lCqe5CpFfDT5v1HT6BtAFCoBYWD/3YMiB2QzLqdBkwZlV7gQZo0Wth9bIH"
     crossorigin="anonymous"
     defer></script>
   ```

5. **Insert Method:** Auto Insert.
6. **Location:** Site Wide Footer.
   *(If you prefer, scope it to just the funnel pages using WPCode's
   "Page-Specific" rules — the script does nothing on pages without the
   markup, so site-wide is safe too.)*
7. Toggle the snippet **Active** and save.

> **Why the long URL?** It's pinned to a specific commit of the GitHub
> repo, and the `integrity="sha384-…"` line tells the browser to refuse to
> run the file if its bytes have been tampered with. The URL is effectively
> immutable.

---

## Part 2 — Per-page setup

You first need to tell each page **what the program's start dates are**.
Then you choose how to display them: a ready-made card block, named
variables sprinkled inline, or both.

### 2a. Set the program dates (do this on every page that uses the script)

Add this line **once** to each page that displays urgency content. Easiest
spot: an Elementor HTML widget at the top of the page. The `hidden`
attribute keeps it invisible.

```html
<div data-fu-dates="2026-06-08,2026-06-15,2026-06-22,2026-06-29" hidden></div>
```

Format: comma-separated `YYYY-MM-DD`, four Monday start dates.

### 2b. (Option 1) Drop in the ready-made card block

Best for the main "pick your start date" section. It's two stacked cards
that auto-update.

1. Drag in an **Elementor HTML widget** where the cards should appear.
2. Open `elementor-snippet.html` from this repo and copy its **entire
   contents**.
3. Paste into the HTML widget. Customize the inline `<style>` block to
   match the brand (or strip it and use your own CSS).

### 2c. (Option 2) Use named variables anywhere on the page

For headlines, sub-copy, banners, CTAs, sticky bars — anywhere you want
to mention the next start date or spots left. Works on any page in the
funnel (sales page, checkout, thank-you, follow-up emails embedded as
web views, etc.) — as long as the page also has the dates line from 2a
and loads the script from Part 1.

There are **four named variables**:

| Variable | What it shows |
|---|---|
| `startdate1` | First date — `Monday, June 8` (or `Start next Monday` after the program) |
| `startdate2` | Second date — `Monday, June 15` (or `Start next Monday`) |
| `spotsleft1` | First spots-left — `9 spots left` / `1 spot left` / `SOLD OUT` / `2 spots left` |
| `spotsleft2` | Second spots-left — same range |

**Use them like this:**

```html
Doors close soon — next group starts
<span data-fu-var="startdate1"></span>,
only <span data-fu-var="spotsleft1"></span>.
```

The script writes the right text into each `<span>`. When a slot has no
data (e.g. there's only one date to show that day), the `<span>` is
automatically hidden via `display:none`.

**Hide a whole sentence when slot 2 has no data:**

If you wrap an entire sentence in `[data-fu-show-if-slot="2"]`, that
wrapper is hidden as a unit on single-date days, so you don't end up with
orphaned copy like "or join us " followed by nothing:

```html
<span data-fu-show-if-slot="2">
  Or join the following Monday, <span data-fu-var="startdate2"></span>
  (<span data-fu-var="spotsleft2"></span>).
</span>
```

**Where to put these in Elementor:**

- In a **Heading** widget → switch to text-with-HTML mode and paste the
  `<span>` inline, or use the simpler approach: drop a small **HTML widget**
  with the full sentence.
- Inside a **Text Editor** widget → use the "Text" tab (not "Visual") and
  paste the markup.

**Styling state hooks:** when a variable is showing "SOLD OUT" the script
adds a `fu-sold-out` class to the `<span>`. When it's showing the
high-price message, it adds `fu-high-price`. Style these in your theme
CSS if you want them to look distinct (e.g. greyed out / yellow accent).

---

## Part 3 — Updating for a new program cycle

Each cycle, edit only the dates line on each page that uses urgency content:

```html
<div data-fu-dates="2026-06-08,2026-06-15,2026-06-22,2026-06-29" hidden></div>
```

Save. Done. Every card and every named variable on the page updates
automatically.

> **Tip:** if the four dates are the same across many pages in the funnel,
> you can put the `<div data-fu-dates="…" hidden></div>` line in your
> WPCode loader snippet (right next to the `<script>` tag) so it's
> available globally and you only update it in one place per WP install.

---

## What the script does, day by day

For each start date, the spots-left number follows this fixed pattern based
on `T` (days until the start date):

| T (days until) | Display |
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

**Slot behavior:**
- The page shows the next two upcoming Monday start dates.
- When a Monday passes, it stays visible (as SOLD OUT) through the
  Tuesday after — then on Wednesday it disappears and the next pair
  rolls into view.
- Between the second-to-last and last cycles, only one date is shown
  (slot 2 hidden).
- After the final start date sells out, slot 2 becomes the high-price
  "Start next Monday — 2 spots left" message with a yellow highlight
  (`fu-high-price` class).

**The page auto-refreshes the display at local midnight** so visitors who
sit on the page overnight see the correct numbers the next morning.

---

## Customizing the look

### Card-style block (Option 1)

Inside `elementor-snippet.html` the `<style>` block at the top defines all
the styling:

- `.fitness-urgency` — outer wrapper (max-width, gap)
- `.fitness-urgency .fu-card` — each card (border, padding, background)
- `.fitness-urgency .fu-card-date` — date text
- `.fitness-urgency .fu-card-spots` — spots text (red)
- `.fitness-urgency .fu-card.fu-sold-out` — sold-out state (greyed out)
- `.fitness-urgency .fu-card.fu-high-price` — high-price state (yellow)

Edit any of these to match the brand. You don't need to touch the HTML.

### Inline named variables (Option 2)

Style state-aware highlights in your theme CSS:

```css
[data-fu-var].fu-sold-out  { color: #888; letter-spacing: 0.5px; }
[data-fu-var].fu-high-price { color: #b26a00; }
```

If you'd prefer **Elementor styling** for the cards instead of editing CSS:
delete the inline `<style>` block from the snippet and rebuild the cards
using Elementor's own widgets. The script only needs to find:

- One element with `data-fu-dates="…"` (anywhere on the page)
- For card-style: one element with `data-fu-slot="1"` and one with
  `data-fu-slot="2"`, each containing children with `data-fu-date` and
  `data-fu-spots`

In Elementor, add custom HTML attributes to a Section / Column / Widget
via **Advanced → Attributes** (or the "HTML attributes" feature depending
on Elementor version).

---

## Previewing without waiting for the real dates

The repo includes **`demo.html`**. Download it plus `fitness-urgency.js`
into the same folder on your computer and open `demo.html` in a browser.
You'll see both placement styles working live, plus a date-range walker
at the bottom that shows what visitors will see on each day from May 31
through July 2.

---

## When the script is updated

The loader URL is pinned to a specific commit on purpose, so a bad push
can't silently break every site.

To pick up an update, replace the loader snippet in WPCode with the new
URL + integrity hash that the repo owner provides. Two strings change;
nothing else.

---

## Troubleshooting

- **Variables / cards show empty.** The script didn't load or didn't
  find the markup. In the browser, open DevTools → Console and look for
  errors. Common causes:
  - The loader `<script>` tag isn't on the page — check WPCode is active
    and the snippet is set to load on this page.
  - The `data-fu-dates` line is missing on the page, or comes after the
    script ran. It can go anywhere in the page body, but it must be there.
- **Cards / variables show but dates are wrong.** Check the dates
  string — must be `YYYY-MM-DD`, comma-separated, no spaces.
- **Browser console shows an integrity error.** The integrity hash
  doesn't match the file. Make sure the loader snippet is copied
  exactly, including the full `integrity="sha384-…"` value.
- **Old data after a new push.** Browser cache. Hard-reload with
  Cmd/Ctrl + Shift + R. The CDN itself doesn't cache stale because the
  URL is pinned to a specific commit.
- **A `[data-fu-show-if-slot="2"]` block stays visible on a single-date
  day.** Make sure the wrapper has the attribute exactly: `data-fu-show-if-slot="2"`
  (no quotes mismatch, no typo).
