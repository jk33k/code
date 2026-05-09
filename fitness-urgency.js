/*!
 * Fitness Program Urgency Display
 *
 * Fills in date and "spots left" text inside an existing design.
 * Works inside Elementor / CartFlows / any HTML — the designer marks the
 * spots in their layout with data attributes and the script writes to them.
 *
 * MARKUP THE DESIGNER ADDS TO THE PAGE
 * ------------------------------------
 *   1. Specify the program's start dates somewhere on the page (anywhere,
 *      can be a hidden div). Comma-separated YYYY-MM-DD Mondays:
 *
 *        <div data-fu-dates="2026-06-08,2026-06-15,2026-06-22,2026-06-29"></div>
 *
 *   2. Mark the two slot blocks in the design. Each slot has a date
 *      element and a spots element identified by data attributes:
 *
 *        <div data-fu-slot="1">
 *          ...
 *          <span data-fu-date>Monday, June 8</span>
 *          ...
 *          <span data-fu-spots>6 spots left</span>
 *          ...
 *        </div>
 *
 *        <div data-fu-slot="2">
 *          <span data-fu-date>Monday, June 15</span>
 *          <span data-fu-spots>9 spots left</span>
 *        </div>
 *
 *   3. Include this script once at the end of the page (or in the footer
 *      via your code-injection plugin):
 *
 *        <script src="fitness-urgency.js"></script>
 *
 * BEHAVIOUR
 * ---------
 *   - The script writes the next 1-2 upcoming Monday start dates into the
 *     two slots, with a "spots left" countdown that shrinks as each date
 *     approaches.
 *   - When only one date is left to show (Wed-Sat after the previous
 *     Monday but before the final date sells out), Slot 2 is hidden via
 *     display:none.
 *   - When the final start date sells out (Sun-Tue around it), Slot 2 is
 *     replaced with the high-price "Start next Monday (2 spots left)"
 *     message and the slot gets a `fu-high-price` class for styling.
 *   - When the final start date's window is fully past, Slot 1 shows the
 *     high-price message and Slot 2 is hidden.
 */
