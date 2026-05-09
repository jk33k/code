/**
 * Client-side midnight refresh for Fitness Urgency.
 *
 * PHP renders the correct values on first load (no flash of empty content).
 * This script re-runs the same logic when the site's local date rolls over
 * at midnight, so visitors who keep the page open overnight see fresh numbers
 * without a full page reload.
 *
 * fuRefreshData is inlined by PHP (FU_Shortcodes::enqueue_frontend) and
 * contains:
 *   { programs: { slug: { dates, highPriceMessage, highPriceSpots } },
 *     tzOffset: <site UTC offset in minutes, e.g. -300 for America/New_York> }
 */
(function () {
  'use strict';

  var data = (typeof fuRefreshData !== 'undefined') ? fuRefreshData : null;
  if (!data) return;

  // ── Spots table (mirrors PHP renderer) ────────────────────────
  function spotsForDaysUntil(t) {
    if (t < -1) return { past: true };
    if (t <= 1)  return { soldOut: true };
    if (t <= 3)  return { count: 1 };
    if (t === 4) return { count: 2 };
    if (t === 5) return { count: 3 };
    if (t === 6) return { count: 4 };
    if (t === 7) return { count: 5 };
    return { count: 5 + Math.ceil((t - 7) / 2) };
  }

  function spotsLabel(n) {
    return n === 1 ? '1 spot left' : n + ' spots left';
  }

  // ── Date helpers ───────────────────────────────────────────────
  function parseISODate(s) {
    var p = s.split('-');
    return new Date(+p[0], +p[1] - 1, +p[2]);
  }

  /** Today's date in site timezone (using the UTC offset from PHP). */
  function siteToday() {
    var now      = new Date();
    var utcMs    = now.getTime() + now.getTimezoneOffset() * 60000;
    var siteNow  = new Date(utcMs + data.tzOffset * 60000);
    return new Date(siteNow.getFullYear(), siteNow.getMonth(), siteNow.getDate());
  }

  function daysBetween(from, to) {
    return Math.round((to.getTime() - from.getTime()) / 86400000);
  }

  function formatDate(d) {
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
  }

  // ── Slot computation (mirrors PHP renderer) ────────────────────
  function computeSlots(dateStrings, programCfg, today) {
    var dates = dateStrings.map(parseISODate);
    var t0    = today || siteToday();

    var slot1Index = -1;
    for (var i = 0; i < dates.length; i++) {
      if (daysBetween(t0, dates[i]) >= -1) { slot1Index = i; break; }
    }

    if (slot1Index === -1) return [{ type: 'highPrice' }];

    var slot1Date = dates[slot1Index];
    var slot1T    = daysBetween(t0, slot1Date);
    var slot1     = { type: 'date', date: slot1Date, daysUntil: slot1T, spots: spotsForDaysUntil(slot1T) };

    var slot2Date = dates[slot1Index + 1];
    if (slot2Date) {
      var slot2T = daysBetween(t0, slot2Date);
      return [slot1, { type: 'date', date: slot2Date, daysUntil: slot2T, spots: spotsForDaysUntil(slot2T) }];
    }

    if (slot1.spots.soldOut) return [slot1, { type: 'highPrice' }];
    return [slot1];
  }

  // ── DOM update ─────────────────────────────────────────────────
  function applySlotToCard(el, slot, programCfg) {
    if (!el) return;
    el.classList.remove('fu-sold-out', 'fu-high-price');

    var dateEl  = el.querySelector('.fu-card-date');
    var spotsEl = el.querySelector('.fu-card-spots');

    if (slot.type === 'highPrice') {
      el.classList.add('fu-high-price');
      if (dateEl)  dateEl.textContent  = programCfg.highPriceMessage;
      if (spotsEl) spotsEl.textContent = spotsLabel(programCfg.highPriceSpots);
      return;
    }
    if (dateEl) dateEl.textContent = formatDate(slot.date);
    if (slot.spots.soldOut) {
      el.classList.add('fu-sold-out');
      if (spotsEl) spotsEl.textContent = 'SOLD OUT';
    } else if (spotsEl) {
      spotsEl.textContent = spotsLabel(slot.spots.count);
    }
  }

  function applyVarEl(el, vars) {
    var name = el.getAttribute('data-fu-var');
    var v    = name && vars[name];
    el.classList.remove('fu-sold-out', 'fu-high-price');
    if (!v || !v.visible) { el.style.display = 'none'; return; }
    el.style.display = '';
    el.textContent   = v.text;
    if (v.soldOut)   el.classList.add('fu-sold-out');
    if (v.highPrice) el.classList.add('fu-high-price');
  }

  function computeVars(slots, programCfg) {
    var vars = {};
    for (var i = 0; i < 2; i++) {
      var n    = i + 1;
      var slot = slots[i];
      if (!slot) {
        vars['startdate' + n] = { visible: false };
        vars['spotsleft' + n] = { visible: false };
        continue;
      }
      if (slot.type === 'highPrice') {
        vars['startdate' + n] = { visible: true, text: programCfg.highPriceMessage, highPrice: true };
        vars['spotsleft' + n] = { visible: true, text: spotsLabel(programCfg.highPriceSpots), highPrice: true };
      } else {
        vars['startdate' + n] = { visible: true, text: formatDate(slot.date) };
        vars['spotsleft' + n] = slot.spots.soldOut
          ? { visible: true, text: 'SOLD OUT', soldOut: true }
          : { visible: true, text: spotsLabel(slot.spots.count) };
      }
    }
    return vars;
  }

  function refresh() {
    var today = siteToday();

    // Update fu-card blocks (rendered by [fu_card] shortcode).
    document.querySelectorAll('.fu-urgency').forEach(function (wrapper) {
      var slug = wrapper.getAttribute('data-fu-program') || Object.keys(data.programs)[0];
      var cfg  = data.programs[slug];
      if (!cfg) return;

      var slots = computeSlots(cfg.dates, cfg, today);

      // Show/hide and update each card.
      var cards = wrapper.querySelectorAll('.fu-card');
      slots.forEach(function (slot, i) {
        if (cards[i]) { cards[i].style.display = ''; applySlotToCard(cards[i], slot, cfg); }
      });
      // Hide any extra cards.
      for (var i = slots.length; i < cards.length; i++) cards[i].style.display = 'none';
    });

    // Update [data-fu-var] elements (rendered by [fu_startdate]/[fu_spotsleft]).
    document.querySelectorAll('[data-fu-var]').forEach(function (el) {
      var slug = el.getAttribute('data-fu-program') || Object.keys(data.programs)[0];
      var cfg  = data.programs[slug];
      if (!cfg) return;
      var slots = computeSlots(cfg.dates, cfg, today);
      var vars  = computeVars(slots, cfg);
      applyVarEl(el, vars);
    });

    // [fu_show_if_slot] wrappers.
    document.querySelectorAll('[data-fu-show-if-slot]').forEach(function (el) {
      var slug      = el.getAttribute('data-fu-program') || Object.keys(data.programs)[0];
      var cfg       = data.programs[slug];
      var slotIndex = parseInt(el.getAttribute('data-fu-show-if-slot'), 10) - 1;
      if (!cfg) return;
      var slots = computeSlots(cfg.dates, cfg, today);
      el.style.display = slots[slotIndex] ? '' : 'none';
    });
  }

  // ── Schedule midnight rollover ─────────────────────────────────
  function scheduleMidnight() {
    var now  = new Date();
    // Compute next site-local midnight in UTC.
    var utcMs   = now.getTime() + now.getTimezoneOffset() * 60000;
    var siteNow = new Date(utcMs + data.tzOffset * 60000);
    var siteMidnight = new Date(
      siteNow.getFullYear(), siteNow.getMonth(), siteNow.getDate() + 1,
      0, 0, 5 // 5s after midnight to clear any edge-case ties
    );
    var siteToUTC = siteMidnight.getTime() - data.tzOffset * 60000;
    var msUntil   = siteToUTC - now.getTime();

    setTimeout(function () {
      refresh();
      scheduleMidnight();
    }, msUntil);
  }

  scheduleMidnight();
})();
