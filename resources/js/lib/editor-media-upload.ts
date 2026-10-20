import { router } from '@inertiajs/react';
import type { ImageResult } from '@maildun/email-builder/editor';
import { MEDIA_MAX_BYTES } from '@/hooks/use-media-upload';
import { show, store } from '@/routes/media/editor_uploads';
import type { MediaItem } from '@/types/media';

const UPLOADABLE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const POLL_INTERVAL = 1000;
/** How long the editor waits for a queued WebP conversion before giving up. */
const POLL_LIMIT = 45;

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

/** "summer-sale_hero.png" → "Summer sale hero". */
function altFromName(name: string): string {
    const words = name
        .replace(/\.[a-z0-9]+$/i, '')
        .replace(/[-_]+/g, ' ')
        .trim();

    return words === '' ? '' : words[0].toUpperCase() + words.slice(1);
}

async function request(
    url: string,
    init: RequestInit = {},
): Promise<MediaItem> {
    const token = xsrfToken();
    const response = await fetch(url, {
        ...init,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        credentials: 'same-origin',
    });
    const body = (await response.json().catch(() => null)) as {
        media?: MediaItem;
        message?: string;
        errors?: Record<string, string[]>;
    } | null;

    if (!response.ok || !body?.media) {
        const firstError = Object.values(body?.errors ?? {})[0]?.[0];

        throw new Error(
            firstError ?? body?.message ?? 'The image could not be uploaded.',
        );
    }

    return body.media;
}

/**
 * "Upload" in an image block (and images dropped on one): stores the file in
 * the workspace media library and resolves with its URL once it is ready,
 * waiting out a queued WebP conversion. The page's media library dialog
 * reloads so the new image is there too.
 */
export async function uploadEditorImage(
    teamSlug: string,
    file: File,
): Promise<ImageResult> {
    if (!UPLOADABLE_TYPES.includes(file.type)) {
        throw new Error('Upload a JPEG, PNG, GIF, or WebP image.');
    }

    if (file.size > MEDIA_MAX_BYTES) {
        throw new Error('Each image must be 2 MB or smaller.');
    }

    const form = new FormData();
    form.append('files[]', file);

    let media = await request(store.url(teamSlug), {
        method: 'POST',
        body: form,
    });

    for (let attempt = 0; media.status === 'processing'; attempt++) {
        if (attempt >= POLL_LIMIT) {
            throw new Error(
                'The image is still being optimized. Pick it from the media library in a moment.',
            );
        }

        await new Promise((resolve) =>
            window.setTimeout(resolve, POLL_INTERVAL),
        );
        media = await request(
            show.url({ current_team: teamSlug, media: media.uuid }),
        );
    }

    if (media.status === 'failed' || !media.absolute_url) {
        throw new Error(
            media.failed_reason ?? 'The image could not be processed.',
        );
    }

    router.reload({ only: ['mediaLibrary'] });

    return {
        url: media.absolute_url,
        alt: media.alt ?? altFromName(media.name),
    };
}
