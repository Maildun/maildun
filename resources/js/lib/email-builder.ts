import {
    canContain,
    emptyDocument,
    findParent,
    hasChildren,
    renderEmail,
} from '@maildun/email-builder';
import type { BlockInput, EmailDocument, Op } from '@maildun/email-builder';
import {
    fromEmailBuilderJs,
    isEmailBuilderJsDocument,
} from '@maildun/email-builder/compat';
import type { EditorStore, MergeTag } from '@maildun/email-builder/editor';
import type {
    EmailBuilderDocument,
    EmailEditorMode,
    EmailSourceMode,
} from '@/types/emails';
import type { MediaItem } from '@/types/media';
import type {
    TeamBrandColor,
    TeamBrandFont,
    TeamBrandTheme,
} from '@/types/teams';

/** A block the author saved to reuse in any block email in the workspace. */
export type SavedSection = {
    id: string;
    name: string;
    /** Stored without ids, so every insert gets fresh ones. */
    blocks: BlockInput[];
};

/** Email-safe hex for each workspace brand color (the app theme uses OKLCH). */
const BRAND_EMAIL_COLORS: Record<TeamBrandColor, string> = {
    blue: '#2b7fff',
    indigo: '#4f39f6',
    violet: '#7f22fe',
    purple: '#9810fa',
    fuchsia: '#c800de',
    pink: '#e60076',
    rose: '#ec003f',
    red: '#e7000b',
    orange: '#f54900',
    amber: '#e17100',
    lime: '#5ea500',
    emerald: '#009966',
    teal: '#009689',
    cyan: '#0092b8',
    neutral: '#262626',
};

/** The closest email-safe font stack in the builder for each workspace font. */
const BRAND_EMAIL_FONTS: Record<TeamBrandFont, string> = {
    inter: 'MODERN_SANS',
    'instrument-sans': 'GEOMETRIC_SANS',
    'system-sans': 'MODERN_SANS',
    'rounded-sans': 'ROUNDED_SANS',
    'humanist-sans': 'BOOK_SANS',
    serif: 'MODERN_SERIF',
    georgia: 'BOOK_SERIF',
    mono: 'MONOSPACE',
};

/**
 * One undoable editor step that switches a design to the workspace brand
 * (Settings → Theme): its color for buttons and links, and its font.
 */
export function brandThemeOp(theme: TeamBrandTheme): Op {
    const color = BRAND_EMAIL_COLORS[theme.color] ?? BRAND_EMAIL_COLORS.blue;
    const font = BRAND_EMAIL_FONTS[theme.font] ?? BRAND_EMAIL_FONTS.inter;

    return {
        op: 'updateTheme',
        colors: { primary: color, link: color },
        fonts: { body: font, heading: font },
    };
}

export function isSourceEditor(
    editor: EmailEditorMode,
): editor is EmailSourceMode {
    return editor === 'plain_text' || editor === 'markdown';
}

/**
 * Merge tags offered in the block editor's link fields and text toolbar.
 * Audience attributes are added per campaign by the caller.
 */
export const BUILDER_MERGE_TAGS: MergeTag[] = [
    { key: 'first_name', label: 'First name' },
    { key: 'last_name', label: 'Last name' },
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'web_view_url', label: 'View in browser link' },
    { key: 'unsubscribe_url', label: 'Unsubscribe link' },
    { key: 'subscribe_url', label: 'Subscribe link' },
];

/**
 * The app types blocks loosely (see EmailBuilderDocument), so the document is
 * handed over as-is at the point where it crosses into the package.
 */
export function toEmailDocument(document: EmailBuilderDocument): EmailDocument {
    return withEmptyObjects(document) as EmailDocument;
}

const repairedDocuments = new WeakMap<object, EmailBuilderDocument>();

function isEmptyArray(value: unknown): boolean {
    return Array.isArray(value) && value.length === 0;
}

const ALT_BLOCK_TYPES = new Set(['image', 'video', 'avatar']);

/** Those blocks require alt to be a string. A missing description is blank. */
function hasNullAlt(
    block: Record<string, unknown> | null | undefined,
): boolean {
    if (!block || !ALT_BLOCK_TYPES.has(String(block.type))) {
        return false;
    }

    const props = block.props;

    return (
        typeof props === 'object' &&
        props !== null &&
        !Array.isArray(props) &&
        (props as { alt?: unknown }).alt === null
    );
}

/**
 * PHP decodes an empty JSON object as an empty array, so a saved design can
 * come back with `props: []` (e.g. a container) or `blocks: []`. The package
 * validates strictly and would reject every edit on such a document, so
 * restore those objects. Repaired documents are cached per input so the
 * editor keeps receiving the same reference between renders.
 */
function withEmptyObjects(
    document: EmailBuilderDocument,
): EmailBuilderDocument {
    const cached = repairedDocuments.get(document);

    if (cached) {
        return cached;
    }

    const blocks = document.blocks as unknown;
    const entries = isEmptyArray(blocks)
        ? []
        : Object.entries(
              (blocks ?? {}) as Record<string, Record<string, unknown>>,
          );
    const needsRepair =
        isEmptyArray(blocks) ||
        entries.some(
            ([, block]) =>
                isEmptyArray(block?.props) ||
                isEmptyArray(block?.style) ||
                hasNullAlt(block),
        );

    if (!needsRepair) {
        return document;
    }

    const repaired = {
        ...document,
        blocks: Object.fromEntries(
            entries.map(([id, block]) => [
                id,
                {
                    ...block,
                    ...(isEmptyArray(block.props) ? { props: {} } : {}),
                    ...(hasNullAlt(block)
                        ? {
                              props: {
                                  ...(block.props as Record<string, unknown>),
                                  alt: '',
                              },
                          }
                        : {}),
                    ...(isEmptyArray(block.style) ? { style: {} } : {}),
                },
            ]),
        ),
    } as EmailBuilderDocument;

    repairedDocuments.set(document, repaired);

    return repaired;
}

