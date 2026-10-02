import assert from 'node:assert/strict';
import { test } from 'node:test';
import { applyOps, emptyDocument } from '@maildun/email-builder';
import type { BlockInput } from '@maildun/email-builder';
import { EditorStore } from '@maildun/email-builder/editor';
import { insertBuilderMedia } from '../../resources/js/lib/email-builder.ts';
import type { MediaItem } from '../../resources/js/types/media.ts';

function media(overrides: Partial<MediaItem> = {}): MediaItem {
    return {
        uuid: 'banner',
        name: 'Newsletter banner',
        alt: 'Our team',
        url: '/storage/banner.webp',
        absolute_url: 'https://maildun-open.test/storage/banner.webp',
        mime_type: 'image/webp',
        extension: 'webp',
        size: 1024,
        size_label: '1 KB',
        width: 600,
        height: 300,
        status: 'ready',
        failed_reason: null,
        processing: false,
        created_at: null,
        category: null,
        tags: [],
        ...overrides,
    };
}

function editor(blocks: BlockInput[] = []): EditorStore {
    if (blocks.length === 0) {
        return new EditorStore(emptyDocument());
    }

    const result = applyOps(emptyDocument(), {
        op: 'insert',
        blocks,
    });
    assert.equal(result.ok, true, JSON.stringify(result.issues));

    return new EditorStore(result.document);
}

test('inserting media adds a selected image with an absolute URL and supports undo and redo', () => {
    const store = editor();
    const before = store.getState().document;

    assert.equal(insertBuilderMedia(store, media()), true);

    const { document, selectedId } = store.getState();
    assert.deepEqual(document.root, [selectedId]);
    assert.equal(document.blocks[selectedId!].type, 'image');
    assert.equal(document.blocks[selectedId!].props.src, media().absolute_url);
    assert.equal(document.blocks[selectedId!].props.alt, 'Our team');
    store.undo();
    assert.deepEqual(store.getState().document, before);
    store.redo();
    assert.deepEqual(store.getState().document, document);
});

test('inserting into a selected image replaces its source and alt while preserving layout and link', () => {
    const store = editor([
        {
            id: 'image',
            type: 'image',
            props: {
                src: 'https://example.com/old.png',
                alt: 'Old',
                href: 'https://example.com',
                width: 240,
            },
            style: { padding: 20, borderRadius: 12 },
        },
    ]);
    const before = store.getState().document;
    store.select('image');

    assert.equal(insertBuilderMedia(store, media({ alt: null })), true);

    const document = store.getState().document;
    assert.deepEqual(document.root, ['image']);
    assert.deepEqual(document.blocks.image, {
        ...before.blocks.image,
        props: {
            ...before.blocks.image.props,
            src: media().absolute_url,
            alt: 'Newsletter banner',
        },
    });
    store.undo();
    assert.deepEqual(store.getState().document, before);
});

test('inserting after a nested text block keeps the image in the same column', () => {
    const store = editor([
        {
            id: 'columns',
            type: 'columns',
            children: [
                {
                    id: 'column',
                    type: 'column',
                    children: [
                        {
                            id: 'text',
                            type: 'text',
                            props: { markdown: 'Hello' },
                        },
                        {
                            id: 'next',
                            type: 'text',
                            props: { markdown: 'Next' },
                        },
                    ],
                },
            ],
        },
    ]);
    store.select('text');

    assert.equal(insertBuilderMedia(store, media()), true);

    const { document, selectedId } = store.getState();
    assert.deepEqual(document.root, ['columns']);
    assert.deepEqual(document.blocks.column.children, [
        'text',
        selectedId,
        'next',
    ]);
});

test('inserting with a container selected appends inside it and with columns selected inserts after it', () => {
    for (const type of ['container', 'columns'] as const) {
        const store = editor([
            {
                id: 'layout',
                type,
                children:
                    type === 'columns'
                        ? [{ id: 'column', type: 'column', children: [] }]
                        : [],
            },
        ]);
        store.select('layout');

        assert.equal(insertBuilderMedia(store, media()), true);

        const { document, selectedId } = store.getState();

        if (type === 'container') {
            assert.deepEqual(document.blocks.layout.children, [selectedId]);
            assert.deepEqual(document.root, ['layout']);
        } else {
            assert.deepEqual(document.root, ['layout', selectedId]);
            assert.deepEqual(document.blocks.layout.children, ['column']);
        }
    }
});

test('processing failed missing URLs and unsafe URLs leave the document unchanged', () => {
    for (const overrides of [
        { status: 'processing', processing: true },
        { status: 'failed' },
        { absolute_url: null },
        { absolute_url: 'javascript:alert(1)' },
    ] satisfies Partial<MediaItem>[]) {
        const store = editor();
        const before = store.getState().document;

        assert.equal(insertBuilderMedia(store, media(overrides)), false);
        assert.deepEqual(store.getState().document, before);
        assert.equal(store.getState().canUndo, false);
    }
});
