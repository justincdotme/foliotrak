'use strict';

const assert = require('node:assert/strict');
const { test } = require('node:test');

const { parseH5075 } = require('./reader');

const cases = [
  {
    name: 'live capture from GVH5075_3A14',
    hex: '88ec0003704c3700',
    expected: { temperatureC: 22.5, humidity: 35.6, battery: 55 },
  },
  {
    name: 'documented example payload',
    hex: '88ec0003d90d64',
    expected: { temperatureC: 25.2, humidity: 17.3, battery: 100 },
  },
  {
    name: 'negative temperature flagged by the top bit',
    hex: '88ec00818c4c5a',
    expected: { temperatureC: -10.1, humidity: 45.2, battery: 90 },
  },
  {
    name: 'apple beacon frame from the same sensor',
    hex: '4c000215494e54454c4c495f524f434b535f48575075f2ff0c',
    expected: null,
  },
  {
    name: 'wrong company id',
    hex: '88ed0003d90d64',
    expected: null,
  },
  {
    name: 'too short to hold a reading',
    hex: '88ec0003d9',
    expected: null,
  },
];

test('parseH5075', async (t) => {
  for (const { name, hex, expected } of cases) {
    await t.test(name, () => {
      assert.deepEqual(parseH5075(Buffer.from(hex, 'hex')), expected);
    });
  }

  await t.test('missing manufacturer data', () => {
    assert.equal(parseH5075(undefined), null);
  });
});
