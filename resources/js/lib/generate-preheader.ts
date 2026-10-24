import { xsrfToken } from '@/lib/design-assistant-stream';
import type { EmailBuilderDocument } from '@/types/emails';

type PreheaderRequest = {
    design: EmailBuilderDocument;
    subject: string;
    model: string;
};

/**
 * Asks the assistant to write a preheader for the design on screen. Resolves
 * with it, or rejects with a message the editor shows under the field.
 */
export async function generatePreheader(
    url: string,
    request: PreheaderRequest,
): Promise<string> {
    const token = xsrfToken();
    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify(request),
        });
    } catch {
        throw new Error('Could not reach the server. Check your connection.');
    }

    let body: {
        preheader?: string;
        message?: string;
        errors?: Record<string, string[]>;
    } = {};

    try {
        body = await response.json();
    } catch {
        // Throttle and proxy errors may not be JSON; use the defaults below.
    }

    if (response.ok && body.preheader) {
        return body.preheader;
    }

    if (response.status === 403) {
        throw new Error('You do not have permission to edit this email.');
    }

    throw new Error(
        Object.values(body.errors ?? {})[0]?.[0] ??
            body.message ??
            'Could not generate a preheader. Try again.',
    );
}
