/**
 * Country flag SVGs from the MIT-licensed flag-icons project
 * (https://github.com/lipis/flag-icons), keyed by ISO 3166-1 alpha-2 code.
 *
 * Vite emits every flag as its own asset instead of the project's stylesheet,
 * so a report ships a small URL map and downloads only the flags it renders.
 */
const FLAG_URLS = import.meta.glob<string>(
    '/node_modules/flag-icons/flags/4x3/*.svg',
    { query: '?url', import: 'default', eager: true },
);

const FLAG_URLS_BY_COUNTRY_CODE = new Map(
    Object.entries(FLAG_URLS).map(([path, url]) => [
        (path.split('/').pop() ?? '').replace('.svg', '').toUpperCase(),
        url,
    ]),
);

export function countryFlagUrl(countryCode: string): string | null {
    const normalizedCode = countryCode.trim().toUpperCase();

    if (!/^[A-Z]{2}$/.test(normalizedCode)) {
        return null;
    }

    return FLAG_URLS_BY_COUNTRY_CODE.get(normalizedCode) ?? null;
}
