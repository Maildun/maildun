export function formatRelativeTime(value: string): string {
    const diffMs = Date.now() - new Date(value).getTime();
    const diffMinutes = Math.floor(diffMs / 60_000);

    if (diffMinutes < 1) {
        return 'Now';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes}m`;
    }

    const diffHours = Math.floor(diffMinutes / 60);

    if (diffHours < 24) {
        return `${diffHours}h`;
    }

    const diffDays = Math.floor(diffHours / 24);

    if (diffDays < 30) {
        return `${diffDays}d`;
    }

    return new Date(value).toLocaleDateString();
}

/**
 * The forward-looking counterpart to formatRelativeTime, which measures from
 * the value to now and so collapses every future timestamp to "Now". Use this
 * for a time the app is waiting on, such as an automation run's scheduled_at.
 */
export function formatTimeUntil(value: string): string {
    const diffMs = new Date(value).getTime() - Date.now();
    const diffMinutes = Math.floor(diffMs / 60_000);

    if (diffMinutes < 1) {
        return 'now';
    }

    if (diffMinutes < 60) {
        return `in ${diffMinutes}m`;
    }

    const diffHours = Math.floor(diffMinutes / 60);

    if (diffHours < 24) {
        return `in ${diffHours}h`;
    }

    const diffDays = Math.floor(diffHours / 24);

    if (diffDays < 30) {
        return `in ${diffDays}d`;
    }

    return `on ${new Date(value).toLocaleDateString()}`;
}

const FILE_SIZE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB'] as const;

/**
 * Client-side counterpart to Laravel's Number::fileSize, used while a file is
 * still uploading and the server has not returned a size_label yet.
 */
export function formatFileSize(bytes: number): string {
    let size = Math.max(0, bytes);
    let unit = 0;

    while (size >= 1024 && unit < FILE_SIZE_UNITS.length - 1) {
        size /= 1024;
        unit += 1;
    }

    const rounded = unit === 0 ? Math.round(size) : Math.round(size * 10) / 10;

    return `${rounded} ${FILE_SIZE_UNITS[unit]}`;
}

/**
 * Formats a Date attribute value for display. A value that is not a date is
 * shown as typed instead of throwing, so one bad value cannot blank a page.
 * Date-only values parse as UTC midnight, so they are formatted in UTC too;
 * the viewer's zone would otherwise show the previous day west of UTC.
 */
export function formatAttributeDate(
    value: string | number,
    locale?: string,
): string {
    const parsed = new Date(String(value));

    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(parsed);
}
