import { ThemeProvider } from '@mui/material/styles';
import { useEffect, useRef } from 'react';
import EmailBuilderApp from '@/email-builder/App';
import type { TEditorConfiguration } from '@/email-builder/documents/editor/core';
import {
    resetDocument,
    subscribeDocument,
} from '@/email-builder/documents/editor/EditorContext';
import theme from '@/email-builder/theme';
import { useMounted } from '@/hooks/use-mounted';
import { cn } from '@/lib/utils';
import type { EmailBuilderDocument } from '@/types/emails';
import 'highlight.js/styles/default.css';
import './email-builder-editor.css';

type Props = {
    document: EmailBuilderDocument;
    onChange: (document: EmailBuilderDocument) => void;
    disabled?: boolean;
    fill?: boolean;
};

export function EmailBuilderEditor({
    document,
    onChange,
    disabled = false,
    fill = false,
}: Props) {
    const mounted = useMounted();

    if (!mounted) {
        return (
            <div
                data-test="email-builder-js"
                data-fill={fill || undefined}
                className="email-builder-js bg-muted"
                aria-hidden="true"
            />
        );
    }

    return (
        <EmailBuilderEditorClient
            document={document}
            disabled={disabled}
            fill={fill}
            onChange={onChange}
        />
    );
}

function EmailBuilderEditorClient({
    document,
    onChange,
    disabled,
    fill = false,
}: Props) {
    const onChangeRef = useRef(onChange);
    const lastPushed = useRef<EmailBuilderDocument>(document);

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    useEffect(() => {
        resetDocument(document as TEditorConfiguration);
        lastPushed.current = document;

        return subscribeDocument((next) => {
            const typed = next as EmailBuilderDocument;
            lastPushed.current = typed;
            onChangeRef.current(typed);
        });
        // Load the campaign/template document once per mount. Parent-driven
        // replacements are handled below by reference.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (document === lastPushed.current) {
            return;
        }

        resetDocument(document as TEditorConfiguration);
        lastPushed.current = document;
    }, [document]);

    return (
        <div
            data-test="email-builder-js"
            data-fill={fill || undefined}
            className={cn(
                'email-builder-js',
                disabled && 'pointer-events-none',
            )}
        >
            <ThemeProvider theme={theme}>
                <EmailBuilderApp />
            </ThemeProvider>
        </div>
    );
}
