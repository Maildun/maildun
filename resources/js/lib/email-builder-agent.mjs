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

try {
    const request = JSON.parse(readFileSync(0, 'utf8'));
    let document = request.document;

    if (isEmailBuilderJsDocument(document)) {
        document = fromEmailBuilderJs(document).document;
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
        const session = createAgentSession(validation.document);
        const result = runTool(
            session.tools,
            request.tool,
            request.input ?? {},
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
                    ...lintDocument(next),
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
