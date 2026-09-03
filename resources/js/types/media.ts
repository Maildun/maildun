export type MediaStatus = 'processing' | 'ready' | 'failed';

export type MediaTaxonomy = {
    uuid: string;
    name: string;
};

export type MediaItem = {
    uuid: string;
    name: string;
    alt: string | null;
    url: string | null;
    absolute_url: string | null;
    mime_type: string | null;
    extension: string | null;
    size: number;
    size_label: string;
    width: number | null;
    height: number | null;
    status: MediaStatus;
    failed_reason: string | null;
    processing: boolean;
    created_at: string | null;
    category: MediaTaxonomy | null;
    tags: MediaTaxonomy[];
};

export type MediaFilters = {
    q: string;
    category: string;
    tag: string;
};
