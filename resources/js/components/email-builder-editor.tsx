import { EmailEditor } from '@maildun/email-builder/editor';
import type {
    EmailEditorHandle,
    ImageResult,
    MergeTag,
} from '@maildun/email-builder/editor';
import type { Ref } from 'react';
import { useMounted } from '@/hooks/use-mounted';
import { BUILDER_MERGE_TAGS, toEmailDocument } from '@/lib/email-builder';
import type { EmailBuilderDocument } from '@/types/emails';
import './email-builder-editor.css';

type Props = {
    document: EmailBuilderDocument;
    onChange: (document: EmailBuilderDocument) => void;
    disabled?: boolean;
    fill?: boolean;
    mergeTags?: MergeTag[];
    ref?: Ref<EmailEditorHandle>;
    onPickImage?: () => Promise<ImageResult | null>;
};

export function EmailBuilderEditor({
    document,
    onChange,
    disabled = false,
    fill = false,
    mergeTags = BUILDER_MERGE_TAGS,
    ref,
    onPickImage,
}: Props) {
    const mounted = useMounted();

    return (
        <div
            data-test="email-builder"
            data-fill={fill || undefined}
            className="email-builder"
        >
            {mounted ? (
                <EmailEditor
                    ref={ref}
                    value={toEmailDocument(document)}
                    onChange={onChange}
                    readOnly={disabled}
                    mergeTags={mergeTags}
                    onPickImage={onPickImage}
                />
            ) : (
                <div className="size-full bg-muted" aria-hidden="true" />
            )}
        </div>
    );
}
