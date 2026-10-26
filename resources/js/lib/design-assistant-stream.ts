import type { Op } from '@maildun/email-builder';

export type DesignAssistantWarning = {
    code: string;
    severity: string;
    message: string;
    blockId?: string;
};

export type DesignAssistantEvent =
    | { type: 'text'; delta: string }
    | { type: 'tool'; name: string; detail: string | null }
    | { type: 'proposal'; ops: Op[]; warnings: DesignAssistantWarning[] }
    | { type: 'error'; message: string }
    | { type: 'done' };

export type DesignAssistantRequest = {
    prompt: string;
    messages: { role: 'user' | 'assistant'; content: string }[];
    design: unknown;
    model: string;
    context?: {
        name?: string;
        subject?: string;
        preheader?: string;
        audience?: string;
    };
};

/**
 * Splits a server-sent event stream into the assistant's typed events.
 * Chunks may end mid-event, so incomplete text is held until the next feed.
 */
export function createDesignAssistantParser(
    onEvent: (event: DesignAssistantEvent) => void,
): (chunk: string) => void {
    let buffer = '';

    return (chunk) => {
        buffer += chunk.replace(/\r\n/g, '\n');
        let boundary = buffer.indexOf('\n\n');

        while (boundary !== -1) {
            const block = buffer.slice(0, boundary);
            buffer = buffer.slice(boundary + 2);
            boundary = buffer.indexOf('\n\n');

            let name = 'message';
            const data: string[] = [];

            for (const line of block.split('\n')) {
                if (line.startsWith('event:')) {
                    name = line.slice(6).trim();
                } else if (line.startsWith('data:')) {
                    data.push(line.slice(5).trimStart());
                }
            }

            const event = toEvent(name, data.join('\n'));

            if (event) {
                onEvent(event);
            }
        }
    };
}

function toEvent(name: string, raw: string): DesignAssistantEvent | null {
    let data: Record<string, unknown>;

    try {
        data = JSON.parse(raw) as Record<string, unknown>;
    } catch {
        return null;
    }

    switch (name) {
        case 'text':
            return { type: 'text', delta: String(data.delta ?? '') };
        case 'tool':
            return {
                type: 'tool',
                name: String(data.name ?? ''),
                detail: typeof data.detail === 'string' ? data.detail : null,
            };
        case 'proposal':
            return {
                type: 'proposal',
                ops: (data.ops ?? []) as Op[],
                warnings: (data.warnings ?? []) as DesignAssistantWarning[],
            };
        case 'error':
            return { type: 'error', message: String(data.message ?? '') };
        case 'done':
            return { type: 'done' };
        default:
            return null;
    }
}

/** The CSRF token Laravel sets for same-origin `fetch` calls. */
export function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

/** A readable message for a request the server refused before streaming. */
async function refusalMessage(response: Response): Promise<string> {
    if (response.status === 429) {
        try {
            const body = (await response.clone().json()) as {
                message?: string;
            };

            if (body.message) {
                return body.message;
            }
        } catch {
            // Throttle middleware responses may not be JSON; use the default below.
        }
    }

    if (response.status === 422) {
        try {
            const body = (await response.json()) as {
                message?: string;
                errors?: Record<string, string[]>;
            };
            const first = Object.values(body.errors ?? {})[0]?.[0];

            return first ?? body.message ?? 'The request was invalid.';
        } catch {
            return 'The request was invalid.';
        }
    }

    switch (response.status) {
        case 403:
            return 'You do not have permission to edit this email.';
        case 409:
            return 'This campaign can no longer be edited.';
        case 429:
            return 'Too many requests. Wait a minute and try again.';
        default:
            return 'The assistant is unavailable right now. Try again.';
    }
}

/**
 * Sends one assistant turn and reports its events as they stream in.
 * Resolves when the stream ends; aborting the signal stops it quietly.
 */
export async function streamDesignAssistant(
    url: string,
    request: DesignAssistantRequest,
    onEvent: (event: DesignAssistantEvent) => void,
    signal?: AbortSignal,
): Promise<void> {
    const token = xsrfToken();
    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify(request),
            signal,
        });
    } catch (error) {
        if (!signal?.aborted) {
            onEvent({
                type: 'error',
                message: 'Could not reach the server. Check your connection.',
            });
        }

        throw error;
    }

    if (!response.ok || !response.body) {
        onEvent({ type: 'error', message: await refusalMessage(response) });

        return;
    }

    const feed = createDesignAssistantParser(onEvent);
    const reader = response.body
        .pipeThrough(new TextDecoderStream())
        .getReader();

    try {
        for (;;) {
            const { value, done } = await reader.read();

            if (done) {
                break;
            }

            feed(value);
        }
    } catch (error) {
        if (!signal?.aborted) {
            onEvent({
                type: 'error',
                message: 'The connection was interrupted. Try again.',
            });
        }

        throw error;
    }
}
