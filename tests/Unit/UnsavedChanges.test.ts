import assert from 'node:assert/strict';
import { test } from 'node:test';
import { shouldConfirmUnsavedVisit } from '../../resources/js/hooks/use-unsaved-changes.ts';

const currentUrl = 'https://maildun-open.test/team/emails/draft/edit';

test('media polling preserves unsaved edits without prompting', () => {
    assert.equal(
        shouldConfirmUnsavedVisit(
            {
                url: new URL(currentUrl),
                method: 'get',
                prefetch: false,
                only: ['mediaLibrary'],
                preserveState: true,
            },
            currentUrl,
        ),
        false,
    );
});

test('navigation full reloads and partial requests that reset state still prompt', () => {
    for (const overrides of [
        { url: new URL('https://maildun-open.test/team/emails') },
        { url: new URL(`${currentUrl}?page=2`) },
        { only: [] },
        { preserveState: false },
    ]) {
        assert.equal(
            shouldConfirmUnsavedVisit(
                {
                    url: new URL(currentUrl),
                    method: 'get',
                    prefetch: false,
                    only: ['mediaLibrary'],
                    preserveState: true,
                    ...overrides,
                },
                currentUrl,
            ),
            true,
        );
    }
});

test('prefetch and saves do not prompt to leave', () => {
    for (const overrides of [
        { prefetch: true },
        { method: 'patch' as const },
    ]) {
        assert.equal(
            shouldConfirmUnsavedVisit(
                {
                    url: new URL(currentUrl),
                    method: 'get',
                    prefetch: false,
                    only: [],
                    preserveState: false,
                    ...overrides,
                },
                currentUrl,
            ),
            false,
        );
    }
});
