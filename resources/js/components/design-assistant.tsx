import {
    AiChat02Icon,
    Cancel01Icon,
    ChatGptIcon,
    ClaudeIcon,
    GoogleGeminiIcon,
    SendHorizontalIcon,
    StopIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { EmailEditorHandle } from '@maildun/email-builder/editor';
import type { RefObject } from 'react';
import { useEffect, useRef, useState } from 'react';
import { DesignAssistantActivity } from '@/components/design-assistant-activity';
import type { AssistantStep } from '@/components/design-assistant-activity';
import { DesignAssistantIcon } from '@/components/design-assistant-icon';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { MODEL_STORAGE_KEY, readStoredModel } from '@/lib/assistant-model';
import { streamDesignAssistant } from '@/lib/design-assistant-stream';
import type { DesignAssistantWarning } from '@/lib/design-assistant-stream';
import { cn } from '@/lib/utils';

export type AssistantModel = {
    id: string;
    label: string;
    model: string;
    provider: string;
};

/** Provider marks for the model picker; unknown providers get the assistant mark. */
const PROVIDER_ICONS: Record<string, typeof AiChat02Icon> = {
    openai: ChatGptIcon,
    anthropic: ClaudeIcon,
    gemini: GoogleGeminiIcon,
};

function ProviderIcon({ provider }: { provider: string | undefined }) {
    return (
        <HugeiconsIcon
            icon={(provider && PROVIDER_ICONS[provider]) || AiChat02Icon}
            className="size-4 shrink-0"
            aria-hidden="true"
        />
    );
}

type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    activity: AssistantStep[];
    warnings: DesignAssistantWarning[];
    error?: string;
    proposed?: boolean;
};

/** Fields the author already set on a campaign draft. */
export type CampaignBrief = {
    name: string;
    subject: string;
    preheader: string;
    audience: string | null;
};

type Props = {
    url: string;
    models: AssistantModel[];
    editor: RefObject<EmailEditorHandle | null>;
    open: boolean;
    onClose: () => void;
    /** Notifies the editor toolbar while a turn is running. */
    onStreamingChange?: (streaming: boolean) => void;
    /** Present on the campaign editor, so the assistant writes to that draft. */
    campaign?: CampaignBrief;
};

const STARTERS = [
    'Create a product launch email with a hero, three features and a footer',
    'Write a friendly monthly newsletter with two article sections',
    'Make the design more compact and tighten the copy',
];

const REVEAL =
    'motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-1 motion-safe:fill-mode-both motion-safe:duration-500 motion-reduce:animate-none';

function clip(value: string, max = 72): string {
    const trimmed = value.trim().replace(/\s+/g, ' ');

    return trimmed.length > max
        ? `${trimmed.slice(0, max - 1).trimEnd()}…`
        : trimmed;
}

/** Starters that name the campaign the author is already composing. */
function startersFor(campaign: CampaignBrief | undefined): string[] {
    if (!campaign) {
        return STARTERS;
    }

    const subject = clip(campaign.subject);
    const audience = clip(campaign.audience ?? '', 48);
    const prompts = [
        subject !== '' ? `Design this email for "${subject}"` : null,
        audience !== ''
            ? `Write it for ${audience} with a clear call to action`
            : null,
        'Make the layout more compact and tighten the copy',
    ].filter((prompt): prompt is string => prompt !== null);

    return prompts.length > 1 ? prompts : STARTERS;
}

/**
 * Campaign fields worth sending. An empty object would arrive in PHP as an
 * array, so omit the key until there is something to say.
 */
function campaignContext(
    campaign: CampaignBrief | undefined,
):
    | { name?: string; subject?: string; preheader?: string; audience?: string }
    | undefined {
    if (!campaign) {
        return undefined;
    }

    const context: {
        name?: string;
        subject?: string;
        preheader?: string;
        audience?: string;
    } = {};
    const name = campaign.name.trim();
    const subject = campaign.subject.trim();
    const preheader = campaign.preheader.trim();
    const audience = campaign.audience?.trim() ?? '';

    if (name !== '') {
        context.name = name;
    }

    if (subject !== '') {
        context.subject = subject;
    }

    if (preheader !== '') {
        context.preheader = preheader;
    }

    if (audience !== '') {
        context.audience = audience;
    }

    return Object.keys(context).length > 0 ? context : undefined;
}

