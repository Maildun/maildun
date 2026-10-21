import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatAttributeDate } from '../../resources/js/lib/format.ts';

test('a value that is not a date is shown as typed instead of throwing', () => {
    assert.equal(formatAttributeDate('31.12.2026', 'en-US'), '31.12.2026');
    assert.equal(formatAttributeDate('VIP', 'en-US'), 'VIP');
});
