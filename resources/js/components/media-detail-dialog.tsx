import {
    ArrowDown03Icon,
    Copy01Icon,
    Delete02Icon,
    MoreHorizontalIcon,
    Tick02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { useForm } from '@inertiajs/react';
import { MediaCategoryCombobox } from '@/components/media-category-combobox';
import { TagsCombobox } from '@/components/tags-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatRelativeTime } from '@/lib/format';
import { update } from '@/routes/media';
import type { MediaItem, MediaTaxonomy } from '@/types';

type Props = {
    teamSlug: string;
    media: MediaItem | null;
    categories: MediaTaxonomy[];
    tags: MediaTaxonomy[];
    canManage: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDelete: (media: MediaItem) => void;
};

export default function MediaDetailDialog({
    teamSlug,
    media,
    categories,
    tags,
    canManage,
    open,
    onOpenChange,
    onDelete,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="flex max-h-[min(44rem,calc(100vh-2rem))] w-4xl flex-col gap-0 overflow-hidden p-0"
                showCloseButton
            >
                {media ? (
                    <MediaDetailBody
                        key={media.uuid}
                        teamSlug={teamSlug}
                        media={media}
                        categories={categories}
                        tags={tags}
                        canManage={canManage}
                        onDelete={onDelete}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function MediaDetailBody({
    teamSlug,
    media,
    categories,
    tags,
    canManage,
    onDelete,
}: {
    teamSlug: string;
    media: MediaItem;
    categories: MediaTaxonomy[];
    tags: MediaTaxonomy[];
    canManage: boolean;
    onDelete: (media: MediaItem) => void;
}) {
    const [copiedText, copy] = useClipboard();
    const form = useForm({
        name: media.name,
        alt: media.alt ?? '',
        category: media.category?.name ?? '',
        tags: media.tags.map((tag) => tag.name),
    });
    const copied = copiedText === media.absolute_url;

    const copyLink = async () => {
        if (!media.absolute_url) {
            return;
        }

        const ok = await copy(media.absolute_url);

        if (ok) {
            toast.add({
                type: 'success',
                title: 'Link copied.',
            });
        }
    };

    const downloadFile = async () => {
        const href = media.url ?? media.absolute_url;

        if (!href) {
            return;
        }

        try {
            const response = await fetch(href);

            if (!response.ok) {
                throw new Error('Download failed.');
            }

            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = media.name;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(objectUrl);
        } catch {
            toast.add({
                type: 'error',
                title: 'Failed to download the file.',
            });
        }
    };

    return (
        <>
            <DialogHeader className="px-6 pt-6 pr-14 pb-4">
                <DialogTitle className="truncate">{media.name}</DialogTitle>
                <DialogDescription>
                    {media.created_at
                        ? `Added ${formatRelativeTime(media.created_at)}`
                        : 'Team media'}
                </DialogDescription>
            </DialogHeader>

            <div className="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-2">
                <div className="flex min-h-0 flex-col bg-muted">
                    <div className="flex max-h-64 min-h-48 flex-1 items-center justify-center p-6 md:max-h-none">
                        {media.processing ? (
                            <Spinner />
                        ) : media.url ? (
                            <img
                                src={media.url}
                                alt={media.alt ?? media.name}
                                className="max-h-full max-w-full object-contain"
                            />
                        ) : (
                            <p className="px-4 text-center text-sm text-muted-foreground">
                                {media.failed_reason ??
                                    'This file is not ready.'}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center gap-2 px-6 pb-4">
                        <Badge
                            variant={
                                media.status === 'ready'
                                    ? 'success'
                                    : media.status === 'failed'
                                      ? 'destructive'
                                      : 'secondary'
                            }
                        >
                            {media.status === 'processing'
                                ? 'Processing'
                                : media.status === 'failed'
                                  ? 'Failed'
                                  : 'Ready'}
                        </Badge>
                        {media.mime_type ? (
                            <Badge variant="outline">{media.mime_type}</Badge>
                        ) : null}
                        <span className="text-xs text-muted-foreground">
                            {media.size_label}
                            {media.width && media.height
                                ? ` · ${media.width} × ${media.height}`
                                : ''}
                        </span>
                    </div>
                </div>

                <form
                    id="media-details-form"
                    className="flex min-h-0 flex-col overflow-y-auto p-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(update.url([teamSlug, media.uuid]), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="media-url">
                                File URL
                            </FieldLabel>
                            <InputGroup>
                                <InputGroupInput
                                    id="media-url"
                                    readOnly
                                    value={media.absolute_url ?? ''}
                                    placeholder="https://example.com/storage/hero.png"
                                    onFocus={(event) => event.target.select()}
                                />
                                <InputGroupAddon align="inline-end">
                                    <InputGroupButton
                                        variant="ghost"
                                        size="icon-xs"
                                        aria-label={
                                            copied ? 'Copied' : 'Copy link'
                                        }
                                        disabled={!media.absolute_url}
                                        onClick={copyLink}
                                    >
                                        <HugeiconsIcon
                                            icon={
                                                copied ? Tick02Icon : Copy01Icon
                                            }
                                        />
                                    </InputGroupButton>
                                </InputGroupAddon>
                            </InputGroup>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="media-name">Name</FieldLabel>
                            <Input
                                id="media-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="hero-banner.png"
                                disabled={!canManage}
                                aria-invalid={Boolean(form.errors.name)}
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.alt)}>
                            <FieldLabel htmlFor="media-alt">
                                Alt text
                            </FieldLabel>
                            <Input
                                id="media-alt"
                                value={form.data.alt}
                                onChange={(event) =>
                                    form.setData('alt', event.target.value)
                                }
                                placeholder="Product photo on a white background"
                                disabled={!canManage}
                                aria-invalid={Boolean(form.errors.alt)}
                            />
                            <FieldError>{form.errors.alt}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.category)}>
                            <FieldLabel htmlFor="media-category">
                                Category
                            </FieldLabel>
                            {canManage ? (
                                <MediaCategoryCombobox
                                    id="media-category"
                                    value={form.data.category}
                                    onValueChange={(name) =>
                                        form.setData('category', name)
                                    }
                                    availableCategories={categories}
                                    aria-invalid={Boolean(form.errors.category)}
                                />
                            ) : (
                                <p className="text-sm">
                                    {media.category?.name ?? 'Uncategorized'}
                                </p>
                            )}
                            <FieldError>{form.errors.category}</FieldError>
                        </Field>
                        <Field
                            data-invalid={Boolean(form.errors.tags)}
                            data-test="media-tags-field"
                        >
                            <FieldLabel htmlFor="media-tags">Tags</FieldLabel>
                            {canManage ? (
                                <TagsCombobox
                                    id="media-tags"
                                    value={form.data.tags}
                                    onValueChange={(names) =>
                                        form.setData('tags', names)
                                    }
                                    availableTags={tags}
                                    placeholder="Search or create a tag…"
                                    aria-invalid={Boolean(form.errors.tags)}
                                />
                            ) : media.tags.length > 0 ? (
                                <div className="flex flex-wrap gap-1">
                                    {media.tags.map((tag) => (
                                        <Badge
                                            key={tag.uuid}
                                            variant="secondary"
                                        >
                                            {tag.name}
                                        </Badge>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No tags
                                </p>
                            )}
                            <FieldError>{form.errors.tags}</FieldError>
                        </Field>
                    </FieldGroup>
                </form>
            </div>

            <>
                <Separator />
                <DialogFooter className="px-6 py-4">
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    aria-label="Media actions"
                                    data-test="media-actions-button"
                                />
                            }
                        >
                            <HugeiconsIcon icon={MoreHorizontalIcon} />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    data-test="copy-media-link"
                                    disabled={!media.absolute_url}
                                    onClick={copyLink}
                                >
                                    <HugeiconsIcon icon={Copy01Icon} />
                                    Copy link
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    data-test="download-media-button"
                                    disabled={!media.url && !media.absolute_url}
                                    onClick={downloadFile}
                                >
                                    <HugeiconsIcon icon={ArrowDown03Icon} />
                                    Download
                                </DropdownMenuItem>
                                {canManage ? (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            data-test="delete-media-button"
                                            onClick={() => onDelete(media)}
                                        >
                                            <HugeiconsIcon
                                                icon={Delete02Icon}
                                            />
                                            Delete
                                        </DropdownMenuItem>
                                    </>
                                ) : null}
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    {canManage ? (
                        <Button
                            type="submit"
                            form="media-details-form"
                            disabled={form.processing || !form.isDirty}
                        >
                            {form.processing ? (
                                <Spinner data-icon="inline-start" />
                            ) : null}
                            Save details
                        </Button>
                    ) : null}
                </DialogFooter>
            </>
        </>
    );
}
