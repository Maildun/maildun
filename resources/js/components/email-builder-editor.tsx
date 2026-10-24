import { AiEditingIcon } from '@hugeicons/core-free-icons';
import { EmailEditor } from '@maildun/email-builder/editor';
import type {
    EditorClassNames,
    EditorIcons,
    EmailEditorHandle,
    ImageResult,
    MergeTag,
    PaletteGroup,
} from '@maildun/email-builder/editor';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import type { Ref } from 'react';
import { useMounted } from '@/hooks/use-mounted';
import { useProposalExit } from '@/hooks/use-proposal-exit';
import { BUILDER_MERGE_TAGS, toEmailDocument } from '@/lib/email-builder';
import type { EmailBuilderDocument } from '@/types/emails';
import './email-builder-editor.css';

/**
 * The proposal bar in the app's card style: the bar is the muted shell, and a
 * pseudo-element draws the inset card behind its content (the bar is its own
 * stacking context, so `-z-10` stays above the shell's background). Text
 * only: its icons are hidden, and buttons get back the padding an icon took.
 * It bounces in here; `useProposalExit` plays the way out.
 */
const EDITOR_CLASS_NAMES: EditorClassNames = {
    proposal:
        'rounded-2xl border-border/60 bg-muted py-3.5 pr-3.5 pl-5 shadow-lg before:absolute before:inset-1 before:-z-10 before:rounded-xl before:border before:border-border before:bg-card before:shadow-xs [&_button]:pl-2.5! [&_svg]:hidden origin-bottom motion-safe:animate-proposal-in',
};

const EDITOR_ICONS: EditorIcons = { generate: AiEditingIcon };

const PANELS_KEY = 'maildun.email-builder.panels';
/** From this width the assistant fits beside the block palette. */
const WIDE_WINDOW = 1600;

type Panels = { sidebar: boolean; inspector: boolean };

function loadPanels(): Panels {
    try {
        const stored = JSON.parse(
            window.localStorage.getItem(PANELS_KEY) ?? '{}',
        ) as Partial<Panels>;

        return {
            sidebar: stored.sidebar !== false,
            inspector: stored.inspector !== false,
        };
    } catch {
        return { sidebar: true, inspector: true };
    }
}

function savePanels(panels: Panels) {
    try {
        window.localStorage.setItem(PANELS_KEY, JSON.stringify(panels));
    } catch {
        // Remembered for this visit only.
    }
}

/** Whether the open assistant is taking the block palette's place. */
function hidesSidebar(assistantOpen: boolean): boolean {
    return assistantOpen && window.innerWidth < WIDE_WINDOW;
}

type Props = {
    document: EmailBuilderDocument;
    onChange: (document: EmailBuilderDocument) => void;
    disabled?: boolean;
    fill?: boolean;
    mergeTags?: MergeTag[];
    ref?: Ref<EmailEditorHandle>;
    onPickImage?: () => Promise<ImageResult | null>;
    onUploadImage?: (file: File) => Promise<ImageResult>;
    /** Writes a preheader for the design; shows the generate button on the preheader field. */
    onGeneratePreheader?: (document: EmailBuilderDocument) => Promise<string>;
    paletteGroups?: PaletteGroup[];
    /** The design assistant panel is open beside the editor. */
    assistantOpen?: boolean;
};

export function EmailBuilderEditor({
    document,
    onChange,
    disabled = false,
    fill = false,
    mergeTags = BUILDER_MERGE_TAGS,
    ref,
    onPickImage,
    onUploadImage,
    onGeneratePreheader,
    paletteGroups,
    assistantOpen = false,
}: Props) {
    const mounted = useMounted();
    const frame = useRef<HTMLDivElement>(null);
    const [handle, setHandle] = useState<EmailEditorHandle | null>(null);
    const assistantOpenRef = useRef(assistantOpen);

    useProposalExit(frame, mounted);

    useEffect(() => {
        assistantOpenRef.current = assistantOpen;
    }, [assistantOpen]);

    const attach = useCallback(
        (next: EmailEditorHandle | null) => {
            setHandle(next);

            if (typeof ref === 'function') {
                ref(next);
            } else if (ref) {
                ref.current = next;
            }
        },
        [ref],
    );

    // Panels stay the way the author left them: restored when the editor
    // opens, and saved whenever the author toggles one.
    useLayoutEffect(() => {
        const store = handle?.store;

        if (!store) {
            return;
        }

        const saved = loadPanels();
        store.setPanel(
            'sidebar',
            saved.sidebar && !hidesSidebar(assistantOpenRef.current),
        );
        store.setPanel('inspector', saved.inspector);

        let previous = store.getState().panels;

        return store.subscribe(() => {
            const { panels } = store.getState();

            if (panels === previous) {
                return;
            }

            // While the assistant hides the palette, only an explicit reopen counts as a choice.
            const sidebar =
                hidesSidebar(assistantOpenRef.current) && !panels.sidebar
                    ? loadPanels().sidebar
                    : panels.sidebar;
            previous = panels;
            savePanels({ sidebar, inspector: panels.inspector });
        });
    }, [handle]);

    // The assistant takes the block palette's place on narrower windows, so
    // the 600px email still fits the canvas; closing it brings back the
    // author's own choice rather than always reopening the palette.
    useEffect(() => {
        const store = handle?.store;

        if (!store || window.innerWidth >= WIDE_WINDOW) {
            return;
        }

        store.setPanel('sidebar', assistantOpen ? false : loadPanels().sidebar);
    }, [assistantOpen, handle]);

    return (
        <div
            ref={frame}
            data-test="email-builder"
            data-fill={fill || undefined}
            className="email-builder"
        >
            {mounted ? (
                <EmailEditor
                    ref={attach}
                    value={toEmailDocument(document)}
                    onChange={onChange}
                    readOnly={disabled}
                    mergeTags={mergeTags}
                    onPickImage={onPickImage}
                    onUploadImage={onUploadImage}
                    onGeneratePreheader={onGeneratePreheader}
                    paletteGroups={paletteGroups}
                    classNames={EDITOR_CLASS_NAMES}
                    icons={EDITOR_ICONS}
                />
            ) : (
                <div className="size-full bg-muted" aria-hidden="true" />
            )}
        </div>
    );
}
