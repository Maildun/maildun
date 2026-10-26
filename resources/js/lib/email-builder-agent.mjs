/* global process */
import { readFileSync } from 'node:fs';
import {
    emptyDocument,
    formatIssues,
    lintDocument,
    renderEmail,
    validateDocument,
} from '@maildun/email-builder';
import {
    buildSystemPrompt,
    createAgentSession,
    outlineDocument,
    runTool,
    toMcpTools,
} from '@maildun/email-builder/agent';
import {
    fromEmailBuilderJs,
    isEmailBuilderJsDocument,
} from '@maildun/email-builder/compat';

const asObject = (value) =>
    Array.isArray(value) && value.length === 0 ? {} : value;

/**
 * Keys whose values are always objects in tool inputs: block props and style,
 * settings and theme patches, and section params.
 */
const INPUT_OBJECT_KEYS = new Set([
    'props',
    'style',
    'settings',
    'theme',
    'colors',
    'fonts',
    'params',
    'padding',
]);

/**
 * PHP decodes a tool input's `{}` as `[]` too, so restore those objects
 * wherever they appear (blocks to insert, patches, ops in a batch).
 */
function restoreEmptyInputObjects(value) {
    if (Array.isArray(value)) {
        return value.map(restoreEmptyInputObjects);
    }

    if (typeof value !== 'object' || value === null) {
        return value;
    }

    return Object.fromEntries(
        Object.entries(value).map(([key, item]) => [
            key,
            INPUT_OBJECT_KEYS.has(key)
                ? restoreEmptyInputObjects(asObject(item))
                : restoreEmptyInputObjects(item),
        ]),
    );
}

/**
 * PHP encodes an empty map as `[]`, so restore the objects the schema expects
 * before validating (a blank design arrives with `blocks: []`).
 */
function restoreEmptyObjects(document) {
    if (typeof document !== 'object' || document === null) {
        return document;
    }

    const blocks = asObject(document.blocks);

    if (typeof blocks === 'object' && blocks !== null) {
        for (const block of Object.values(blocks)) {
            if (typeof block === 'object' && block !== null) {
                block.props = asObject(block.props);

                // Image, video, and avatar alt is a string. null is what an
                // empty string becomes after PHP request normalization.
                if (
                    (block.type === 'image' ||
                        block.type === 'video' ||
                        block.type === 'avatar') &&
                    block.props?.alt === null
                ) {
                    block.props.alt = '';
                }

                if ('style' in block) {
                    block.style = asObject(block.style);
                }
            }
        }
    }

    const settings = asObject(document.settings);

    if (
        typeof settings === 'object' &&
        settings !== null &&
        'padding' in settings
    ) {
        settings.padding = asObject(settings.padding);
    }

    const theme = asObject(document.theme);

    if (typeof theme === 'object' && theme !== null) {
        for (const key of ['colors', 'fonts']) {
            if (key in theme) {
                theme[key] = asObject(theme[key]);
            }
        }
    }

    return { ...document, settings, theme, blocks };
}

try {
    const request = JSON.parse(readFileSync(0, 'utf8'));
    let document = request.document;

    if (isEmailBuilderJsDocument(document)) {
        document = fromEmailBuilderJs(document).document;
    } else {
        document = restoreEmptyObjects(document);
    }

    if (document === null) {
        document = emptyDocument();

        if (request.html?.trim()) {
            document.root = ['existing-html'];
            document.blocks['existing-html'] = {
                type: 'html',
                props: { html: request.html },
            };
        }
    }

    const validation = validateDocument(document);

    if (!validation.ok) {
        process.stdout.write(
            JSON.stringify({
                ok: false,
                result: { content: formatIssues(validation.issues) },
            }),
        );
    } else {
        const lint = {
            requireUnsubscribe: request.require_unsubscribe === true,
        };
        const session = createAgentSession(validation.document, { lint });
        const result = runTool(
            session.tools,
            request.tool,
            restoreEmptyInputObjects(request.input ?? {}),
        );

        if (!result.ok) {
            process.stdout.write(JSON.stringify({ ok: false, result }));
        } else {
            const next = session.getDocument();
            const rendered = renderEmail(next);
            const output = {
                ok: true,
                document: next,
                html: rendered.html,
                text: rendered.text,
                warnings: [
                    ...lintDocument(next, lint),
                    ...rendered.warnings.map((warning) => ({
                        severity: 'warning',
                        ...warning,
                    })),
                ],
                outline: outlineDocument(next),
                ops: session.ops,
                result,
            };

            if (request.tool === 'get_document') {
                output.tools = toMcpTools(session.tools);
                output.instructions = buildSystemPrompt({
                    mergeTags: request.merge_tags,
                });
            }

            process.stdout.write(JSON.stringify(output));
        }
    }
} catch {
    process.stdout.write(
        JSON.stringify({
            ok: false,
            error: 'Unable to process the email design.',
        }),
    );
    process.exitCode = 1;
}
