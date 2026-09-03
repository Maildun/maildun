export function pathnameFromHref(href: string): string {
    if (!href.startsWith('http')) {
        return href.split(/[?#]/, 1)[0] ?? href;
    }

    try {
        return new URL(href).pathname;
    } catch {
        return href;
    }
}

export function normalizePathname(path: string): string {
    if (path === '/') {
        return '/';
    }

    return path.endsWith('/') ? path.slice(0, -1) : path;
}

/**
 * Exact match, or a boundary-aware prefix so `/acme` does not highlight `/acmecorp`.
 */
export function isCurrentPath(
    currentPath: string,
    hrefPath: string,
    startsWith = false,
): boolean {
    const current = normalizePathname(currentPath);
    const href = normalizePathname(hrefPath);

    if (current === href) {
        return true;
    }

    if (!startsWith) {
        return false;
    }

    if (href === '/') {
        return true;
    }

    return current.startsWith(`${href}/`);
}
