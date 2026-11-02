import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Block =
    | { type: 'heading'; level: number; text: string }
    | { type: 'paragraph'; lines: string[] }
    | { type: 'list'; ordered: boolean; start: number; items: string[] }
    | { type: 'code'; text: string };

const HEADING = /^(#{1,6})\s+(.*)$/;
const UNORDERED_ITEM = /^\s*[-*+•]\s+(.*)$/;
const ORDERED_ITEM = /^\s*(\d+)[.)]\s+(.*)$/;
const FENCE = /^\s*```/;

/**
 * Inline tokens, in priority order: code, bold, italic, links. Unclosed
 * markers (mid-stream) don't match, so they show as plain text until the
 * closing marker arrives.
 */
const INLINE =
    /(`[^`\n]+`)|(\*\*[^*\n]+?\*\*|__[^_\n]+?__)|(\*[^*\s][^*\n]*?\*|(?<![\w])_[^_\s][^_\n]*?_(?![\w]))|(\[[^\]\n]+\]\(https?:\/\/[^)\s]+\))/g;

/** Groups the reply into headings, paragraphs, lists and fenced code. */
function parseBlocks(content: string): Block[] {
    const blocks: Block[] = [];
    const lines = content.replace(/\r\n?/g, '\n').split('\n');
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];

        if (line.trim() === '') {
            index++;

            continue;
        }

        if (FENCE.test(line)) {
            const code: string[] = [];
            index++;

            while (index < lines.length && !FENCE.test(lines[index])) {
                code.push(lines[index]);
                index++;
            }

            index++;
            blocks.push({ type: 'code', text: code.join('\n') });

            continue;
        }

        const heading = line.match(HEADING);

        if (heading) {
            blocks.push({
                type: 'heading',
                level: heading[1].length,
                text: heading[2],
            });
            index++;

            continue;
        }

        const ordered = line.match(ORDERED_ITEM);

        if (ordered || UNORDERED_ITEM.test(line)) {
            const isOrdered = ordered !== null;
            const pattern = isOrdered ? ORDERED_ITEM : UNORDERED_ITEM;
            const items: string[] = [];

            while (index < lines.length) {
                const match = lines[index].match(pattern);

                if (match) {
                    items.push(match[match.length - 1]);
                } else if (
                    lines[index].trim() !== '' &&
                    /^\s+/.test(lines[index]) &&
                    items.length > 0
                ) {
                    items[items.length - 1] += ` ${lines[index].trim()}`;
                } else {
                    break;
                }

                index++;
            }

            blocks.push({
                type: 'list',
                ordered: isOrdered,
                start: ordered ? Number(ordered[1]) : 1,
                items,
            });

            continue;
        }

        const paragraph: string[] = [];

        while (
            index < lines.length &&
            lines[index].trim() !== '' &&
            !HEADING.test(lines[index]) &&
            !FENCE.test(lines[index]) &&
            !UNORDERED_ITEM.test(lines[index]) &&
            !ORDERED_ITEM.test(lines[index])
        ) {
            paragraph.push(lines[index]);
            index++;
        }

        blocks.push({ type: 'paragraph', lines: paragraph });
    }

    return blocks;
}

/**
 * Each word resolves out of a blur once, when it first arrives; positional
 * keys keep earlier words still as later chunks stream in.
 */
function animatedWords(text: string, keyPrefix: string): ReactNode[] {
    return text.split(/(\s+)/).map((part, index) =>
        part.trim() === '' ? (
            part
        ) : (
            <span
                key={`${keyPrefix}-${index}`}
                className="inline-block motion-safe:animate-word-in motion-reduce:animate-none"
            >
                {part}
            </span>
        ),
    );
}

function renderInline(text: string, keyPrefix: string): ReactNode[] {
    const nodes: ReactNode[] = [];
    let cursor = 0;
    let tokenIndex = 0;

    for (const match of text.matchAll(INLINE)) {
        const start = match.index ?? 0;
        const key = `${keyPrefix}-${tokenIndex++}`;

        if (start > cursor) {
            nodes.push(...animatedWords(text.slice(cursor, start), `${key}t`));
        }

        const [token, code, bold, italic, link] = match;

        if (code) {
            nodes.push(
                <code
                    key={key}
                    className="rounded bg-muted px-1 py-0.5 font-mono text-[0.85em]"
                >
                    {code.slice(1, -1)}
                </code>,
            );
        } else if (bold) {
            nodes.push(
                <strong key={key} className="font-semibold">
                    {renderInline(bold.slice(2, -2), key)}
                </strong>,
            );
        } else if (italic) {
            nodes.push(
                <em key={key}>{renderInline(italic.slice(1, -1), key)}</em>,
            );
        } else if (link) {
            const [, label, href] = link.match(/^\[(.+)\]\((.+)\)$/) ?? [];

            nodes.push(
                <a
                    key={key}
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="underline underline-offset-2"
                >
                    {renderInline(label ?? token, key)}
                </a>,
            );
        }

        cursor = start + token.length;
    }

    if (cursor < text.length) {
        nodes.push(...animatedWords(text.slice(cursor), `${keyPrefix}-end`));
    }

    return nodes;
}

/**
 * Renders the assistant's Markdown reply (paragraphs, lists, headings,
 * emphasis, inline code and links) as React elements — never raw HTML.
 */
export function AssistantMarkdown({
    content,
    className,
}: {
    content: string;
    className?: string;
}) {
    return (
        <div className={cn('space-y-3 break-words', className)}>
            {parseBlocks(content).map((block, blockIndex) => {
                const key = `b${blockIndex}`;

                switch (block.type) {
                    case 'heading':
                        return (
                            <p key={key} className="font-semibold">
                                {renderInline(block.text, key)}
                            </p>
                        );
                    case 'code':
                        return (
                            <pre
                                key={key}
                                className="overflow-x-auto rounded-md bg-muted px-3 py-2 font-mono text-xs"
                            >
                                {block.text}
                            </pre>
                        );
                    case 'list': {
                        const ListTag = block.ordered ? 'ol' : 'ul';

                        return (
                            <ListTag
                                key={key}
                                start={block.ordered ? block.start : undefined}
                                className={cn(
                                    'space-y-1 pl-5 marker:text-muted-foreground',
                                    block.ordered
                                        ? 'list-decimal'
                                        : 'list-disc',
                                )}
                            >
                                {block.items.map((item, itemIndex) => (
                                    <li key={`${key}-${itemIndex}`}>
                                        {renderInline(
                                            item,
                                            `${key}-${itemIndex}`,
                                        )}
                                    </li>
                                ))}
                            </ListTag>
                        );
                    }
                    default:
                        return (
                            <p key={key}>
                                {block.lines.map((line, lineIndex) => (
                                    <span key={`${key}-${lineIndex}`}>
                                        {lineIndex > 0 && <br />}
                                        {renderInline(
                                            line,
                                            `${key}-${lineIndex}`,
                                        )}
                                    </span>
                                ))}
                            </p>
                        );
                }
            })}
        </div>
    );
}
