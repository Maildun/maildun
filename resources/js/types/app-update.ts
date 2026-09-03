export type AppUpdateStatus = {
    status:
        'current' | 'disabled' | 'unknown' | 'unsupported' | 'update_available';
    current_version: string;
    latest_version: string | null;
    minimum_version: string | null;
    release_url: string | null;
    notes_url: string | null;
    checked_at: string | null;
};