/**
 * Matches EmailTemplate::builderDesign() so blank and starter designs share
 * one neutral theme.
 */
function withMaildunDefaults(
    document: EmailBuilderDocument,
): EmailBuilderDocument {
    return {
        ...document,
        theme: {
            ...document.theme,
            colors: {
                ...document.theme.colors,
                text: '#262626',
                muted: '#737373',
                background: '#f5f5f5',
                surface: '#ffffff',
                border: '#e5e5e5',
            },
        },
    };
}

export const EMPTY_BUILDER_DOCUMENT: EmailBuilderDocument =
    withMaildunDefaults(emptyDocument());

export function emptyBuilderDocument(): EmailBuilderDocument {
    return structuredClone(EMPTY_BUILDER_DOCUMENT);
}

/**
 * Stored designs may still be EmailBuilder.js documents (written before the
 * switch, or by an MCP client). Convert them on open; the next save stores
 * the new format. Designs saved without version, settings or theme (an MCP
 * client sending only root and blocks) get the blank defaults, because the
 * renderer reads the theme before anything else.
 */
export function toBuilderDocument(stored: unknown): EmailBuilderDocument {
    if (isEmailBuilderJsDocument(stored)) {
        return fromEmailBuilderJs(stored).document;
    }

    const document = stored as Partial<EmailBuilderDocument>;
    const defaults = emptyBuilderDocument();

    return {
        ...defaults,
        ...document,
        settings: { ...defaults.settings, ...document.settings },
        theme: {
            colors: { ...defaults.theme.colors, ...document.theme?.colors },
            fonts: { ...defaults.theme.fonts, ...document.theme?.fonts },
        },
        root: document.root ?? defaults.root,
        blocks: document.blocks ?? defaults.blocks,
    };
}

/**
 * Render a document to the email-safe HTML that gets stored and sent. The
 * renderer is pure TypeScript, so this is safe to call during render.
 */
export function renderBuilderHtml(document: EmailBuilderDocument): string {
    return renderEmail(toEmailDocument(document)).html;
}

/**
 * Move existing markup into the block editor without losing it: the whole body
 * becomes one raw HTML block the author can then break apart.
 */
export function htmlToBuilderDocument(html: string): EmailBuilderDocument {
    const document = emptyBuilderDocument();

    if (html.trim() === '') {
        return document;
    }

    return {
        ...document,
        root: ['block-0'],
        blocks: {
            'block-0': { type: 'html', props: { html } },
        },
    };
}

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

/**
 * Markdown goes through the EmailBuilder.js importer so tables, images and
 * inline HTML (which the restricted text block escapes) become an HTML block.
 */
export function sourceToBuilderDocument(
    source: string,
    editor: EmailSourceMode,
): EmailBuilderDocument {
    if (editor === 'markdown') {
        const { document } = fromEmailBuilderJs({
            root: {
                type: 'EmailLayout',
                data: { childrenIds: source.trim() === '' ? [] : ['source'] },
            },
            source: {
                type: 'Text',
                data: {
                    style: {
                        padding: { top: 24, right: 24, bottom: 24, left: 24 },
                    },
                    props: { text: source, markdown: true },
                },
            },
        });

        return withMaildunDefaults(document);
    }

    return {
        ...emptyBuilderDocument(),
        root: ['source'],
        blocks: {
            source: {
                type: 'html',
                props: {
                    html: `<div style="padding:24px;line-height:1.6">${escapeHtml(source).replaceAll('\r\n', '\n').replaceAll('\r', '\n').replaceAll('\n', '<br>')}</div>`,
                },
            },
        },
    };
}

export function renderSourceHtml(
    source: string,
    editor: EmailSourceMode,
): string {
    return renderBuilderHtml(sourceToBuilderDocument(source, editor));
}

export function getChildrenIds(document: EmailBuilderDocument): string[] {
    return document.root ?? [];
}

export function insertBuilderMedia(
    store: EditorStore,
    image: MediaItem,
): boolean {
    if (image.status !== 'ready' || !image.absolute_url) {
        return false;
    }

    const { document, selectedId } = store.getState();
    const selected = selectedId ? document.blocks[selectedId] : undefined;
    const props = { src: image.absolute_url, alt: image.alt ?? image.name };

    if (selectedId && selected?.type === 'image') {
        return store.apply({ op: 'update', id: selectedId, props }).ok;
    }

    let parentId = 'root';
    let index = document.root.length;

    if (selectedId && selected) {
        if (hasChildren(selected) && canContain(selected.type, 'image')) {
            parentId = selectedId;
            index = selected.children.length;
        } else {
            const parent = findParent(document, selectedId);
            const parentType =
                parent?.parentId === 'root'
                    ? 'root'
                    : document.blocks[parent?.parentId ?? '']?.type;

            if (parent && parentType && canContain(parentType, 'image')) {
                parentId = parent.parentId;
                index = parent.index + 1;
            }
        }
    }

    const result = store.apply({
        op: 'insert',
        parentId,
        index,
        blocks: [{ type: 'image', props }],
    });

    if (result.ok && result.inserted[0]) {
        store.select(result.inserted[0]);
    }

    return result.ok;
}
