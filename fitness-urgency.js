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
 *   2a. NAMED VARIABLES (use anywhere, on any page in the funnel):
 *
 *        Sign up for <span data-fu-var="startdate1"></span>
 *        — only <span data-fu-var="spotsleft1"></span>!
 *
 *      Available variables:
 *        startdate1   -> "Monday, June 8" / "Start next Monday"
 *        startdate2   -> "Monday, June 15" / "Start next Monday"
 *        spotsleft1   -> "9 spots left" / "1 spot left" / "SOLD OUT" / "2 spots left"
 *        spotsleft2   -> same
 *
 *      When a slot has no data (e.g. no second date to show on a single-date
 *      day), the [data-fu-var] element is hidden via display:none. Wrap a
 *      whole sentence in [data-fu-show-if-slot="2"] to hide the surrounding
 *      copy with it:
 *
 *        <span data-fu-show-if-slot="2">
 *          or join us <span data-fu-var="startdate2"></span>!
 *        </span>
 *
 *   2b. CARD-STYLE SLOT BLOCKS (alternative to 2a; both can be used):
 *
 *        <div data-fu-slot="1">
 *          <span data-fu-date>Monday, June 8</span>
 *          <span data-fu-spots>6 spots left</span>
 *        </div>
 *
 *      The wrapping element gets `fu-sold-out` / `fu-high-price` classes
 *      added when those states are active, for styling hooks.
 *
 *   3. Include this script once on the site (or per page):
 *
 *        <script src="fitness-urgency.js"></script>
 *
 * BEHAVIOUR
 * ---------
 *   - The script writes the next 1-2 upcoming Monday start dates into the
 *     two slots, with a "spots left" countdown that shrinks as each date
 *     approaches.
 *   - When only one date is left to show (Wed-Sat after the previous
 *     Monday but before the final date sells out), Slot 2 is hidden.
 *   - When the final start date sells out (Sun-Tue around it), Slot 2
 *     becomes the high-price "Start next Monday (2 spots left)" message
 *     and gets a `fu-high-price` class for styling.
 *   - When the final start date's window is fully past, Slot 1 becomes the
 *     high-price message and Slot 2 is hidden.
 *   - Re-renders at local midnight so the display rolls over without a
 *     page refresh.
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
    // Class hooks the script toggles on slot / variable elements.
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
  // VARIABLES
  // ============================================================
  // Map slots -> { startdate1, spotsleft1, startdate2, spotsleft2 }
  // Each value is { visible, text, soldOut, highPrice }.
  function computeVariables(slots) {
    var vars = {};
    for (var i = 0; i < 2; i++) {
      var n = i + 1;
      var slot = slots[i];
      if (!slot) {
        vars['startdate' + n] = { visible: false };
        vars['spotsleft' + n] = { visible: false };
        continue;
      }
      if (slot.type === 'highPrice') {
        vars['startdate' + n] = { visible: true, text: CONFIG.highPriceMessage, highPrice: true };
        vars['spotsleft' + n] = { visible: true, text: spotsLabel(CONFIG.highPriceSpots), highPrice: true };
      } else {
        vars['startdate' + n] = { visible: true, text: formatDate(slot.date) };
        vars['spotsleft' + n] = slot.spots.soldOut
          ? { visible: true, text: 'SOLD OUT', soldOut: true }
          : { visible: true, text: spotsLabel(slot.spots.count) };
      }
    }
    return vars;
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

  function applyVariables(slots) {
    var vars = computeVariables(slots);

    // [data-fu-var="..."] -> text replacement (hidden when no data)
    var varEls = document.querySelectorAll('[data-fu-var]');
    Array.prototype.forEach.call(varEls, function (el) {
      var name = el.getAttribute('data-fu-var');
      var v = vars[name];
      el.classList.remove(CONFIG.highPriceClass, CONFIG.soldOutClass);
      if (!v || !v.visible) {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        return;
      }
      el.style.display = '';
      el.removeAttribute('aria-hidden');
      el.textContent = v.text;
      if (v.soldOut) el.classList.add(CONFIG.soldOutClass);
      if (v.highPrice) el.classList.add(CONFIG.highPriceClass);
    });

    // [data-fu-show-if-slot="N"] -> wrapper visibility
    var showEls = document.querySelectorAll('[data-fu-show-if-slot]');
    Array.prototype.forEach.call(showEls, function (el) {
      var n = parseInt(el.getAttribute('data-fu-show-if-slot'), 10);
      var slot = slots[n - 1];
      if (slot) {
        el.style.display = '';
        el.removeAttribute('aria-hidden');
      } else {
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
      }
    });
  }

  function render() {
    var dates = CONFIG.programStartDates.length ? CONFIG.programStartDates : readDatesFromDOM();
    if (!dates.length) return; // No dates configured — leave the page alone.

    var slots = computeSlots(new Date(), dates);
    applyToSlot(document.querySelector('[data-fu-slot="1"]'), slots[0]);
    applyToSlot(document.querySelector('[data-fu-slot="2"]'), slots[1]);
    applyVariables(slots);
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
      computeVariables: computeVariables,
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