/**
 * Assistant copy resolves word by word out of a blur as it streams in. Each
 * word animates once, when it first arrives; later chunks leave earlier words
 * alone because their keys stay put.
 */
function AssistantReply({ content }: { content: string }) {
    const parts = content.split(/(\s+)/);

    return (
        <p className="whitespace-pre-wrap">
            {parts.map((part, index) =>
                part.trim() === '' ? (
                    part
                ) : (
                    <span
                        key={index}
                        className="inline-block motion-safe:animate-word-in motion-reduce:animate-none"
                    >
                        {part}
                    </span>
                ),
            )}
        </p>
    );
}

/** The send or stop icon: the shown one slides in, the hidden one slides out (or just stays hidden before any swap). */
function composerIcon(shown: boolean, swapped: boolean): string {
    if (shown) {
        return cn(swapped && 'animate-icon-in motion-reduce:animate-none');
    }

    return cn(
        'opacity-0',
        swapped && 'animate-icon-out motion-reduce:animate-none',
    );
}

/** A one-line label for the editor's proposal bar, from the assistant's reply. */
function proposalSummary(reply: string): string {
    const sentence = reply.trim().split(/(?<=[.!?])\s/)[0] ?? '';

    if (sentence === '') {
        return 'Assistant changes';
    }

    return sentence.length > 60
        ? `${sentence.slice(0, 57).trimEnd()}…`
        : sentence;
}

/**
 * The design assistant chat, docked beside the block editor. Each turn sends
 * the editor's visible design; the reply comes back as a proposal the author
 * reviews in the editor. Nothing is saved until the author saves the email.
 */