(function () {
  'use strict';

  var DEFAULTS = {
    // Optional: dates can also come from data-fu-dates on a page element.
    programStartDates: [],
    locale: 'en-US',
    dateFormat: { weekday: 'long', month: 'long', day: 'numeric' },
    // Copy shown after the final start date has passed.
    highPriceMessage: 'Start next Monday',
    highPriceSpots: 2,
    // Class hooks the script toggles on slot elements (style in your CSS).
    highPriceClass: 'fu-high-price',
    soldOutClass: 'fu-sold-out',
  };

  var CONFIG = Object.assign(
    {},
    DEFAULTS,
    (typeof window !== 'undefined' && window.FitnessUrgencyConfig) || {}
  );

  // ============================================================
  // SPOTS TABLE
  // ============================================================
  // T = days until start date.
  //   T=15,14 -> 9   T=13,12 -> 8   T=11,10 -> 7   T=9,8 -> 6
  //   T=7 -> 5  T=6 -> 4  T=5 -> 3  T=4 -> 2  T=3,2 -> 1
  //   T=1,0,-1 -> SOLD OUT
  // For T > 15 the same "two days per spot" cadence is extrapolated.
  function spotsForDaysUntil(t) {
    if (t < -1) return { past: true };
    if (t <= 1) return { soldOut: true };
    if (t <= 3) return { count: 1 };
    if (t === 4) return { count: 2 };
    if (t === 5) return { count: 3 };
    if (t === 6) return { count: 4 };
    if (t === 7) return { count: 5 };
    return { count: 5 + Math.ceil((t - 7) / 2) };
  }

  // ============================================================
  // DATE HELPERS
  // ============================================================
  function parseISODate(s) {
    var p = s.split('-');
    return new Date(+p[0], +p[1] - 1, +p[2]);
  }

  function startOfDay(d) {
    var x = new Date(d.getTime());
    x.setHours(0, 0, 0, 0);
    return x;
  }

  function daysBetween(from, to) {
    return Math.round((startOfDay(to).getTime() - startOfDay(from).getTime()) / 86400000);
  }

  function formatDate(d) {
    return d.toLocaleDateString(CONFIG.locale, CONFIG.dateFormat);
  }

  function spotsLabel(n) {
    return n + ' ' + (n === 1 ? 'spot' : 'spots') + ' left';
  }

  // ============================================================
  // SLOT COMPUTATION  (logic identical to spec)
  // ============================================================
  function computeSlots(today, startDateStrings) {
    var dates = (startDateStrings || []).map(parseISODate);
    var t0 = startOfDay(today);

    var slot1Index = -1;
    for (var i = 0; i < dates.length; i++) {
      if (daysBetween(t0, dates[i]) >= -1) {
        slot1Index = i;
        break;
      }
    }

    if (slot1Index === -1) {
      return [{ type: 'highPrice' }];
    }

    var slot1Date = dates[slot1Index];
    var slot1T = daysBetween(t0, slot1Date);
    var slot1 = {
      type: 'date',
      date: slot1Date,
      daysUntil: slot1T,
      spots: spotsForDaysUntil(slot1T),
    };

    var slot2Date = dates[slot1Index + 1];
    if (slot2Date) {
      var slot2T = daysBetween(t0, slot2Date);
      return [
        slot1,
        {
          type: 'date',
          date: slot2Date,
          daysUntil: slot2T,
          spots: spotsForDaysUntil(slot2T),
        },
      ];
    }

    if (slot1.spots.soldOut) {
      return [slot1, { type: 'highPrice' }];
    }
    return [slot1];
  }

  // ============================================================
  // DOM WRITING
  // ============================================================
  function readDatesFromDOM() {
    var el = document.querySelector('[data-fu-dates]');
    if (!el) return [];
    var s = el.getAttribute('data-fu-dates') || '';
    return s.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
  }

  function applyToSlot(el, data) {
    if (!el) return;
    el.classList.remove(CONFIG.highPriceClass, CONFIG.soldOutClass);

    if (!data) {
      el.style.display = 'none';
      el.setAttribute('aria-hidden', 'true');
      return;
    }
    el.style.display = '';
    el.removeAttribute('aria-hidden');

    var dateEl = el.querySelector('[data-fu-date]');
    var spotsEl = el.querySelector('[data-fu-spots]');

    if (data.type === 'highPrice') {
      el.classList.add(CONFIG.highPriceClass);
      if (dateEl) dateEl.textContent = CONFIG.highPriceMessage;
      if (spotsEl) spotsEl.textContent = spotsLabel(CONFIG.highPriceSpots);
      return;
    }

    if (dateEl) dateEl.textContent = formatDate(data.date);
    if (data.spots.soldOut) {
      el.classList.add(CONFIG.soldOutClass);
      if (spotsEl) spotsEl.textContent = 'SOLD OUT';
    } else if (spotsEl) {
      spotsEl.textContent = spotsLabel(data.spots.count);
    }
  }

  function render() {
    var dates = CONFIG.programStartDates.length ? CONFIG.programStartDates : readDatesFromDOM();
    if (!dates.length) return; // No dates configured — leave the page alone.

    var slots = computeSlots(new Date(), dates);
    applyToSlot(document.querySelector('[data-fu-slot="1"]'), slots[0]);
    applyToSlot(document.querySelector('[data-fu-slot="2"]'), slots[1]);
  }

  function scheduleMidnightRefresh() {
    var now = new Date();
    var next = new Date(now);
    next.setHours(24, 0, 5, 0);
    setTimeout(function () {
      render();
      scheduleMidnightRefresh();
    }, next.getTime() - now.getTime());
  }

  if (typeof window !== 'undefined') {
    window.FitnessUrgency = {
      render: render,
      computeSlots: computeSlots,
      spotsForDaysUntil: spotsForDaysUntil,
      config: CONFIG,
    };
  }

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        render();
        scheduleMidnightRefresh();
      });
    } else {
      render();
      scheduleMidnightRefresh();
    }
  }
})();
