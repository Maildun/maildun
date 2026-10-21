import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatAttributeDate } from '../../resources/js/lib/format.ts';

test('a value that is not a date is shown as typed instead of throwing', () => {
    assert.equal(formatAttributeDate('31.12.2026', 'en-US'), '31.12.2026');
    assert.equal(formatAttributeDate('VIP', 'en-US'), 'VIP');
});

test('a date attribute shows the same day for viewers west of UTC', () => {
    const originalTimeZone = process.env.TZ;
    process.env.TZ = 'America/New_York';

    try {
        assert.equal(
            formatAttributeDate('2026-12-31', 'en-US'),
            'Dec 31, 2026',
        );
    } finally {
        if (originalTimeZone === undefined) {
            delete process.env.TZ;
        } else {
            process.env.TZ = originalTimeZone;
        }
    }
});
