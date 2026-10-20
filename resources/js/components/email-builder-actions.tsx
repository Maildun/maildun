import {
    Copy01Icon,
    Download04Icon,
    FileCodeIcon,
    PaintBrush02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import type { EmailEditorHandle } from '@maildun/email-builder/editor';
import type { RefObject } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toast } from '@/components/ui/toast';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import { brandThemeOp } from '@/lib/email-builder';
import type { TeamBrandTheme } from '@/types/teams';

type ExportKind = 'html' | 'text' | 'json';

const EXPORTS: Record<
    ExportKind,
    { extension: string; type: string; label: string }
> = {
    html: { extension: 'html', type: 'text/html', label: 'Download HTML' },
    text: {
        extension: 'txt',
        type: 'text/plain',
        label: 'Download plain text',
    },
    json: {
        extension: 'json',
        type: 'application/json',
        label: 'Download design JSON',
    },
};

/** "Spring sale: 20% off!" → "spring-sale-20-off". */
function fileName(name: string): string {
    const slug = name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return slug === '' ? 'email' : slug;
}

function download(contents: string, name: string, type: string) {
    const url = URL.createObjectURL(new Blob([contents], { type }));
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

/**
 * The block editor's Apply brand and Export actions for a design navbar.
 * Exports render in the browser from what is on the canvas, saved or not.
 */
export function EmailBuilderActions({
    editor,
    name,
    brandTheme,
    canManage,
}: {
    editor: RefObject<EmailEditorHandle | null>;
    name: string;
    brandTheme: TeamBrandTheme;
    canManage: boolean;
}) {
    const [, copy] = useClipboard();

    const applyBrand = () => {
        const result = editor.current?.apply(brandThemeOp(brandTheme));

        if (result?.ok) {
            toast.add({
                type: 'success',
                title: 'Applied your brand color and font.',
                description: 'Press ⌘Z to undo.',
            });
        } else {
            toast.add({
                type: 'error',
                title: 'Could not apply the brand.',
                description: result?.issues[0]?.message,
            });
        }
    };

    const copyHtml = async () => {
        const handle = editor.current;

        if (!handle) {
            return;
        }

        const { html, warnings } = handle.render();

        if (await copy(html)) {
            toast.add({
                type: 'success',
                title: 'Copied the email HTML.',
                description: warnings[0]?.message,
            });
        } else {
            toast.add({ type: 'error', title: 'Could not copy the HTML.' });
        }
    };

    const exportAs = (kind: ExportKind) => {
        const handle = editor.current;

        if (!handle) {
            return;
        }

        const { extension, type } = EXPORTS[kind];
        const contents =
            kind === 'json'
                ? `${JSON.stringify(handle.getDocument(), null, 2)}\n`
                : handle.render()[kind];

        download(contents, `${fileName(name)}.${extension}`, type);
    };

    return (
        <>
            {canManage ? (
                <Tooltip>
                    <TooltipTrigger
                        render={
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                aria-label="Apply brand"
                                data-test="apply-brand"
                                onClick={applyBrand}
                            />
                        }
                    >
                        <HugeiconsIcon
                            icon={PaintBrush02Icon}
                            data-icon="inline-start"
                        />
                        <span className="hidden xl:inline">Apply brand</span>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        Use the workspace brand color and font in this email
                    </TooltipContent>
                </Tooltip>
            ) : null}
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            aria-label="Export"
                            data-test="export-design"
                        />
                    }
                >
                    <HugeiconsIcon
                        icon={Download04Icon}
                        data-icon="inline-start"
                    />
                    <span className="hidden xl:inline">Export</span>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <DropdownMenuGroup>
                        <DropdownMenuItem onClick={() => void copyHtml()}>
                            <HugeiconsIcon icon={Copy01Icon} />
                            Copy HTML
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => exportAs('html')}>
                            <HugeiconsIcon icon={FileCodeIcon} />
                            {EXPORTS.html.label}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => exportAs('text')}>
                            <HugeiconsIcon icon={Download04Icon} />
                            {EXPORTS.text.label}
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => exportAs('json')}>
                            <HugeiconsIcon icon={Download04Icon} />
                            {EXPORTS.json.label}
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );
}
