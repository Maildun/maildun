import { lazy } from 'react';

/**
 * The block builder, the CodeMirror editors, and the design assistant are the
 * heaviest code on the compose pages, and an email only ever uses the one
 * editor its stored `editor` picks. Each loads the first time Design renders
 * it, so the hub and the other editors never download it. Render them inside
 * a Suspense boundary with `EmailEditorFallback`.
 */
export const EmailBuilderEditor = lazy(() =>
    import('@/components/email-builder-editor').then((module) => ({
        default: module.EmailBuilderEditor,
    })),
);

export const EmailHtmlEditor = lazy(() =>
    import('@/components/email-html-editor').then((module) => ({
        default: module.EmailHtmlEditor,
    })),
);

export const EmailSourceEditor = lazy(() =>
    import('@/components/email-source-editor').then((module) => ({
        default: module.EmailSourceEditor,
    })),
);

export const DesignAssistant = lazy(() =>
    import('@/components/design-assistant').then((module) => ({
        default: module.DesignAssistant,
    })),
);

/** The muted canvas the builder itself shows until it mounts. */
export function EmailEditorFallback() {
    return (
        <div
            className="flex min-h-[32rem] min-w-0 flex-1 animate-pulse bg-muted"
            aria-busy="true"
            aria-label="Loading editor"
        />
    );
}
