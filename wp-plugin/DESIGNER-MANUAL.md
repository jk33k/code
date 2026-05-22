# DWC – Spots Left — Designer Manual

A friendly, get-it-done guide. If you want the deep reference (exact day-by-day
timing, the full spots math, every CSS hook), see
**[DESIGNER-INSTRUCTIONS.md](DESIGNER-INSTRUCTIONS.md)**. This manual is the
fast path.

---

## What this plugin does (30 seconds)

It shows a live "spots left" countdown for our weekly Monday-start programs.
You drop a shortcode on a page; the plugin fills in the next start date(s) and
a spot count that shrinks as the date gets closer. When a date sells out it
flips to a "Start next Monday" message automatically. **You never edit the
numbers by hand** — they update themselves every day.

```
┌─────────────────────────┐   ┌─────────────────────────┐
│  Monday, June 8th       │   │  Monday, June 15th      │
│  3 spots left           │   │  6 spots left           │
└─────────────────────────┘   └─────────────────────────┘
        (this is what [fu_card] renders, styled)
```

---

## Who sets what (important)

There are two jobs. They might both be you, or split with the account owner:

| Job | Who | Where |
|---|---|---|
| **Set the dates** (once per cycle) | A **super admin** | **Network Admin → Settings → DWC - Spots Left** |
| **Place the shortcodes** in funnels | **You, the designer** | Any page/Elementor block on any subsite |

The dates live **once** at the network level and are shared by **every
subsite**. That's the whole point: you set up a funnel once, copy it to as
many client subsites as you want, and the **exact same shortcode** shows the
**exact same dates** everywhere. Nothing to reconfigure per site.

> Subsites have **no settings screen** for this plugin. If you go looking for
> a "Spots Left" menu inside a single site, you won't find one — it's only in
> Network Admin. That's expected.

---

## Quick start (3 steps)

**1. Make sure the plugin is on.**
It should already be **Network Activated** (Network Admin → Plugins). If
shortcodes show up as raw text like `[fu_card]` on the page, it isn't active.

**2. Make sure dates exist.**
A super admin enters the 4 Monday dates in **Network Admin → Settings →
DWC - Spots Left**. If the shortcode renders *nothing* (blank), the dates
are missing.

**3. Drop a shortcode on the page.**
In Elementor, use a **Shortcode** widget (preferred) — or paste it into a
**Text** block. The easiest one:

```
[fu_card]
```

That's it. The plugin loads its own CSS automatically.

---

## The shortcodes you'll actually use

### ⭐ The big one — the card block

```
[fu_card]
```

Renders the two styled date cards above. The second card hides itself on days
when only one date matters, and turns into a yellow "Start next Monday" card
when the final date sells out. **Use this for the main "choose your start
date" section.** Done.

### Inline text bits (use inside any sentence/headline/button)

```
[fu_startdate slot="1"]      → Monday, June 8th
[fu_spotsleft slot="1"]      → 3 spots left   (or SOLD OUT)
[fu_finaldate]               → June 29th
```

`slot="1"` = the next upcoming Monday. `slot="2"` = the one after it.

Real examples you can paste into a heading or text widget:

```
Next group starts [fu_startdate slot="1"] — only [fu_spotsleft slot="1"]!
```

```
Final intake is [fu_finaldate]. Don't miss it.
```

Each renders as a tidy `<span>`. If a slot has no data that day, it outputs
**nothing** and takes up no space — so your layout never breaks.

### Hide a whole sentence when there's no 2nd date