export function DesignAssistant({
    url,
    models,
    editor,
    open,
    onClose,
    onStreamingChange,
    campaign,
}: Props) {
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [prompt, setPrompt] = useState('');
    const [model, setModel] = useState(() => readStoredModel(models));
    const [streaming, setStreaming] = useState(false);
    // The send/stop icon stays still on first render and animates from the first swap on.
    const [iconSwapped, setIconSwapped] = useState(false);
    const abort = useRef<AbortController | null>(null);
    const nextId = useRef(1);
    const scroller = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLTextAreaElement>(null);
    const onStreamingChangeRef = useRef(onStreamingChange);
    const starters = startersFor(campaign);

    useEffect(() => {
        onStreamingChangeRef.current = onStreamingChange;
    }, [onStreamingChange]);

    useEffect(() => {
        scroller.current?.scrollTo({ top: scroller.current.scrollHeight });
    }, [messages]);

    useEffect(() => {
        if (open) {
            input.current?.focus();
        }
    }, [open]);

    useEffect(
        () => () => {
            abort.current?.abort();
            onStreamingChangeRef.current?.(false);
        },
        [],
    );

    const chooseModel = (value: string | null) => {
        if (!value) {
            return;
        }

        setModel(value);

        try {
            window.localStorage.setItem(MODEL_STORAGE_KEY, value);
        } catch {
            // Remembering the choice is a convenience only.
        }
    };

    const updateMessage = (
        id: number,
        update: (message: ChatMessage) => ChatMessage,
    ) =>
        setMessages((current) =>
            current.map((message) =>
                message.id === id ? update(message) : message,
            ),
        );

    const send = async (text: string) => {
        const handle = editor.current;
        const content = text.trim();

        if (!handle || content === '' || streaming || model === '') {
            return;
        }

        const history = messages
            .filter((message) => !message.error && message.content !== '')
            .map(({ role, content }) => ({ role, content }));
        const userId = nextId.current++;
        const replyId = nextId.current++;
        const controller = new AbortController();
        abort.current = controller;

        setMessages((current) => [
            ...current,
            {
                id: userId,
                role: 'user',
                content,
                activity: [],
                warnings: [],
            },
            {
                id: replyId,
                role: 'assistant',
                content: '',
                activity: [],
                warnings: [],
            },
        ]);
        setPrompt('');
        setStreaming(true);
        setIconSwapped(true);
        onStreamingChangeRef.current?.(true);

        let reply = '';
        let finished = false;

        try {
            await streamDesignAssistant(
                url,
                {
                    prompt: content,
                    messages: history,
                    design: handle.store.visibleDocument(),
                    model,
                    context: campaignContext(campaign),
                },
                (event) => {
                    switch (event.type) {
                        case 'text':
                            reply += event.delta;
                            updateMessage(replyId, (message) => ({
                                ...message,
                                content: reply,
                            }));
                            break;
                        case 'tool':
                            updateMessage(replyId, (message) => ({
                                ...message,
                                activity: [
                                    ...message.activity,
                                    { name: event.name, detail: event.detail },
                                ],
                            }));
                            break;
                        case 'proposal': {
                            const result = handle.propose(
                                event.ops,
                                proposalSummary(reply),
                            );

                            updateMessage(replyId, (message) =>
                                result.ok
                                    ? {
                                          ...message,
                                          proposed: true,
                                          warnings: event.warnings,
                                      }
                                    : {
                                          ...message,
                                          warnings: event.warnings,
                                          error: 'The design changed while I was working, so I could not apply my changes. Ask again.',
                                      },
                            );
                            break;
                        }
                        case 'error':
                            finished = true;
                            updateMessage(replyId, (message) => ({
                                ...message,
                                error: event.message,
                            }));
                            break;
                        case 'done':
                            finished = true;
                            break;
                    }
                },
                controller.signal,
            );

            if (!finished && !controller.signal.aborted) {
                updateMessage(replyId, (message) => ({
                    ...message,
                    error: 'The assistant stopped before finishing. Try again.',
                }));
            }
        } catch {
            // Network failures were already reported as an error event.
        } finally {
            setStreaming(false);
            onStreamingChangeRef.current?.(false);
            abort.current = null;
        }
    };

    return (
        <aside
            aria-label="Design assistant"
            data-test="design-assistant"
            className={cn(
                'flex w-full shrink-0 flex-col border-l bg-background sm:w-96',
                !open && 'hidden',
            )}
        >
            <header className="flex h-12 shrink-0 items-center justify-between gap-2 border-b px-4">
                <h2 className="flex items-center gap-2 text-sm font-medium">
                    <DesignAssistantIcon
                        loading={streaming}
                        className="size-4"
                    />
                    Design assistant
                </h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Close design assistant"
                    onClick={onClose}
                >
                    <HugeiconsIcon icon={Cancel01Icon} />
                </Button>
            </header>

            <div
                ref={scroller}
                className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4"
            >
                {messages.length === 0 ? (
                    <div className="flex flex-col gap-3">
                        <p
                            className={cn(
                                'text-sm text-muted-foreground',
                                REVEAL,
                            )}
                        >
                            Describe the email you want, or ask for changes to
                            this one. You'll review every change before it's
                            applied.
                        </p>
                        {starters.map((starter, index) => (
                            <Button
                                key={starter}
                                type="button"
                                variant="outline"
                                style={{ animationDelay: `${index * 70}ms` }}
                                className={cn(
                                    'h-auto justify-start px-3 py-2 text-left whitespace-normal',
                                    REVEAL,
                                )}
                                onClick={() => void send(starter)}
                            >
                                {starter}
                            </Button>
                        ))}
                    </div>
                ) : (
                    messages.map((message) => (
                        <div
                            key={message.id}
                            className={cn(
                                'flex flex-col gap-1.5 text-sm',
                                message.role === 'user' && 'items-end',
                            )}
                        >
                            {message.role === 'user' ? (
                                <p
                                    className={cn(
                                        'max-w-[85%] rounded-lg bg-muted px-3 py-2 whitespace-pre-wrap',
                                        REVEAL,
                                    )}
                                >
                                    {message.content}
                                </p>
                            ) : (
                                <>
                                    <DesignAssistantActivity
                                        steps={message.activity}
                                        outcome={
                                            streaming &&
                                            message.id === messages.at(-1)?.id
                                                ? 'working'
                                                : message.error
                                                  ? 'failed'
                                                  : message.proposed
                                                    ? 'proposed'
                                                    : 'answered'
                                        }
                                    />
                                    {message.content !== '' && (
                                        <AssistantReply
                                            content={message.content}
                                        />
                                    )}
                                    {message.proposed && (
                                        <p
                                            className={cn(
                                                'text-xs text-muted-foreground',
                                                REVEAL,
                                            )}
                                        >
                                            Review the changes in the editor,
                                            then accept or reject them.
                                        </p>
                                    )}
                                    {message.warnings.length > 0 && (
                                        <ul className="flex flex-col gap-1">
                                            {message.warnings
                                                .slice(0, 3)
                                                .map((warning, index) => (
                                                    <li
                                                        key={`${warning.code}-${warning.blockId ?? index}`}
                                                        className={cn(
                                                            'text-xs text-muted-foreground',
                                                            REVEAL,
                                                        )}
                                                    >
                                                        {warning.message}
                                                    </li>
                                                ))}
                                        </ul>
                                    )}
                                    {message.error && (
                                        <p
                                            className={cn(
                                                'text-destructive',
                                                REVEAL,
                                            )}
                                        >
                                            {message.error}
                                        </p>
                                    )}
                                </>
                            )}
                        </div>
                    ))
                )}
            </div>

            {/* Not a <form>: the editor pages wrap the design view in their own
                save form, and a nested form submits the page instead. */}
            <div className="shrink-0 p-3">
                <div className="flex flex-col gap-1 rounded-2xl border bg-background p-2 shadow-sm transition-[color,box-shadow] focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/20 dark:bg-input/30">
                    <Textarea
                        ref={input}
                        value={prompt}
                        placeholder="Ask for a new section, a tone change, brand colors…"
                        aria-label="Message the design assistant"
                        rows={2}
                        className="max-h-40 min-h-14 resize-none border-0 bg-transparent px-2 py-1.5 shadow-none focus-visible:border-0 focus-visible:ring-0 dark:bg-transparent"
                        onChange={(event) => setPrompt(event.target.value)}
                        onKeyDown={(event) => {
                            if (
                                event.key === 'Enter' &&
                                !event.shiftKey &&
                                !event.nativeEvent.isComposing
                            ) {
                                event.preventDefault();
                                void send(prompt);
                            }
                        }}
                    />
                    <div className="flex items-center justify-end gap-1">
                        {models.length === 1 ? (
                            <p
                                data-test="design-assistant-model"
                                title={models[0].model}
                                className="flex min-w-0 items-center gap-1.5 px-2 text-sm text-muted-foreground"
                            >
                                <ProviderIcon provider={models[0].provider} />
                                <span className="truncate">
                                    {models[0].label}
                                </span>
                            </p>
                        ) : (
                            <Select
                                value={model}
                                onValueChange={chooseModel}
                                disabled={streaming}
                                items={models.map((option) => ({
                                    value: option.id,
                                    label: option.label,
                                }))}
                            >
                                <SelectTrigger
                                    size="sm"
                                    aria-label="Model"
                                    data-test="design-assistant-model"
                                    className="min-w-0 border-0 bg-transparent text-muted-foreground shadow-none hover:bg-muted hover:text-foreground dark:bg-transparent dark:hover:bg-muted"
                                >
                                    <SelectValue>
                                        {(value: string) => {
                                            const selected = models.find(
                                                (option) => option.id === value,
                                            );

                                            return (
                                                <>
                                                    <ProviderIcon
                                                        provider={
                                                            selected?.provider
                                                        }
                                                    />
                                                    {selected?.label}
                                                </>
                                            );
                                        }}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent
                                    side="top"
                                    align="end"
                                    alignItemWithTrigger={false}
                                    className="w-max"
                                >
                                    <SelectGroup>
                                        {models.map((option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={option.id}
                                            >
                                                <ProviderIcon
                                                    provider={option.provider}
                                                />
                                                <span>{option.label}</span>
                                                <span className="ml-auto pl-4 text-xs text-muted-foreground">
                                                    {option.model}
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        )}
                        {/* One button that turns from send into stop; only the icon moves (see --animate-icon-in). */}
                        <Button
                            type="button"
                            size="icon-sm"
                            variant={streaming ? 'secondary' : 'default'}
                            className="grid place-items-center overflow-hidden rounded-full [&>*]:col-start-1 [&>*]:row-start-1"
                            aria-label={
                                streaming
                                    ? 'Stop the assistant'
                                    : 'Send message'
                            }
                            data-test="design-assistant-send"
                            disabled={!streaming && prompt.trim() === ''}
                            onClick={() =>
                                streaming
                                    ? abort.current?.abort()
                                    : void send(prompt)
                            }
                        >
                            <HugeiconsIcon
                                icon={SendHorizontalIcon}
                                aria-hidden
                                className={composerIcon(
                                    !streaming,
                                    iconSwapped,
                                )}
                            />
                            <HugeiconsIcon
                                icon={StopIcon}
                                aria-hidden
                                className={composerIcon(streaming, iconSwapped)}
                            />
                        </Button>
                    </div>
                </div>
            </div>
        </aside>
    );
}
