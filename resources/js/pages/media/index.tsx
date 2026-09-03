import {
    ArrowUp03Icon,
    Folder01Icon,
    GridViewIcon,
    Image01Icon,
    ListViewIcon,
    Settings02Icon,
    TagsIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ActiveFilters } from '@/components/active-filters';
import DeleteMediaModal from '@/components/delete-media-modal';
import { FilterMenu } from '@/components/filter-menu';
import { ListSearch } from '@/components/list-search';
import MediaDetailDialog from '@/components/media-detail-dialog';
import MediaDropzone from '@/components/media-dropzone';
import { Paginator } from '@/components/paginator';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useListFilters } from '@/hooks/use-list-filters';
import { MEDIA_ACCEPT, useMediaUpload } from '@/hooks/use-media-upload';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/media';
import { edit as mediaSettings } from '@/routes/media/settings';
import type { MediaFilters, MediaItem, MediaTaxonomy } from '@/types';
import type { Paginated } from '@/types/audiences';

type MediaView = 'grid' | 'list';

const MEDIA_VIEW_STORAGE_KEY = 'media-view';

function getStoredMediaView(): MediaView {
    if (typeof window === 'undefined') {
        return 'grid';
    }

    return localStorage.getItem(MEDIA_VIEW_STORAGE_KEY) === 'list'
        ? 'list'
        : 'grid';
}

function MediaThumbnail({
    item,
    className,
}: {
    item: MediaItem;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative shrink-0 overflow-hidden rounded-2xl bg-muted',
                className,
            )}
        >
            {item.processing ? (
                <div className="grid size-full place-items-center">
                    <Skeleton className="absolute inset-0 rounded-none" />
                    <Spinner />
                </div>
            ) : item.url ? (
                <img
                    src={item.url}
                    alt={item.alt ?? item.name}
                    className="size-full object-cover"
                />
            ) : (
                <div className="grid size-full place-items-center p-3 text-center text-xs text-muted-foreground">
                    {item.status === 'failed' ? 'Failed' : 'No preview'}
                </div>
            )}
            {item.status !== 'ready' ? (
                <Badge
                    className="absolute top-1 left-1"
                    variant={
                        item.status === 'failed' ? 'destructive' : 'secondary'
                    }
                >
                    {item.status === 'processing' ? 'Processing' : 'Failed'}
                </Badge>
            ) : null}
        </div>
    );
}

type Props = {
    media: Paginated<MediaItem>;
    filters: MediaFilters;
    categories: MediaTaxonomy[];
    tags: MediaTaxonomy[];
    convertUploadsToWebp: boolean;
    canManage: boolean;
};

