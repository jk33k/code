/*!
 * Fitness Program Urgency Display
 *
 * Drop-in script that renders 1-2 upcoming Monday start dates with a
 * "spots left" countdown that shrinks as each start date approaches.
 *
 * USAGE
 *   1. Add a container element to your page:
 *        <div id="fitness-urgency"></div>
 *   2. Configure the program dates below (or pass via window.FitnessUrgencyConfig
 *      before loading this script).
 *   3. Include the script:
 *        <script src="fitness-urgency.js"></script>
 *
 * The script auto-renders on DOMContentLoaded and re-renders at local midnight
 * so the display updates without a page refresh.
 */
(function () {
  'use strict';

  // ============================================================
  // CONFIG  (override via window.FitnessUrgencyConfig before load)
  // ============================================================
  var DEFAULTS = {
    // Monday start dates as 'YYYY-MM-DD' (local time).
    programStartDates: [
      '2026-06-08',
      '2026-06-15',
      '2026-06-22',
      '2026-06-29',
    ],
    containerSelector: '#fitness-urgency',
    locale: 'en-US',
    // Copy shown after the final start date has passed.
    highPriceMessage: 'Start next Monday',
    highPriceSpots: 2,
    // CSS class hooks (style however you like in your own stylesheet).
    classes: {
      root: 'fu',
      slot: 'fu-slot',
      slotHighPrice: 'fu-slot--high-price',
      slotSoldOut: 'fu-slot--sold-out',
      date: 'fu-date',
      spots: 'fu-spots',
      soldOut: 'fu-sold-out',
    },
  };

  var CONFIG = Object.assign(
    {},
    DEFAULTS,
    (typeof window !== 'undefined' && window.FitnessUrgencyConfig) || {}
  );

  // ============================================================
  // SPOTS TABLE
  // ============================================================
  // T = days until start date. Pattern derived from the spec:
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
    // T >= 8: 5 + ceil((T - 7) / 2)  =>  8,9->6 ; 10,11->7 ; 12,13->8 ; ...
    return { count: 5 + Math.ceil((t - 7) / 2) };
  }

  // ============================================================
  // DATE HELPERS
  // ============================================================
  function parseISODate(s) {
    var parts = s.split('-');
    return new Date(+parts[0], +parts[1] - 1, +parts[2]);
  }

  function startOfDay(d) {
    var x = new Date(d.getTime());
    x.setHours(0, 0, 0, 0);
    return x;
  }

  function daysBetween(from, to) {
    var ms = startOfDay(to).getTime() - startOfDay(from).getTime();
    return Math.round(ms / 86400000);
  }

  function formatDate(d) {
    return d.toLocaleDateString(CONFIG.locale, {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
    });
  }

  // ============================================================
  // SLOT COMPUTATION
  // ============================================================
  // Returns 1-2 slot objects:
  //   { type: 'date', date, daysUntil, spots: { count } | { soldOut: true } }
  //   { type: 'highPrice' }
  function computeSlots(today, startDateStrings) {
    var dates = (startDateStrings || CONFIG.programStartDates).map(parseISODate);
    var t0 = startOfDay(today);

    // Slot 1: earliest D whose active window hasn't ended (today <= D + 1).
    var slot1Index = -1;
    for (var i = 0; i < dates.length; i++) {
      if (daysBetween(t0, dates[i]) >= -1) {
        slot1Index = i;
        break;
      }
    }

    if (slot1Index === -1) {
      // Past the final start date's window — only the high-price slot.
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

    // No real next date — show high-price slot only once Slot 1 sells out.
    if (slot1.spots.soldOut) {
      return [slot1, { type: 'highPrice' }];
    }
    return [slot1];
  }

  // ============================================================
  // RENDERING
  // ============================================================
  function escapeHTML(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function spotsLabel(n) {
    return n + ' ' + (n === 1 ? 'spot' : 'spots') + ' left';
  }

  function renderSlot(slot) {
    var cls = CONFIG.classes;
    if (slot.type === 'highPrice') {
      return (
        '<div class="' + cls.slot + ' ' + cls.slotHighPrice + '">' +
          '<div class="' + cls.date + '">' + escapeHTML(CONFIG.highPriceMessage) + '</div>' +
          '<div class="' + cls.spots + '">' + escapeHTML(spotsLabel(CONFIG.highPriceSpots)) + '</div>' +
        '</div>'
      );
    }
    var dateHtml = escapeHTML(formatDate(slot.date));
    var spotsHtml;
    var slotClass = cls.slot;
    if (slot.spots.soldOut) {
      slotClass += ' ' + cls.slotSoldOut;
      spotsHtml = '<span class="' + cls.soldOut + '">SOLD OUT</span>';
    } else {
      spotsHtml = escapeHTML(spotsLabel(slot.spots.count));
    }
    return (
      '<div class="' + slotClass + '">' +
        '<div class="' + cls.date + '">' + dateHtml + '</div>' +
        '<div class="' + cls.spots + '">' + spotsHtml + '</div>' +
      '</div>'
    );
  }

  function render() {
    var container = document.querySelector(CONFIG.containerSelector);
    if (!container) return;
    var slots = computeSlots(new Date());
    container.className = (container.className ? container.className + ' ' : '') + CONFIG.classes.root;
    container.innerHTML = slots.map(renderSlot).join('');
  }

  // Re-render at the next local midnight so the display rolls over without
  // requiring a page refresh.
  function scheduleMidnightRefresh() {
    var now = new Date();
    var next = new Date(now);
    next.setHours(24, 0, 5, 0); // 5s after midnight to avoid edge cases
    setTimeout(function () {
      render();
      scheduleMidnightRefresh();
    }, next.getTime() - now.getTime());
  }

  // Public API (also useful for tests).
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
