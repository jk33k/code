/**
 * Client-side refresh for Fitness Urgency.
 *
 * PHP renders the correct values on first load (no flash of empty content).
 * This script handles two things:
 *   1. Midnight rollover — re-runs slot/phase logic when the site's local date
 *      changes, so visitors who keep the page open overnight see fresh content.
 *   2. Live countdown — ticks [fu_countdown] every second via setInterval.
 *
 * fuRefreshData is inlined by PHP (FU_Shortcodes::enqueue_frontend):
 *   { programs: { slug: { dates, evergreen, highPriceMessage, highPriceSpots } },
 *     tzOffset: <site UTC offset in minutes, e.g. -300 for America/New_York> }
 */
(function () {
  'use strict';

  var data = (typeof fuRefreshData !== 'undefined') ? fuRefreshData : null;
  if (!data) return;

  // ── Ordinal suffix ─────────────────────────────────────────────
  function ordinal(n) {
    if (n >= 11 && n <= 13) return 'th';
    switch (n % 10) {
      case 1: return 'st';
      case 2: return 'nd';
      case 3: return 'rd';
      default: return 'th';
    }
  }

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

  /** Today's date object with Y/M/D values in site timezone. */
  function siteToday() {
    var now     = new Date();
    var utcMs   = now.getTime() + now.getTimezoneOffset() * 60000;
    var siteNow = new Date(utcMs + data.tzOffset * 60000);
    return new Date(siteNow.getFullYear(), siteNow.getMonth(), siteNow.getDate());
  }

  function daysBetween(from, to) {
    return Math.round((to.getTime() - from.getTime()) / 86400000);
  }

  /** "Monday, June 8th" */
  function formatDate(d) {
    var day = d.getDate();
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long' }) + ' ' + day + ordinal(day);
  }

  /** "June 8th" (no weekday) */
  function formatDateShort(d) {
    var day = d.getDate();
    return d.toLocaleDateString('en-US', { month: 'long' }) + ' ' + day + ordinal(day);
  }

  // ── Mode resolution (mirrors PHP FU_Renderer::resolve_mode) ────
  function resolveMode(dates, evergreen) {
    if (evergreen) return 'ongoing';
    switch ((dates || []).length) {
      case 1:  return 'single';
      case 2:  return 'double';
      case 3:  return 'triple';
      default: return 'fixed';
    }
  }

  // ── Upcoming Mondays (mirrors PHP FU_Renderer::upcoming_mondays) ─
  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  function isoOf(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }

  function upcomingMondays(count, today) {
    var t0   = today || siteToday();
    var dow  = t0.getDay();                  // JS: Sun=0..Sat=6
    var back = (dow === 0) ? 6 : (dow - 1);  // days back to Monday
    var anchor = new Date(t0.getFullYear(), t0.getMonth(), t0.getDate() - back);
    var out = [];
    for (var i = 0; i < count; i++) {
      out.push(isoOf(new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate() + i * 7)));
    }
    return out;
  }

  // ── Per-config effective dates + lead gate (mirrors PHP shortcode resolve) ─
  function effectiveDates(cfg, today) {
    if (resolveMode(cfg.dates, cfg.evergreen) === 'ongoing') return upcomingMondays(5, today);
    return cfg.dates || [];
  }
  function maxLeadFor(cfg) {
    return resolveMode(cfg.dates, cfg.evergreen) === 'single' ? 21 : null;
  }

  // ── Slot computation (mirrors PHP renderer) ────────────────────
  function computeSlots(dateStrings, programCfg, today, maxLeadDays) {
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

    if (maxLeadDays != null && slot1T > maxLeadDays) return [];

    var slot2Date = dates[slot1Index + 1];
    if (slot2Date) {
      var slot2T = daysBetween(t0, slot2Date);
      return [slot1, { type: 'date', date: slot2Date, daysUntil: slot2T, spots: spotsForDaysUntil(slot2T) }];
    }

    if (slot1.spots.soldOut) return [slot1, { type: 'highPrice' }];
    return [slot1];
  }

  // ── Phase computation (mirrors PHP renderer) ───────────────────
  // high_demand : D1+2 ≤ today ≤ Dlast-9
  // final_week  : Dlast-8 ≤ today ≤ Dlast-2
  function computePhase(dateStrings, today) {
    if (!dateStrings || !dateStrings.length) return null;
    var t0     = today || siteToday();
    var first  = parseISODate(dateStrings[0]);
    var last   = parseISODate(dateStrings[dateStrings.length - 1]);
    var tFirst = daysBetween(t0, first);
    var tLast  = daysBetween(t0, last);
    if (tFirst <= -2 && tLast >= 9) return 'high_demand';
    if (tLast >= 2 && tLast <= 8)   return 'final_week';
    return null;
  }

  // ── Variable computation (mirrors PHP renderer) ────────────────
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

    // finaldate: the last program date as "Month Nth"
    if (programCfg.dates && programCfg.dates.length) {
      var lastDate = parseISODate(programCfg.dates[programCfg.dates.length - 1]);
      vars['finaldate'] = { visible: true, text: formatDateShort(lastDate) };
    }

    return vars;
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

  function refresh() {
    var today = siteToday();

    // fu-card blocks ([fu_card]).
    document.querySelectorAll('.fu-urgency').forEach(function (wrapper) {
      var slug = wrapper.getAttribute('data-fu-program') || Object.keys(data.programs)[0];
      var cfg  = data.programs[slug];
      if (!cfg) return;

      var slots = computeSlots(effectiveDates(cfg, today), cfg, today, maxLeadFor(cfg));
      var cards = wrapper.querySelectorAll('.fu-card');
      slots.forEach(function (slot, i) {
        if (cards[i]) { cards[i].style.display = ''; applySlotToCard(cards[i], slot, cfg); }
      });
      for (var i = slots.length; i < cards.length; i++) cards[i].style.display = 'none';
    });

    // Inline variable spans ([fu_startdate], [fu_spotsleft], [fu_finaldate]).
    document.querySelectorAll('[data-fu-var]').forEach(function (el) {
      var slug = el.getAttribute('data-fu-program') || Object.keys(data.programs)[0];
      var cfg  = data.programs[slug];
      if (!cfg) return;
      var slots = computeSlots(effectiveDates(cfg, today), cfg, today, maxLeadFor(cfg));
      var vars  = computeVars(slots, cfg);
      applyVarEl(el, vars);
    });

    // [fu_show_if_slot] wrappers.
    document.querySelectorAll('[data-fu-show-if-slot]').forEach(function (el) {
      var slug      = el.getAttribute('data-fu-program') || Object.keys(data.programs)[0];
      var cfg       = data.programs[slug];
      var slotIndex = parseInt(el.getAttribute('data-fu-show-if-slot'), 10) - 1;
      if (!cfg) return;
      var slots = computeSlots(effectiveDates(cfg, today), cfg, today, maxLeadFor(cfg));
      el.style.display = slots[slotIndex] ? '' : 'none';
    });

    // [fu_show_phase] wrappers.
    document.querySelectorAll('[data-fu-phase]').forEach(function (el) {
      var slug  = el.getAttribute('data-fu-program') || Object.keys(data.programs)[0];
      var cfg   = data.programs[slug];
      if (!cfg) return;
      var phase        = el.getAttribute('data-fu-phase');
      if (resolveMode(cfg.dates, cfg.evergreen) === 'ongoing') { el.style.display = 'none'; return; }
      var currentPhase = computePhase(cfg.dates, today);
      el.style.display = (currentPhase === phase) ? '' : 'none';
    });
  }

  // ── Live countdown ticker ──────────────────────────────────────
  function tickCountdowns() {
    var nowSec = Math.floor(Date.now() / 1000);
    document.querySelectorAll('[data-fu-countdown]').forEach(function (el) {
      var targetTs  = parseInt(el.getAttribute('data-fu-countdown'), 10);
      var remaining = Math.max(0, targetTs - nowSec);

      var days    = Math.floor(remaining / 86400);
      var hours   = Math.floor((remaining % 86400) / 3600);
      var minutes = Math.floor((remaining % 3600) / 60);
      var seconds = remaining % 60;

      function pad(n) { return String(n).padStart(2, '0'); }

      var d = el.querySelector('.fu-cd-days');
      var h = el.querySelector('.fu-cd-hours');
      var m = el.querySelector('.fu-cd-minutes');
      var s = el.querySelector('.fu-cd-seconds');
      if (d) d.textContent = pad(days);
      if (h) h.textContent = pad(hours);
      if (m) m.textContent = pad(minutes);
      if (s) s.textContent = pad(seconds);
    });
  }

  function startCountdownTicker() {
    if (!document.querySelector('[data-fu-countdown]')) return;
    tickCountdowns();
    setInterval(tickCountdowns, 1000);
  }

  // ── Schedule midnight rollover ─────────────────────────────────
  function scheduleMidnight() {
    var now  = new Date();
    var utcMs    = now.getTime() + now.getTimezoneOffset() * 60000;
    var siteNow  = new Date(utcMs + data.tzOffset * 60000);
    var siteMidnight = new Date(
      siteNow.getFullYear(), siteNow.getMonth(), siteNow.getDate() + 1,
      0, 0, 5 // 5s buffer
    );
    var siteToUTC = siteMidnight.getTime() - data.tzOffset * 60000;
    var msUntil   = siteToUTC - now.getTime();

    setTimeout(function () {
      refresh();
      scheduleMidnight();
    }, msUntil);
  }

  startCountdownTicker();
  scheduleMidnight();
})();
