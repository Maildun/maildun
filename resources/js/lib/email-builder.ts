import { emptyDocument, renderEmail } from '@maildun/email-builder';
import type { EmailDocument } from '@maildun/email-builder';
import {
    fromEmailBuilderJs,
    isEmailBuilderJsDocument,
} from '@maildun/email-builder/compat';
import type { MergeTag } from '@maildun/email-builder/editor';
import type {
    EmailBuilderDocument,
    EmailEditorMode,
    EmailSourceMode,
} from '@/types/emails';

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
    return document as EmailDocument;
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
 * the new format.
 */
export function toBuilderDocument(stored: unknown): EmailBuilderDocument {
    if (isEmailBuilderJsDocument(stored)) {
        return fromEmailBuilderJs(stored).document;
    }

    return stored as EmailBuilderDocument;
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
