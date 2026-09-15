import {
    Alert01Icon,
    ArrowUp03Icon,
    Copy01Icon,
    Image01Icon,
    Tick02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { usePoll } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import MediaDropzone from '@/components/media-dropzone';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { useClipboard } from '@/hooks/use-clipboard';
import { MEDIA_ACCEPT, useMediaUpload } from '@/hooks/use-media-upload';
import type { MediaItem, MediaLibraryData } from '@/types';

type Props = {
    teamSlug: string;
    library: MediaLibraryData;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function MediaLibraryDialog({
    teamSlug,
    library,
    open,
    onOpenChange,
}: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [search, setSearch] = useState('');
    const [copiedText, copy] = useClipboard();
    const processing = library.items.some((item) => item.processing);
    const { start, stop } = usePoll(
        2000,
        { only: ['mediaLibrary'] },
        { autoStart: false },
    );
    const { upload, uploading } = useMediaUpload(teamSlug, {
        convertUploadsToWebp: library.convertUploadsToWebp,
        only: ['mediaLibrary'],
    });
    const visibleItems = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();

        if (query === '') {
            return library.items;
        }

        return library.items.filter((item) =>
            [item.name, item.alt, item.category?.name]
                .filter(Boolean)
                .some((value) => value?.toLocaleLowerCase().includes(query)),
        );
    }, [library.items, search]);

    useEffect(() => {
        if (open && processing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [open, processing, start, stop]);

    const pickFiles = () => {
        if (!library.canUpload || uploading) {
            return;
        }

        fileInputRef.current?.click();
    };

    const copyLink = async (item: MediaItem) => {
        if (!item.absolute_url) {
            return;
        }

        if (await copy(item.absolute_url)) {
            toast.add({ type: 'success', title: 'Link copied.' });
        }
    };

    const content =
        visibleItems.length > 0 ? (
            <div
                className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                data-test="builder-media-grid"
            >
                {visibleItems.map((item) => (
                    <div
                        key={item.uuid}
                        className="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-2 shadow-xs"
                        data-test="builder-media-item"
                    >
                        <div className="relative grid aspect-video place-items-center overflow-hidden rounded-lg bg-muted">
                            {item.processing ? (
                                <Spinner />
                            ) : item.url ? (
                                <img
                                    src={item.url}
                                    alt={item.alt ?? item.name}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <span className="px-3 text-center text-xs text-muted-foreground">
                                    {item.failed_reason ??
                                        'Preview unavailable'}
                                </span>
                            )}
                            {item.status !== 'ready' ? (
                                <Badge
                                    className="absolute top-2 left-2"
                                    variant={
                                        item.status === 'failed'
                                            ? 'destructive'
                                            : 'secondary'
                                    }
                                >
                                    {item.status === 'failed'
                                        ? 'Failed'
                                        : 'Processing'}
                                </Badge>
                            ) : null}
                        </div>
                        <div className="flex min-w-0 items-center gap-2 px-1 pb-1">
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {item.name}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {item.size_label}
                                    {item.width && item.height
                                        ? ` · ${item.width} × ${item.height}`
                                        : ''}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!item.absolute_url}
                                aria-label={`Copy link for ${item.name}`}
                                data-test="copy-builder-media-link"
                                onClick={() => void copyLink(item)}
                            >
                                <HugeiconsIcon
                                    icon={
                                        copiedText === item.absolute_url
                                            ? Tick02Icon
                                            : Copy01Icon
                                    }
                                    data-icon="inline-start"
                                />
                                Copy link
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        ) : (
            <Empty className="min-h-72">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <HugeiconsIcon icon={Image01Icon} />
                    </EmptyMedia>
                    <EmptyTitle>
                        {search.trim() === ''
                            ? 'No media yet'
                            : 'No media found'}
                    </EmptyTitle>
                    <EmptyDescription>
                        {search.trim() === ''
                            ? library.canUpload
                                ? 'Upload an image to copy its link into your email.'
                                : 'Files uploaded by your team will appear here.'
                            : 'Try another search term.'}
                    </EmptyDescription>
                </EmptyHeader>
                {search.trim() === '' && library.canUpload ? (
                    <EmptyContent>
                        <Button type="button" onClick={pickFiles}>
                            Upload media
                        </Button>
                    </EmptyContent>
                ) : null}
            </Empty>
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="flex max-h-[min(48rem,calc(100vh-2rem))] w-5xl flex-col gap-0 overflow-hidden p-0"
                data-test="builder-media-dialog"
            >
                <DialogHeader className="px-6 pt-6 pr-14 pb-4">
                    <DialogTitle>Media library</DialogTitle>
                    <DialogDescription>
                        Upload an image, copy its link, then paste the link into
                        EmailBuilder.js.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-3 border-y px-6 py-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <Input
                            value={search}
                            placeholder="Search recent media"
                            aria-label="Search recent media"
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        {library.canManage ? (
                            <Button
                                type="button"
                                className="sm:shrink-0"
                                disabled={!library.canUpload || uploading}
                                data-test="upload-builder-media"
                                onClick={pickFiles}
                            >
                                {uploading ? (
                                    <Spinner data-icon="inline-start" />
                                ) : (
                                    <HugeiconsIcon
                                        icon={ArrowUp03Icon}
                                        data-icon="inline-start"
                                    />
                                )}
                                Upload
                            </Button>
                        ) : null}
                    </div>

                    {library.canManage && library.atLimit ? (
                        <Alert variant="destructive">
                            <HugeiconsIcon
                                icon={Alert01Icon}
                                aria-hidden="true"
                            />
                            <AlertTitle>Media storage is full</AlertTitle>
                            <AlertDescription>
                                Remove unused files from Media before uploading
                                more.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {library.hasMore ? (
                        <p className="text-xs text-muted-foreground">
                            Showing the 60 most recent files.
                        </p>
                    ) : null}
                </div>

                {library.canUpload ? (
                    <MediaDropzone
                        enabled
                        clickToPick={
                            library.items.length === 0 && search.trim() === ''
                        }
                        className="p-4 sm:p-6"
                        onFiles={upload}
                        onPick={pickFiles}
                    >
                        {content}
                    </MediaDropzone>
                ) : (
                    <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                        {content}
                    </div>
                )}

                {library.canUpload ? (
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept={MEDIA_ACCEPT}
                        multiple
                        className="sr-only"
                        data-test="builder-media-file-input"
                        onChange={(event) => {
                            if (event.target.files) {
                                upload(event.target.files);
                                event.target.value = '';
                            }
                        }}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