export default function MediaIndex({
    media,
    filters,
    categories,
    tags,
    convertUploadsToWebp,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [selectedUuid, setSelectedUuid] = useState<string | null>(null);
    const [mediaToDelete, setMediaToDelete] = useState<MediaItem | null>(null);
    const [view, setView] = useState<MediaView>(getStoredMediaView);
    const selected =
        media.data.find((item) => item.uuid === selectedUuid) ?? null;
    const processing = media.data.some((item) => item.processing);
    const { start, stop } = usePoll(
        2000,
        { only: ['media'] },
        { autoStart: false },
    );
    const { upload, uploading } = useMediaUpload(currentTeam?.slug ?? '', {
        convertUploadsToWebp,
    });

    const {
        visit: visitIndex,
        clear: clearFilters,
        clearAll: clearAllFilters,
        hasActiveFilters,
        searchKey,
    } = useListFilters<MediaFilters>({
        url: currentTeam ? index.url(currentTeam.slug) : '',
        filters,
        empty: { q: '', category: '', tag: '' },
    });

    useEffect(() => {
        if (processing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [processing, start, stop]);

    useEffect(() => {
        localStorage.setItem(MEDIA_VIEW_STORAGE_KEY, view);
    }, [view]);

    if (!currentTeam) {
        return null;
    }

    const categoryFilterLabel =
        filters.category === 'uncategorized'
            ? 'Uncategorized'
            : (categories.find((category) => category.uuid === filters.category)
                  ?.name ?? filters.category);
    const tagFilterLabel =
        tags.find((tag) => tag.uuid === filters.tag)?.name ?? filters.tag;

    const pickFiles = () => {
        if (uploading) {
            return;
        }

        fileInputRef.current?.click();
    };

    const library = (
        <>
            {media.data.length === 0 ? (
                <Empty className="h-full">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <HugeiconsIcon icon={Image01Icon} />
                        </EmptyMedia>
                        <EmptyTitle>No media yet</EmptyTitle>
                        <EmptyDescription>
                            {hasActiveFilters
                                ? 'No files match those filters.'
                                : canManage
                                  ? 'Drop images here or click to browse. You can add more than one at a time.'
                                  : 'Files uploaded by your team will appear here.'}
                        </EmptyDescription>
                    </EmptyHeader>
                    {canManage && !hasActiveFilters && (
                        <EmptyContent>
                            <Button
                                data-test="upload-media-button"
                                onClick={pickFiles}
                            >
                                Upload media
                            </Button>
                        </EmptyContent>
                    )}
                </Empty>
            ) : view === 'grid' ? (
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {media.data.map((item, itemIndex) => (
                        <button
                            key={item.uuid}
                            type="button"
                            data-test="media-tile"
                            onClick={() => setSelectedUuid(item.uuid)}
                            style={{
                                animationDelay: `${Math.min(itemIndex, 11) * 30}ms`,
                            }}
                            className="group flex animate-in flex-col rounded-3xl bg-card p-2 text-left shadow-xs ring-1 ring-foreground/10 transition-shadow duration-300 fade-in-0 zoom-in-95 hover:shadow-md"
                        >
                            <MediaThumbnail
                                item={item}
                                className="aspect-video"
                            />
                            <span className="flex min-w-0 flex-col gap-0.5 px-3 pt-2.5 pb-2.5">
                                <span className="truncate text-sm font-medium tracking-tight">
                                    {item.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {mediaCardDescription(item)}
                                </span>
                                <span className="mt-1.5 truncate text-xs text-muted-foreground">
                                    {mediaCardMeta(item)}
                                </span>
                            </span>
                        </button>
                    ))}
                </div>
            ) : (
                <div className="flex flex-col gap-2">
                    {media.data.map((item, itemIndex) => (
                        <button
                            key={item.uuid}
                            type="button"
                            data-test="media-tile"
                            onClick={() => setSelectedUuid(item.uuid)}
                            style={{
                                animationDelay: `${Math.min(itemIndex, 11) * 30}ms`,
                            }}
                            className="group flex animate-in items-center gap-3 rounded-2xl bg-card p-2 text-left shadow-xs ring-1 ring-foreground/10 transition-shadow duration-300 fade-in-0 slide-in-from-bottom-1 hover:shadow-md"
                        >
                            <MediaThumbnail item={item} className="size-14" />
                            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span className="truncate text-sm font-medium tracking-tight">
                                    {item.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {mediaCardDescription(item)}
                                </span>
                            </span>
                            <span className="shrink-0 pr-2 text-xs text-muted-foreground">
                                {mediaCardMeta(item)}
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </>
    );

    return (
        <>
            <Head title="Media" />
            <div className="flex min-h-0 flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Media
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Upload and manage images this team can use in
                            emails. Add up to 20 images at once, with a 2 MB
                            limit per image.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            nativeButton={false}
                            data-test="media-settings-button"
                            render={
                                <Link
                                    href={mediaSettings(currentTeam.slug)}
                                    prefetch
                                />
                            }
                        >
                            <HugeiconsIcon
                                icon={Settings02Icon}
                                data-icon="inline-start"
                            />
                            Settings
                        </Button>
                        {canManage && (
                            <Button
                                data-test="upload-media-button"
                                disabled={uploading}
                                onClick={pickFiles}
                            >
                                <HugeiconsIcon
                                    icon={ArrowUp03Icon}
                                    data-icon="inline-start"
                                />
                                Upload
                            </Button>
                        )}
                    </div>
                </div>

                {media.data.length > 0 || hasActiveFilters ? (
                    <div className="flex flex-col gap-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <FilterMenu
                                testId="media-filter-button"
                                fields={[
                                    {
                                        key: 'category',
                                        icon: Folder01Icon,
                                        label: 'Category',
                                        value: filters.category,
                                        allLabel: 'All categories',
                                        options: [
                                            {
                                                value: 'uncategorized',
                                                label: 'Uncategorized',
                                            },
                                            ...categories.map((category) => ({
                                                value: category.uuid,
                                                label: category.name,
                                            })),
                                        ],
                                        onValueChange: (category) =>
                                            visitIndex({ category }),
                                        testId: 'media-category-filter',
                                    },
                                    {
                                        key: 'tag',
                                        icon: TagsIcon,
                                        label: 'Tag',
                                        value: filters.tag,
                                        allLabel: 'All tags',
                                        options: tags.map((tag) => ({
                                            value: tag.uuid,
                                            label: tag.name,
                                        })),
                                        onValueChange: (tag) =>
                                            visitIndex({ tag }),
                                        testId: 'media-tag-filter',
                                    },
                                ]}
                            />
                            <div className="flex items-center gap-2">
                                <ListSearch
                                    key={searchKey}
                                    value={filters.q}
                                    onSearch={(q) => visitIndex({ q })}
                                    placeholder="Search media"
                                />
                                <div className="flex items-center gap-0.5 rounded-md border border-border bg-background p-0.5 shadow-xs dark:border-input dark:bg-input/30">
                                    <Button
                                        type="button"
                                        variant={
                                            view === 'grid'
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        size="icon-sm"
                                        aria-pressed={view === 'grid'}
                                        aria-label="Grid view"
                                        data-test="media-view-grid"
                                        onClick={() => setView('grid')}
                                    >
                                        <HugeiconsIcon icon={GridViewIcon} />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            view === 'list'
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        size="icon-sm"
                                        aria-pressed={view === 'list'}
                                        aria-label="List view"
                                        data-test="media-view-list"
                                        onClick={() => setView('list')}
                                    >
                                        <HugeiconsIcon icon={ListViewIcon} />
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <ActiveFilters
                            filters={[
                                ...(filters.q !== ''
                                    ? [
                                          {
                                              key: 'q',
                                              field: 'Search',
                                              value: filters.q,
                                              onClear: () =>
                                                  clearFilters({ q: '' }),
                                          },
                                      ]
                                    : []),
                                ...(filters.category !== ''
                                    ? [
                                          {
                                              key: 'category',
                                              field: 'Category',
                                              value: categoryFilterLabel,
                                              onClear: () =>
                                                  clearFilters({
                                                      category: '',
                                                  }),
                                          },
                                      ]
                                    : []),
                                ...(filters.tag !== ''
                                    ? [
                                          {
                                              key: 'tag',
                                              field: 'Tag',
                                              value: tagFilterLabel,
                                              onClear: () =>
                                                  clearFilters({ tag: '' }),
                                          },
                                      ]
                                    : []),
                            ]}
                            onClearAll={clearAllFilters}
                            clearTestId="clear-media-filters"
                        />
                    </div>
                ) : null}

                {canManage ? (
                    <MediaDropzone
                        enabled
                        clickToPick={media.data.length === 0}
                        onFiles={upload}
                        onPick={pickFiles}
                    >
                        {library}
                    </MediaDropzone>
                ) : (
                    library
                )}

                <Paginator paginator={media} />
            </div>

            {canManage && (
                <input
                    ref={fileInputRef}
                    type="file"
                    accept={MEDIA_ACCEPT}
                    multiple
                    className="sr-only"
                    data-test="media-file-input"
                    onChange={(event) => {
                        if (event.target.files) {
                            upload(event.target.files);
                            event.target.value = '';
                        }
                    }}
                />
            )}

            <MediaDetailDialog
                teamSlug={currentTeam.slug}
                media={selected}
                categories={categories}
                tags={tags}
                canManage={canManage}
                open={selected !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setSelectedUuid(null);
                    }
                }}
                onDelete={(item) => {
                    setSelectedUuid(null);
                    setMediaToDelete(item);
                }}
            />

            {canManage && (
                <DeleteMediaModal
                    teamSlug={currentTeam.slug}
                    media={mediaToDelete}
                    open={mediaToDelete !== null}
                    onOpenChange={(nextOpen) =>
                        setMediaToDelete(nextOpen ? mediaToDelete : null)
                    }
                />
            )}
        </>
    );
}

function mediaCardDescription(item: MediaItem): string {
    if (item.alt) {
        return item.alt;
    }

    if (item.category) {
        return item.category.name;
    }

    if (item.width && item.height) {
        return `${item.width} × ${item.height}`;
    }

    return item.size_label;
}

function mediaCardMeta(item: MediaItem): string {
    const kind = item.extension ? item.extension.toUpperCase() : 'Image';

    if (item.created_at) {
        return `${kind} · ${formatRelativeTime(item.created_at)}`;
    }

    return kind;
}

MediaIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Media',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
