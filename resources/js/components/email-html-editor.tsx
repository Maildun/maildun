import { useState } from 'react';
import PreviewWidthTabs from '@/components/preview-width-tabs';
import type { PreviewWidth } from '@/components/preview-width-tabs';
import { CodeEditor } from '@/components/ui/code-editor';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

type Props = {
    value: string;
    onChange: (html: string) => void;
    disabled?: boolean;
    'aria-invalid'?: boolean;
};

/**
 * The raw HTML editor: the author owns the markup, so the preview is an
 * isolated iframe rather than anything that could leak app styles into it.
 */
export function EmailHtmlEditor({
    value,
    onChange,
    disabled = false,
    'aria-invalid': ariaInvalid,
}: Props) {
    const [previewWidth, setPreviewWidth] = useState<PreviewWidth>('desktop');

    return (
        <Tabs defaultValue="html" className="min-h-[32rem] flex-1">
            <TabsList variant="sliding">
                <TabsTrigger value="html" data-test="email-html-tab">
                    HTML
                </TabsTrigger>
                <TabsTrigger value="preview" data-test="email-preview-tab">
                    Preview
                </TabsTrigger>
            </TabsList>

            <TabsContent value="html" className="flex min-h-0 flex-1 flex-col">
                <CodeEditor
                    data-test="email-html-input"
                    aria-label="Email HTML"
                    aria-invalid={ariaInvalid}
                    language="html"
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
                            <iframe
                                title="Email preview"
                                data-test="email-html-preview"
                                sandbox=""
                                srcDoc={value}
                                className="h-full min-h-[24rem] w-full border-0"
                            />
                        </div>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    );
}
