# How the Spots Change — Visual Timeline Guide

**DWC – Spots Left** shows urgency by counting *spots left* down toward each
start date, and by *rolling* the displayed dates forward as each one passes.

This guide explains, as visually as possible, exactly **when the numbers
change** for each setup:

- [1 start date](#-1-start-date-single-mode)
- [2 start dates](#-2-start-dates-double-mode)
- [3 start dates](#-3-start-dates-triple-mode)
- [4 start dates](#-4-start-dates-fixed-mode--the-classic-cycle)

> The number of dates you enter in the admin **automatically** decides the
> behaviour — you don't pick a "mode" anywhere. 1 date = single, 2 = double,
> 3 = triple, 4+ = fixed.

---

## The one rule behind everything: the Spots Table

Every card looks at **T = how many days from today until that start date**,
then shows a spot count. The closer the date, the fewer spots.

```
   T (days until the date)            Spots shown
 ───────────────────────────────────────────────────
   20–21 days away ........................ 12 spots
   18–19 days away ........................ 11 spots
   16–17 days away ........................ 10 spots
   14–15 days away .........................  9 spots
   12–13 days away .........................  8 spots
   10–11 days away .........................  7 spots
    8–9  days away .........................  6 spots
    7    days away .........................  5 spots
    6    days away .........................  4 spots
    5    days away .........................  3 spots
    4    days away .........................  2 spots
    2–3  days away .........................  1 spot
    1 day away / today / 1 day ago ......... 🔴 SOLD OUT
    2+ days ago ............................ (date disappears)
```

**Visual shape of a single date's life** (spots drop roughly **1 every 2
days**, then a hard SOLD OUT at the end):

```
spots
 12 │■
 11 │■ ■
 10 │■ ■ ■
  9 │■ ■ ■ ■
  8 │■ ■ ■ ■ ■
  7 │■ ■ ■ ■ ■ ■
  6 │■ ■ ■ ■ ■ ■ ■
  5 │■ ■ ■ ■ ■ ■ ■ ■
  4 │■ ■ ■ ■ ■ ■ ■ ■ ■
  3 │■ ■ ■ ■ ■ ■ ■ ■ ■ ■
  2 │■ ■ ■ ■ ■ ■ ■ ■ ■ ■ ■
  1 │■ ■ ■ ■ ■ ■ ■ ■ ■ ■ ■ ■ ■
    └────────────────────────────────────► time →
     21        14        7   5   3  2  1  0  🔴 SOLD OUT
              days until the start date
```

Two more rules that make dates **roll**:

- **A date stays on screen until the day AFTER it.** A Monday start shows
  `SOLD OUT` on Mon and Tue, then **disappears on Wednesday**.
- **Slot 1** = the next/current date. **Slot 2** = the date after it.
  When Slot 1's date disappears, everything shifts left: the old Slot 2
  becomes the new Slot 1, and the next date moves into Slot 2.

Everything below is just these rules playing out for different numbers of dates.

---

## 🟩 1 start date (single mode)

**You enter:** one date (example: **Monday, June 29th**).

**The twist:** the card is **hidden until 21 days before** the date — no point
showing urgency months out. The moment it enters the 21-day window, it appears
and counts down. There is **no Slot 2** (no "next" date to roll into) until the
very end.

```
        hidden                appears & counts down              ends
   ┌──────────────┐   ┌───────────────────────────────┐   ┌──────────────┐
   │  (nothing on │   │ 12 → 9 → 6 → 3 → 2 → 1 spot →  │   │ 🔴 SOLD OUT  │
   │   the page)  │   │            🔴 SOLD OUT         │   │ → high-price │
   └──────────────┘   └───────────────────────────────┘   └──────────────┘
   more than 21          21 days  ........  0 days          day after the
   days before                  before                        date passes
```

**Day-by-day (start date = Mon Jun 29th):**

| Today        | Days until | What shows on the page                |
|--------------|:----------:|---------------------------------------|
| up to Jun 7  |   22+      | *(nothing — outside the 21-day gate)* |
| **Jun 8**    |    21      | June 29th — **12 spots left**         |
| Jun 14       |    15      | June 29th — **9 spots left**          |
| Jun 20       |     9      | June 29th — **6 spots left**          |
| Jun 24       |     5      | June 29th — **3 spots left**          |
| Jun 26       |     3      | June 29th — **1 spot left**           |
| Jun 27       |     2      | June 29th — **1 spot left**           |
| **Jun 28**   |     1      | June 29th — 🔴 **SOLD OUT** *(+ "Start next Monday — 2 spots")* |
| **Jun 29**   |     0      | June 29th — 🔴 **SOLD OUT**           |
| Jun 30       |    −1      | June 29th — 🔴 **SOLD OUT**           |
| **Jul 1**    |    −2      | "**Start next Monday — 2 spots**" (yellow high-price card) |

➡️ **Change moments:** appears at 21 days, then drops a spot every ~2 days,
SOLD OUT the day before, and flips to the yellow "high-price" card two days
after the date.

---

## 🟦 2 start dates (double mode)

**You enter:** two dates (example: **Jun 8** and **Jun 15**).

Now there's always a Slot 2 — until the first date disappears. The page shows
**two cards counting down side by side**, and the pair rolls forward once.

```
   SLOT 1 (next date)          SLOT 2 (the one after)
 ┌────────────────────┐      ┌────────────────────┐
 │     June 8th       │      │     June 15th      │
 │   5 → 1 → SOLD OUT │      │   9 → 6 → 5 spots  │
 └────────────────────┘      └────────────────────┘
            │                          │
            │  Jun 8 passes (gone Wed Jun 10)
            ▼                          ▼
 ┌────────────────────┐      ┌────────────────────┐
 │     June 15th      │      │    (no more dates  │
 │   3 → 1 → SOLD OUT │      │     → hidden, then │
 │                    │      │     high-price)    │
 └────────────────────┘      └────────────────────┘
```

**Day-by-day (dates = Jun 8 & Jun 15):**

| Today      | Slot 1 (card 1)            | Slot 2 (card 2)                 |
|------------|----------------------------|---------------------------------|
| Jun 1      | June 8th — **5 spots**     | June 15th — **9 spots**         |
| Jun 6      | June 8th — **1 spot**      | June 15th — **6 spots**         |
| **Jun 8**  | June 8th — 🔴 **SOLD OUT** | June 15th — **5 spots**         |
| Jun 9      | June 8th — 🔴 **SOLD OUT** | June 15th — **4 spots**         |
| **Jun 10** | June 15th — **3 spots**    | *(hidden — no further date)*    |
| Jun 13     | June 15th — **1 spot**     | *(hidden)*                      |
| **Jun 14** | June 15th — 🔴 **SOLD OUT**| "Start next Monday — 2 spots"   |
| Jun 15     | June 15th — 🔴 **SOLD OUT**| "Start next Monday — 2 spots"   |
| **Jun 17** | "Start next Monday — 2 spots" | *(hidden)*                   |

➡️ **Change moments:** both cards count down daily; on **Wed Jun 10** the sold-out
June 8 card disappears and June 15 slides into Slot 1; Slot 2 goes empty (then
high-price) because there's no third date.

---

## 🟧 3 start dates (triple mode)

**You enter:** three dates (example: **Jun 8**, **Jun 15**, **Jun 22**).

Same rolling behaviour as 2 dates, but now the pair rolls forward **twice**
before the cycle ends. At most **two cards** are ever visible — Slot 2 always
shows the *next* upcoming date.

```
 Week of Jun 8        Week of Jun 15       Week of Jun 22
 ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
 │ S1: Jun 8    │ ──► │ S1: Jun 15   │ ──► │ S1: Jun 22   │
 │ S2: Jun 15   │     │ S2: Jun 22   │     │ S2: (none →  │
 │              │     │              │     │     high-pr.)│
 └──────────────┘     └──────────────┘     └──────────────┘
   roll on Jun 10       roll on Jun 17       ends after Jun 22
```

**Day-by-day (dates = Jun 8, 15, 22):**

| Today      | Slot 1                     | Slot 2                        |
|------------|----------------------------|-------------------------------|
| Jun 1      | June 8th — **5 spots**     | June 15th — **9 spots**       |
| Jun 6      | June 8th — **1 spot**      | June 15th — **6 spots**       |
| **Jun 8**  | June 8th — 🔴 **SOLD OUT** | June 15th — **5 spots**       |
| **Jun 10** | June 15th — **3 spots**    | June 22nd — **8 spots**       |
| **Jun 15** | June 15th — 🔴 **SOLD OUT**| June 22nd — **5 spots**       |
| **Jun 17** | June 22nd — **3 spots**    | *(hidden — no further date)*  |
| Jun 20     | June 22nd — **1 spot**     | *(hidden)*                    |
| **Jun 21** | June 22nd — 🔴 **SOLD OUT**| "Start next Monday — 2 spots" |
| **Jun 24** | "Start next Monday — 2 spots" | *(hidden)*                 |

➡️ **Change moments:** rolls on **Jun 10** (Jun 8 → out, Jun 22 into Slot 2)
and again on **Jun 17** (Jun 15 → out, no date left for Slot 2). Otherwise spots
tick down daily.

---

## 🟥 4 start dates (fixed mode — the classic cycle)

**You enter:** four dates (example: **Jun 8 / 15 / 22 / 29**). This is the
typical month-long cycle. The pair rolls forward **three times**.

```
 ┌─ Jun 8 ─┐ ┌─ Jun 15 ┐ ┌─ Jun 22 ┐ ┌─ Jun 29 ┐
 │ S1      │ │ S1      │ │ S1      │ │ S1      │  (last date)
 │ S2:Jun15│ │ S2:Jun22│ │ S2:Jun29│ │ S2:high │
 └─────────┘ └─────────┘ └─────────┘ └─────────┘
  roll Jun10   roll Jun17  roll Jun24   ends Jul 1
```

**Day-by-day (dates = Jun 8, 15, 22, 29):**

| Today      | Slot 1                     | Slot 2                        |
|------------|----------------------------|-------------------------------|
| **May 25** | June 8th — **9 spots**     | June 15th — **12 spots**      |
| May 31     | June 8th — **6 spots**     | June 15th — **9 spots**       |
| Jun 4      | June 8th — **2 spots**     | June 15th — **7 spots**       |
| **Jun 8**  | June 8th — 🔴 **SOLD OUT** | June 15th — **5 spots**       |
| **Jun 10** | June 15th — **3 spots**    | June 22nd — **8 spots**       |
| **Jun 15** | June 15th — 🔴 **SOLD OUT**| June 22nd — **5 spots**       |
| **Jun 17** | June 22nd — **3 spots**    | June 29th — **8 spots**       |
| **Jun 22** | June 22nd — 🔴 **SOLD OUT**| June 29th — **5 spots**       |
| **Jun 24** | June 29th — **3 spots**    | *(hidden — last date)*        |
| Jun 27     | June 29th — **1 spot**     | *(hidden)*                    |
| **Jun 28** | June 29th — 🔴 **SOLD OUT**| "Start next Monday — 2 spots" |
| Jun 29/30  | June 29th — 🔴 **SOLD OUT**| "Start next Monday — 2 spots" |
| **Jul 1**  | "Start next Monday — 2 spots" | *(hidden)*                 |

➡️ **Change moments:** rolls every Wednesday after a start Monday (**Jun 10,
17, 24**); the banner phases also kick in — see below.

### Bonus: the banner phases (4-date cycles)

With a full cycle, an optional banner above the cards switches on/off:

```
 Jun 8        Jun 10 ─────────────── Jun 20      Jun 21 ───── Jun 27   Jun 28+
   │             │   "🔥 HIGH DEMAND"   │           │ "⏰ FINAL WEEK" │     │
   ▼             ╞═════════════════════╡           ╞════════════════╡     ▼
 first        starts Wed after      ends Sat     starts Sun       ends   (off until
 Monday        first Monday        before final   of final wk     Sat    next cycle)
```

- **🔥 High demand:** Jun 10 → Jun 20
- **⏰ Final week:** Jun 21 → Jun 27
- **Live countdown** (`[fu_countdown]`) ticks down to **midnight Jun 28**.

---

## 🟪 5 or 6 start dates (also `fixed` mode)

**You enter:** five or six dates (example 5-date: **Jun 8 / 15 / 22 / 29 / Jul 6**;
example 6-date: add **Jul 13**). In the admin, click **+ Add date** once or twice
to add a 5th and 6th row.

**Behaviour:** identical rolling logic as the 4-date cycle — **the only
difference is one extra rollover per added date**. With 5 dates the pair rolls
forward **4 times**, with 6 dates **5 times**, before reaching the high-price
end state.

```
 4 dates ──► 3 rollovers ──► ends
 5 dates ──► 4 rollovers ──► ends
 6 dates ──► 5 rollovers ──► ends
```

The visible behaviour you'll notice: a day that would have shown a single card
in a 4-date cycle (the days between the second-to-last Monday and the final
Monday) now shows **two cards**, because a "next" date exists for Slot 2.

**Day-by-day (5 dates = Jun 8 / 15 / 22 / 29 / Jul 6):**

| Today      | Slot 1                     | Slot 2                                          |
|------------|----------------------------|-------------------------------------------------|
| May 31     | June 8th — **6 spots**     | June 15th — **9 spots**                         |
| **Jun 10** | June 15th — **3 spots**    | June 22nd — **8 spots**                         |
| **Jun 17** | June 22nd — **3 spots**    | June 29th — **8 spots**                         |
| **Jun 24** | June 29th — **3 spots**    | **July 6th — 8 spots** *(was hidden in 4-date)* |
| Jun 28     | June 29th — 🔴 **SOLD OUT**| July 6th — **6 spots**                          |
| **Jul 1**  | July 6th — **3 spots**     | *(hidden — no further date)*                    |
| Jul 5      | July 6th — 🔴 **SOLD OUT** | "Start next Monday — 2 spots"                   |
| **Jul 8**  | "Start next Monday — 2 spots" | *(hidden)*                                   |

**Day-by-day (6 dates = the above + Jul 13):**

| Today      | Slot 1                     | Slot 2                                          |
|------------|----------------------------|-------------------------------------------------|
| **Jul 1**  | July 6th — **3 spots**     | **July 13th — 8 spots** *(was hidden in 5-date)* |
| Jul 5      | July 6th — 🔴 **SOLD OUT** | July 13th — **6 spots**                         |
| **Jul 8**  | July 13th — **3 spots**    | *(hidden — no further date)*                    |
| Jul 12     | July 13th — 🔴 **SOLD OUT**| "Start next Monday — 2 spots"                   |
| **Jul 15** | "Start next Monday — 2 spots" | *(hidden)*                                   |

➡️ **Change moments:** the same Wednesday rollover after every Monday start —
each extra date just buys one more rollover before the cycle ends.

> ⚠️ The `🔥 high-demand` and `⏰ final-week` banners and the live
> `[fu_countdown]` still anchor to the **first** and **last** dates of the
> cycle, so longer cycles get a longer high-demand window and the same
> 7-day final week before the last Monday.

---

## Side-by-side summary

| Setup            | Cards shown | Rolls forward | Hidden until 21d? | End state                |
|------------------|:-----------:|:-------------:|:-----------------:|--------------------------|
| **1 date**       | 1           | never         | ✅ yes            | high-price card          |
| **2 dates**      | 1–2         | once          | ❌ no             | high-price card          |
| **3 dates**      | 1–2         | twice         | ❌ no             | high-price card          |
| **4 dates**      | 1–2         | three times   | ❌ no             | high-price card + phases |
| **5 dates**      | 1–2         | four times    | ❌ no             | high-price card + phases |
| **6 dates**      | 1–2         | five times    | ❌ no             | high-price card + phases |
| *N dates*        | 1–2         | N − 1 times   | ❌ no             | high-price card + phases |
| *(ongoing)*      | always 2    | forever       | ❌ no             | never ends               |

**The shared heartbeat for every setup:**

1. **Each day** at midnight, every visible card loses spots as its date nears
   (about 1 spot every 2 days).
2. **The day before** a date → 🔴 SOLD OUT.
3. **Two days after** a date → it disappears and the others roll left.
4. When no dates remain → the yellow **"Start next Monday — 2 spots"**
   high-price card.

> 💡 **Note on caching:** these numbers recompute at midnight (site timezone).
> If your landing pages are heavily cached, exclude them from cache or set the
> cache lifetime to ≤ 24 hours, or visitors may see yesterday's spot counts.

---

*All values above come directly from `FU_Renderer::spots_for_days_until()` and
`compute_slots()` in the plugin, and are covered by the test suite
(`tests/RendererTest.php`). Dates used are illustrative Mondays.*
