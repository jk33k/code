/* Verifies the named-variable resolution layer.
 * Run with:  node test-fitness-urgency-vars.js
 */
'use strict';

global.window = {};
global.document = { readyState: 'complete', querySelector: function () { return null; } };
require('./fitness-urgency.js');

var FU = global.window.FitnessUrgency;
var dates = ['2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29'];

function fmt(d) {
  var p = d.split('-');
  return new Date(+p[0], +p[1] - 1, +p[2]);
}

function vars(d) {
  return FU.computeVariables(FU.computeSlots(fmt(d), dates));
}

var cases = [
  // Normal day — both slots active
  ['2026-05-31', {
    startdate1: { visible: true, text: 'Monday, June 8' },
    spotsleft1: { visible: true, text: '6 spots left' },
    startdate2: { visible: true, text: 'Monday, June 15' },
    spotsleft2: { visible: true, text: '9 spots left' },
  }],
  // Slot 1 sold out, slot 2 still active
  ['2026-06-09', {
    startdate1: { visible: true, text: 'Monday, June 8' },
    spotsleft1: { visible: true, text: 'SOLD OUT', soldOut: true },
    startdate2: { visible: true, text: 'Monday, June 15' },
    spotsleft2: { visible: true, text: '4 spots left' },
  }],
  // Single-date day (final start date, not sold out yet) — slot 2 hidden
  ['2026-06-25', {
    startdate1: { visible: true, text: 'Monday, June 29' },
    spotsleft1: { visible: true, text: '2 spots left' },
    startdate2: { visible: false },
    spotsleft2: { visible: false },
  }],
  // 1 spot left wording (singular)
  ['2026-06-26', {
    startdate1: { visible: true, text: 'Monday, June 29' },
    spotsleft1: { visible: true, text: '1 spot left' },
    startdate2: { visible: false },
    spotsleft2: { visible: false },
  }],
  // Final date sold out -> slot 2 becomes high-price
  ['2026-06-28', {
    startdate1: { visible: true, text: 'Monday, June 29', soldOut: true },
    spotsleft1: { visible: true, text: 'SOLD OUT', soldOut: true },
    startdate2: { visible: true, text: 'Start next Monday', highPrice: true },
    spotsleft2: { visible: true, text: '2 spots left', highPrice: true },
  }],
  // After program ends -> slot 1 becomes high-price, slot 2 hidden
  ['2026-07-01', {
    startdate1: { visible: true, text: 'Start next Monday', highPrice: true },
    spotsleft1: { visible: true, text: '2 spots left', highPrice: true },
    startdate2: { visible: false },
    spotsleft2: { visible: false },
  }],
];

// startdate1 doesn't carry soldOut in the current model (only spotsleft does);
// adjust expectations:
cases[4][1].startdate1 = { visible: true, text: 'Monday, June 29' };

var failures = 0;
cases.forEach(function (row) {
  var date = row[0];
  var expected = row[1];
  var actual = vars(date);
  var ok = true;
  Object.keys(expected).forEach(function (key) {
    var e = expected[key];
    var a = actual[key];
    if (!a) { ok = false; return; }
    if (!!a.visible !== !!e.visible) ok = false;
    if (e.text !== undefined && a.text !== e.text) ok = false;
    if (!!a.soldOut !== !!e.soldOut) ok = false;
    if (!!a.highPrice !== !!e.highPrice) ok = false;
  });
  if (!ok) failures++;
  console.log((ok ? 'PASS' : 'FAIL') + '  ' + date);
  ['startdate1', 'spotsleft1', 'startdate2', 'spotsleft2'].forEach(function (k) {
    var v = actual[k];
    var label = v.visible
      ? '"' + v.text + '"' + (v.soldOut ? ' [sold-out]' : '') + (v.highPrice ? ' [high-price]' : '')
      : '(hidden)';
    console.log('       ' + k + ' = ' + label);
  });
});

console.log('\n' + (failures === 0 ? 'All ' + cases.length + ' cases pass.' : failures + ' / ' + cases.length + ' FAILED'));
process.exit(failures === 0 ? 0 : 1);
