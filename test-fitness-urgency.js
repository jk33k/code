/* Verifies fitness-urgency.js against the spec example.
 * Run with:  node test-fitness-urgency.js
 */
'use strict';

// Stub the browser globals the script expects, then load it.
global.window = {};
global.document = { readyState: 'complete', querySelector: function () { return null; } };
require('./fitness-urgency.js');

var FU = global.window.FitnessUrgency;

// Helper to build a slot description string matching the spec format.
function describe(slots) {
  return slots
    .map(function (s) {
      if (s.type === 'highPrice') return 'Start next Monday (2 spots left) (HIGH PRICE)';
      var date = s.date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
      var spots = s.spots.soldOut
        ? 'SOLD OUT'
        : s.spots.count + ' ' + (s.spots.count === 1 ? 'spot' : 'spots') + ' left';
      return date + ' — ' + spots + ' (T' + (s.daysUntil >= 0 ? '-' : '+') + Math.abs(s.daysUntil) + ')';
    })
    .join('\n  ');
}

var dates = ['2026-06-08', '2026-06-15', '2026-06-22', '2026-06-29'];

// Each entry: [today, expected slot tuples]
// Format of expected: [['Monday, June 8', 6, false], ['Monday, June 15', 9, false]]
//   string = formatted date or 'HIGH', number = spot count, boolean = soldOut
var EXPECTED = [
  ['2026-05-31', [['Monday, June 8', 6, false], ['Monday, June 15', 9, false]]],
  ['2026-06-01', [['Monday, June 8', 5, false], ['Monday, June 15', 9, false]]],
  ['2026-06-02', [['Monday, June 8', 4, false], ['Monday, June 15', 8, false]]],
  ['2026-06-03', [['Monday, June 8', 3, false], ['Monday, June 15', 8, false]]],
  ['2026-06-04', [['Monday, June 8', 2, false], ['Monday, June 15', 7, false]]],
  ['2026-06-05', [['Monday, June 8', 1, false], ['Monday, June 15', 7, false]]],
  ['2026-06-06', [['Monday, June 8', 1, false], ['Monday, June 15', 6, false]]],
  ['2026-06-07', [['Monday, June 8', 0, true],  ['Monday, June 15', 6, false]]],
  ['2026-06-08', [['Monday, June 8', 0, true],  ['Monday, June 15', 5, false]]],
  ['2026-06-09', [['Monday, June 8', 0, true],  ['Monday, June 15', 4, false]]],
  ['2026-06-10', [['Monday, June 15', 3, false], ['Monday, June 22', 8, false]]],
  ['2026-06-11', [['Monday, June 15', 2, false], ['Monday, June 22', 7, false]]],
  ['2026-06-12', [['Monday, June 15', 1, false], ['Monday, June 22', 7, false]]],
  ['2026-06-13', [['Monday, June 15', 1, false], ['Monday, June 22', 6, false]]],
  ['2026-06-14', [['Monday, June 15', 0, true],  ['Monday, June 22', 6, false]]],
  ['2026-06-15', [['Monday, June 15', 0, true],  ['Monday, June 22', 5, false]]],
  ['2026-06-16', [['Monday, June 15', 0, true],  ['Monday, June 22', 4, false]]],
  ['2026-06-17', [['Monday, June 22', 3, false], ['Monday, June 29', 8, false]]],
  ['2026-06-18', [['Monday, June 22', 2, false], ['Monday, June 29', 7, false]]],
  ['2026-06-19', [['Monday, June 22', 1, false], ['Monday, June 29', 7, false]]],
  ['2026-06-20', [['Monday, June 22', 1, false], ['Monday, June 29', 6, false]]],
  ['2026-06-21', [['Monday, June 22', 0, true],  ['Monday, June 29', 6, false]]],
  ['2026-06-22', [['Monday, June 22', 0, true],  ['Monday, June 29', 5, false]]],
  ['2026-06-23', [['Monday, June 22', 0, true],  ['Monday, June 29', 4, false]]],
  ['2026-06-24', [['Monday, June 29', 3, false]]],
  ['2026-06-25', [['Monday, June 29', 2, false]]],
  ['2026-06-26', [['Monday, June 29', 1, false]]],
  ['2026-06-27', [['Monday, June 29', 1, false]]],
  ['2026-06-28', [['Monday, June 29', 0, true], ['HIGH', 2, false]]],
  ['2026-06-29', [['Monday, June 29', 0, true], ['HIGH', 2, false]]],
  // Past last date: only high-price slot
  ['2026-07-01', [['HIGH', 2, false]]],
  ['2026-07-15', [['HIGH', 2, false]]],
];

var failures = 0;
EXPECTED.forEach(function (row) {
  var dayStr = row[0];
  var expected = row[1];
  var parts = dayStr.split('-');
  var today = new Date(+parts[0], +parts[1] - 1, +parts[2]);
  var slots = FU.computeSlots(today, dates);

  var ok = slots.length === expected.length;
  if (ok) {
    for (var i = 0; i < slots.length; i++) {
      var got = slots[i];
      var exp = expected[i];
      if (exp[0] === 'HIGH') {
        if (got.type !== 'highPrice') { ok = false; break; }
      } else {
        if (got.type !== 'date') { ok = false; break; }
        var fmt = got.date.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        if (fmt !== exp[0]) { ok = false; break; }
        if (exp[2] !== !!got.spots.soldOut) { ok = false; break; }
        if (!exp[2] && got.spots.count !== exp[1]) { ok = false; break; }
      }
    }
  }

  var status = ok ? 'PASS' : 'FAIL';
  if (!ok) failures++;
  console.log(status + '  ' + dayStr + '\n  ' + describe(slots));
});

console.log('\n' + (failures === 0 ? 'All ' + EXPECTED.length + ' cases pass.' : failures + ' / ' + EXPECTED.length + ' FAILED'));
process.exit(failures === 0 ? 0 : 1);