If you write copy about the *second* date, wrap it so it vanishes on
single-date days (otherwise you'd be left with a dangling "Or join…"):

```
[fu_show_if_slot slot="2"]
  Or join the week after on [fu_startdate slot="2"] ([fu_spotsleft slot="2"]).
[/fu_show_if_slot]
```

### Top-of-page banners + live countdown (set once, runs itself)

These appear and disappear on their own as the cycle progresses. Paste this
block at the top of the landing page and never touch it again:

```
[fu_show_phase phase="high_demand"]
  <h2 class="banner">New start date added due to high demand</h2>
[/fu_show_phase]

[fu_show_phase phase="final_week"]
  <h2 class="banner">[fu_finaldate] is the final start before the price increases.</h2>
  [fu_countdown]
[/fu_show_phase]
```

- `high_demand` shows during the early/mid part of the cycle.
- `final_week` shows in the last week, with a live ticking `[fu_countdown]`.
- Outside those windows, nothing shows. (Want the exact days? See the full
  reference.)

---

## Targeting a specific campaign

Got more than one program running? Each one has a **slug** (set by the super
admin, e.g. `summer-strength`). Add `program="that-slug"` to **any** shortcode:

```
[fu_card program="summer-strength"]
[fu_startdate slot="1" program="summer-strength"]
[fu_countdown program="summer-strength"]
```

Leave `program=""` off entirely and it uses the **default** program. So for a
campaign that lives on just one subsite: the super admin creates it at the
network level with its own slug, and you put `program="that-slug"` only on that
subsite's pages.

---

## Styling (the short version)

**Card block** — override these in your theme/Elementor Custom CSS:

```css
.fu-urgency .fu-card        { border-radius: 8px; border-color: #yourbrand; }
.fu-urgency .fu-card-date   { /* the date text */ }
.fu-urgency .fu-card-spots  { color: #yourbrand; /* the "3 spots left" text */ }
.fu-urgency .fu-card.fu-sold-out   { /* greyed sold-out state */ }
.fu-urgency .fu-card.fu-high-price { /* yellow "start next Monday" state */ }
```

**Inline bits** — every `[fu_startdate]`/`[fu_spotsleft]` span carries
`.fu-var`, plus `.fu-startdate`/`.fu-spotsleft`, plus `.fu-sold-out` or
`.fu-high-price` when relevant. You can also add your own class:

```
[fu_spotsleft slot="1" class="my-red-text"]
```

**Countdown** — `[fu_countdown]` ships with **no styling** on purpose, so you
style it freely. It renders `.fu-cd-days`, `.fu-cd-hours`, `.fu-cd-minutes`,
`.fu-cd-seconds` (and `.fu-cd-label` for the d/h/m/s letters). Full CSS
examples are in the deep reference.

---

## ⚠️ The one thing that trips everyone up: caching

This plugin changes what it shows **every day**. A page cache can freeze
yesterday's numbers and show the wrong date/spots/banner.

**On any page that uses these shortcodes, either:**
- **exclude the page from caching**, or
- **set the cache lifetime to 24 hours or less.**

Most "the banner is on the wrong day" or "spots didn't update" reports are
just a stale cache. (For visitors who leave a tab open overnight, the plugin
also auto-refreshes at midnight via JavaScript — but that can't fix
server-side cached HTML.)

---

## Quick troubleshooting

| You see… | Fix |
|---|---|
| Raw text `[fu_card]` on the page | Plugin isn't network-active. Super admin → Network Admin → Plugins → Network Activate. |
| Blank / nothing renders | No dates saved. Super admin → Network Admin → Settings → DWC - Spots Left → add the 4 Mondays. |
| Dates look off by a day | Check the site's Timezone (WP Admin → Settings → General). The plugin uses it for all date math. |
| Numbers/banners stuck on yesterday | **Caching.** Exclude the page or shorten cache lifetime (see above). |
| Countdown not ticking | A JS error on the page. Check the browser console. |
| No "Spots Left" menu on a subsite | Expected — it's Network Admin only. |

---

## Need the deep details?

The complete reference — exact phase windows, the full spots-by-day table,
slot transition rules, every CSS selector, and the Elementor-native build
approach — lives in **[DESIGNER-INSTRUCTIONS.md](DESIGNER-INSTRUCTIONS.md)**.
