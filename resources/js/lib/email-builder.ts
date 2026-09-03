import { renderToStaticMarkup } from '@usewaypoint/email-builder';
import type { TReaderDocument } from '@usewaypoint/email-builder';
import type {
    EmailBuilderDocument,
    EmailEditorMode,
    EmailLayoutBlock,
    EmailSourceMode,
} from '@/types/emails';

export function isSourceEditor(
    editor: EmailEditorMode,
): editor is EmailSourceMode {
    return editor === 'plain_text' || editor === 'markdown';
}

export const ROOT_BLOCK_ID = 'root';

export const EMPTY_BUILDER_DOCUMENT: EmailBuilderDocument = {
    [ROOT_BLOCK_ID]: {
        type: 'EmailLayout',
        data: {
            backdropColor: '#F5F5F5',
            canvasColor: '#FFFFFF',
            textColor: '#262626',
            fontFamily: 'MODERN_SANS',
            childrenIds: [],
        },
    },
};

/**
 * The reader's block union is inferred from zod schemas, so the document is
 * handed over as-is at the single point where it crosses into the library.
 */
export function toReaderDocument(
    document: EmailBuilderDocument,
): TReaderDocument {
    return document as unknown as TReaderDocument;
}

/**
 * Render a document to the email-safe HTML that gets stored and sent.
 *
 * This pulls in react-dom/server, so only ever call it from an event handler —
 * never during a render pass, which would nest one renderer inside another.
 */
export function renderBuilderHtml(document: EmailBuilderDocument): string {
    return renderToStaticMarkup(toReaderDocument(document), {
        rootBlockId: ROOT_BLOCK_ID,
    });
}

export function emptyBuilderDocument(): EmailBuilderDocument {
    return structuredClone(EMPTY_BUILDER_DOCUMENT);
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
        [ROOT_BLOCK_ID]: {
            type: 'EmailLayout',
            data: {
                ...(document[ROOT_BLOCK_ID] as EmailLayoutBlock).data,
                childrenIds: ['block-0'],
            },
        },
        'block-0': {
            type: 'Html',
            data: { props: { contents: html } },
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

export function sourceToBuilderDocument(
    source: string,
    editor: EmailSourceMode,
): EmailBuilderDocument {
    const document = emptyBuilderDocument();

    return {
        ...document,
        [ROOT_BLOCK_ID]: {
            type: 'EmailLayout',
            data: {
                ...(document[ROOT_BLOCK_ID] as EmailLayoutBlock).data,
                childrenIds: ['source'],
            },
        },
        source:
            editor === 'markdown'
                ? {
                      type: 'Text',
                      data: {
                          style: {
                              padding: {
                                  top: 24,
                                  right: 24,
                                  bottom: 24,
                                  left: 24,
                              },
                          },
                          props: { text: source, markdown: true },
                      },
                  }
                : {
                      type: 'Html',
                      data: {
                          props: {
                              contents: `<div style="padding:24px;line-height:1.6">${escapeHtml(source).replaceAll('\r\n', '\n').replaceAll('\r', '\n').replaceAll('\n', '<br>')}</div>`,
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
    const root = document[ROOT_BLOCK_ID];

    if (root && root.type === 'EmailLayout') {
        return root.data.childrenIds ?? [];
    }

    return [];
}
