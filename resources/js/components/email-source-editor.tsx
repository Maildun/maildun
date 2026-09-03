import { Reader } from '@usewaypoint/email-builder';
import { useState } from 'react';
import PreviewWidthTabs from '@/components/preview-width-tabs';
import type { PreviewWidth } from '@/components/preview-width-tabs';
import { CodeEditor } from '@/components/ui/code-editor';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    ROOT_BLOCK_ID,
    sourceToBuilderDocument,
    toReaderDocument,
} from '@/lib/email-builder';
import { cn } from '@/lib/utils';
import type { EmailSourceMode } from '@/types';

type Props = {
    editor: EmailSourceMode;
    value: string;
    onChange: (source: string) => void;
    disabled?: boolean;
    'aria-invalid'?: boolean;
};

export function EmailSourceEditor({
    editor,
    value,
    onChange,
    disabled = false,
    'aria-invalid': ariaInvalid,
}: Props) {
    const [previewWidth, setPreviewWidth] = useState<PreviewWidth>('desktop');
    const label = editor === 'markdown' ? 'Markdown' : 'Plain text';
    const document = sourceToBuilderDocument(value, editor);

    return (
        <Tabs defaultValue="source" className="min-h-[32rem] flex-1">
            <TabsList variant="sliding">
                <TabsTrigger value="source" data-test="email-source-tab">
                    {label}
                </TabsTrigger>
                <TabsTrigger value="preview" data-test="email-preview-tab">
                    Preview
                </TabsTrigger>
            </TabsList>

            <TabsContent
                value="source"
                className="flex min-h-0 flex-1 flex-col"
            >
                <CodeEditor
                    data-test="email-source-input"
                    aria-label={`Email ${label}`}
                    aria-invalid={ariaInvalid}
                    language="text"
                    readOnly={disabled}
                    value={value}
                    onChange={onChange}
                    className="min-h-[28rem] flex-1"
                />
            </TabsContent>

            <TabsContent value="preview" className="min-h-0 flex-1">
                <div className="flex min-h-[28rem] flex-col overflow-hidden rounded-lg border">
                    <div className="flex justify-end border-b p-2">
                        <PreviewWidthTabs
                            value={previewWidth}
                            onValueChange={setPreviewWidth}
                            testIdPrefix="email-preview"
                        />
                    </div>

                    <div className="flex flex-1 justify-center overflow-auto bg-muted/30 p-4">
                        <div
                            data-test="email-preview-viewport"
                            data-preview-width={previewWidth}
                            className={cn(
                                'min-h-[24rem] max-w-full shrink-0 overflow-hidden rounded-md bg-white shadow-sm transition-[width] duration-200 motion-reduce:transition-none',
                                previewWidth === 'desktop'
                                    ? 'w-[600px]'
                                    : 'w-[375px]',
                            )}
                        >
                            <Reader
                                document={toReaderDocument(document)}
                                rootBlockId={ROOT_BLOCK_ID}
                            />
                        </div>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    );
}
