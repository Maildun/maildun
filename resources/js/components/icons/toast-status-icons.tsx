import type { SVGProps } from 'react';

/**
 * Hugeicons' free tier only ships stroke-style icons, not the solid/filled
 * variants used for toast status glyphs. These are hand-built to match
 * hugeicons' 24x24 style: a solid `currentColor` circle with the glyph
 * punched through in `var(--popover)` so it reads correctly against the
 * toaster's background in both light and dark themes.
 */
function SolidCircleGlyph({ children, ...props }: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <circle cx="12" cy="12" r="10" fill="currentColor" />
            {children}
        </svg>
    );
}

export function CheckmarkCircleSolidIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <SolidCircleGlyph {...props}>
            <path
                d="M7.75 12.5L10.5 15.25L16.25 9"
                stroke="var(--popover)"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </SolidCircleGlyph>
    );
}

export function InformationCircleSolidIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <SolidCircleGlyph {...props}>
            <circle cx="12" cy="7.75" r="1.25" fill="var(--popover)" />
            <path
                d="M12 11V17"
                stroke="var(--popover)"
                strokeWidth="2"
                strokeLinecap="round"
            />
        </SolidCircleGlyph>
    );
}

export function AlertCircleSolidIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <SolidCircleGlyph {...props}>
            <path
                d="M12 7V13.5"
                stroke="var(--popover)"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <circle cx="12" cy="16.5" r="1.25" fill="var(--popover)" />
        </SolidCircleGlyph>
    );
}

export function MultiplicationSignCircleSolidIcon(
    props: SVGProps<SVGSVGElement>,
) {
    return (
        <SolidCircleGlyph {...props}>
            <path
                d="M8.75 8.75L15.25 15.25M15.25 8.75L8.75 15.25"
                stroke="var(--popover)"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </SolidCircleGlyph>
    );
}
