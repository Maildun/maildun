import assert from 'node:assert/strict';
import { test } from 'node:test';
import { applyOps, createDocument } from '@maildun/email-builder';
import {
    renderBuilderHtml,
    toBuilderDocument,
    toEmailDocument,
} from '../../resources/js/lib/email-builder.ts';
import type { EmailBuilderDocument } from '../../resources/js/types/emails.ts';

/** A design as Laravel returns it: empty objects decoded by PHP as arrays. */
function savedByPhp(): EmailBuilderDocument {
    const design = JSON.parse(
        JSON.stringify(
            createDocument({
                blocks: [
                    { id: 'intro', type: 'text', props: { markdown: 'Hi' } },
                    { id: 'footer', type: 'container', children: [] },
                ],
            }),
        ),
    );
    design.blocks.footer.props = [];

    return design as EmailBuilderDocument;
}

test('restores empty objects so edits apply to designs saved by PHP', () => {
    const document = toEmailDocument(savedByPhp());

    assert.deepEqual(document.blocks.footer?.props, {});
    assert.equal(
        applyOps(document, [
            { op: 'update', id: 'intro', props: { markdown: 'Hello there' } },
        ]).ok,
        true,
    );
});

test('treats a null image alt as blank text', () => {
    const design = createDocument({
        blocks: [
            {
                id: 'photo',
                type: 'image',
                props: { src: 'https://example.com/photo.png' },
            },
        ],
    });
    design.blocks.photo.props.alt = null;

    const document = toEmailDocument(design as EmailBuilderDocument);

    assert.equal(document.blocks.photo?.props?.alt, '');
    assert.match(
        renderBuilderHtml(design as EmailBuilderDocument),
        /photo\.png/,
    );
});

test('opens a design saved with only its root and blocks', () => {
    const document = toBuilderDocument({
        root: ['intro'],
        blocks: {
            intro: { type: 'text', props: { markdown: 'Hello there' } },
        },
    });

    assert.equal(document.theme.colors.surface, '#ffffff');
    assert.match(renderBuilderHtml(document), /Hello there/);
});

test('keeps the same reference between renders', () => {
    const saved = savedByPhp();
    const clean = toEmailDocument(saved);

    assert.equal(toEmailDocument(saved), clean);
    assert.equal(
        toEmailDocument(clean as unknown as EmailBuilderDocument),
        clean,
    );
});
