# Fitness Program Urgency — Designer Setup Guide

This adds an automatic "spots left" countdown to a landing page. The script
shows the next 1–2 Monday start dates and shrinks the spot count as each
date approaches. After the final date passes, it switches to a "Start next
Monday — 2 spots left" message that flags the price bump.

You don't need to understand or edit the script itself. You only:
1. Load the script once on the WP site (one-time setup).
2. Paste an HTML block into the funnel landing page.
3. Update one line each new program cycle.

---

## Files in this repo

- **`fitness-urgency.js`** — the script. You don't edit this; you just load it.
- **`elementor-snippet.html`** — copy-paste HTML for the Elementor HTML widget.
- **`demo.html`** — open in a browser to preview every day in the cycle.

---

## Part 1 — One-time WordPress setup (per WP install)

The script is hosted on GitHub and served via the **jsDelivr** public CDN.
You will load it once site-wide (or scoped to the funnel page) using
**WPCode** (or whatever code-injection plugin is already installed).

### Steps

1. In WP admin, open **WPCode → Add Snippet → Add Your Custom Code**.
2. Snippet type: **HTML Snippet**.
3. Title: `Fitness Urgency — Loader`.
4. Paste this exact code into the snippet:

   ```html
   <script
     src="https://cdn.jsdelivr.net/gh/jk33k/code@345f8d5698d896658ab2777f03f8e0c9ba9ab54e/fitness-urgency.js"
     integrity="sha384-79nVu9YPORBi2BdvyhN1m7R/xoICAhH+l58l509eEeyjYdMjbSjN/Lz0tsVRdLt8"
     crossorigin="anonymous"
     defer></script>
   ```

5. **Insert Method:** Auto Insert.
6. **Location:** Site Wide Footer.
   *(If you prefer, scope it to just the funnel landing page using WPCode's
   "Page-Specific" rules — the script does nothing on pages without the
   markup, so site-wide is safe too.)*
7. Toggle the snippet **Active** and save.

> **Why the long URL?** It's pinned to a specific commit of the GitHub
> repo, and the `integrity="sha384-…"` line tells the browser to refuse to
> run the file if its bytes have been tampered with. The URL is effectively
> immutable.

---

## Part 2 — Add the urgency block to the landing page

1. Edit the funnel landing page in **Elementor**.
2. Drag in an **HTML** widget where the urgency block should appear.
3. Open `elementor-snippet.html` from this repo and copy its **entire
   contents**.
4. Paste the contents into the Elementor HTML widget.
5. **Edit the dates line** to match the current program cycle:

   ```html
   <div data-fu-dates="2026-06-08,2026-06-15,2026-06-22,2026-06-29" hidden></div>
   ```

   Format: comma-separated `YYYY-MM-DD`, four Monday start dates.

6. Update the page. Reload the live page in an incognito window — you
   should see two cards with today's correct dates and spot counts.

---

## Part 3 — Updating for a new program cycle

Each cycle, edit only the dates line in the Elementor HTML widget:

```html
<div data-fu-dates="2026-06-08,2026-06-15,2026-06-22,2026-06-29" hidden></div>
```

Save, done. The script handles everything else automatically.

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
- Between the second-to-last and last cycles, only one card is shown
  (the second card is hidden via `display: none`).
- After the final start date sells out, a high-price card appears with
  "Start next Monday — 2 spots left" and gets a yellow highlight
  (`fu-high-price` class).

**The page auto-refreshes the display at local midnight** so visitors who
sit on the page overnight see the correct numbers the next morning.

---

## Customizing the look

The snippet ships with sensible default styling. You have two paths to
match the brand:

### Option A — edit the inline CSS in the snippet

Inside the Elementor HTML widget, the `<style>` block at the top defines
all the styling:

- `.fitness-urgency` — outer wrapper (max-width, gap)
- `.fitness-urgency .fu-card` — each card (border, padding, background)
- `.fitness-urgency .fu-card-date` — date text
- `.fitness-urgency .fu-card-spots` — spots text (red)
- `.fitness-urgency .fu-card.fu-sold-out` — sold-out state (greyed out)
- `.fitness-urgency .fu-card.fu-high-price` — high-price state (yellow)

Edit any of these to match the brand. You don't need to touch the HTML.

### Option B — strip the inline CSS and use Elementor styling

You can delete the entire `<style>…</style>` block at the top of the
snippet and replace the simple `<div class="fu-card">` markup with
Elementor's own widgets. The script only needs to find:

- One element with `data-fu-dates="…"` (anywhere on the page)
- One element with `data-fu-slot="1"` containing children with
  `data-fu-date` and `data-fu-spots`
- One element with `data-fu-slot="2"` containing children with
  `data-fu-date` and `data-fu-spots`

In Elementor, you can add custom HTML attributes to a Section / Column /
Widget via **Advanced → Attributes** (or the "HTML attributes" feature
depending on Elementor version). The script will find your widgets and
write text into them, while leaving all your styling alone.

The script also toggles two CSS classes on the slot element — style these
in your theme CSS if you go this route:

- `.fu-sold-out` — added when that date is sold out
- `.fu-high-price` — added when this slot is the post-cycle high-price message

---

## Previewing without waiting for the real dates

The repo includes **`demo.html`**. Download it plus `fitness-urgency.js`
into the same folder on your computer and open `demo.html` in a browser.
The bottom section walks every day from May 31 → July 2 so you can see
exactly what visitors will see on each day.

---

## When the script is updated

If the script ever gets updated (bug fix, new feature), the URL **will not
change automatically** — that's deliberate, so a bad push can't silently
break every site.

To pick up an update, replace the loader snippet in WPCode with the new
URL + integrity hash that the repo owner provides. Two strings change;
nothing else.

---

## Troubleshooting

- **Cards show empty / dashes only.** The script didn't load or didn't
  find the markup. In the browser, open DevTools → Console and look for
  errors. Common causes: `<script>` tag missing on the page, or the
  `data-fu-dates` attribute is malformed.
- **Cards show but dates are wrong.** Check the dates string — it must be
  `YYYY-MM-DD`, comma-separated, no spaces.
- **Browser refuses to run the script (integrity error in console).**
  The integrity hash doesn't match the file. Make sure you copied the
  loader snippet exactly, including the full `integrity="sha384-…"` value.
- **Cards show but old data after a new push.** Browser cache. Hard-reload
  with Cmd/Ctrl + Shift + R. CDN cache should not be an issue because the
  URL is pinned to a specific commit.
