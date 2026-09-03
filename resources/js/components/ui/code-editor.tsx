import { html as htmlLanguage } from '@codemirror/lang-html';
import { json as jsonLanguage } from '@codemirror/lang-json';
import CodeMirror, { EditorView, type Extension } from '@uiw/react-codemirror';
import { useAppearance } from '@/hooks/use-appearance';
import { useMounted } from '@/hooks/use-mounted';
import { cn } from '@/lib/utils';

type CodeLanguage = 'html' | 'json' | 'text';

type Props = {
    value: string;
    language: CodeLanguage;
    onChange?: (value: string) => void;
    readOnly?: boolean;
    lineNumbers?: boolean;
    /** Any CSS height; the editor scrolls inside it. */
    height?: string;
    className?: string;
    'aria-label'?: string;
    'aria-invalid'?: boolean;
    'data-test'?: string;
};

/** Language extensions are stable per language, so they are built once. */
const LANGUAGES: Record<CodeLanguage, Extension> = {
    html: htmlLanguage(),
    json: jsonLanguage(),
    text: [],
};

/**
 * CodeMirror paints its own chrome, so it is stripped back to the app's
 * tokens and the surrounding container owns the border, radius and background.
 */
const appTheme = EditorView.theme({
    '&': {
        backgroundColor: 'transparent',
        fontSize: '0.75rem',
        height: '100%',
    },
    '&.cm-focused': { outline: 'none' },
    '.cm-scroller': {
        fontFamily:
            'var(--font-mono, ui-monospace, SFMono-Regular, Menlo, monospace)',
        lineHeight: '1.6',
    },
    '.cm-gutters': {
        backgroundColor: 'transparent',
        borderRight: '1px solid var(--border)',
        color: 'var(--muted-foreground)',
    },
    '.cm-activeLine, .cm-activeLineGutter': { backgroundColor: 'transparent' },
});

/**
 * A code view for the email body: editable for raw HTML, read-only for the
 * generated HTML and the block document's JSON.
 *
 * CodeMirror measures its container, so it is mounted client-side only and SSR
 * renders the same text in a plain `<pre>` (see `.ai/rules/js.md`).
 */
export function CodeEditor({
    value,
    language,
    onChange,
    readOnly = false,
    lineNumbers = true,
    height = '100%',
    className,
    'aria-label': ariaLabel,
    'aria-invalid': ariaInvalid,
    'data-test': dataTest,
}: Props) {
    const mounted = useMounted();
    const { resolvedAppearance } = useAppearance();

    const extensions = [LANGUAGES[language], appTheme];

    if (ariaLabel) {
        extensions.push(
            EditorView.contentAttributes.of({ 'aria-label': ariaLabel }),
        );
    }

    return (
        <div
            data-slot="code-editor"
            data-test={dataTest}
            aria-invalid={ariaInvalid}
            className={cn(
                'flex flex-col overflow-hidden rounded-lg border bg-background text-xs',
                'focus-within:border-ring focus-within:ring-[3px] focus-within:ring-zinc-200 dark:focus-within:ring-zinc-800',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                readOnly && 'focus-within:ring-0',
                className,
            )}
            style={{ height }}
        >
            {mounted ? (
                <CodeMirror
                    value={value}
                    height="100%"
                    theme={resolvedAppearance}
                    editable={!readOnly}
                    readOnly={readOnly}
                    extensions={extensions}
                    basicSetup={{
                        lineNumbers,
                        foldGutter: !readOnly,
                        highlightActiveLine: !readOnly,
                        highlightActiveLineGutter: !readOnly,
                        autocompletion: !readOnly,
                    }}
                    onChange={onChange}
                    className="min-h-0 flex-1"
                />
            ) : (
                <pre className="min-h-0 flex-1 overflow-auto p-3 font-mono text-xs">
                    {value}
                </pre>
            )}
        </div>
    );
}
