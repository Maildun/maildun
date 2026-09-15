import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from '@/components/ui/toast';
import {
    firstUploadErrorMessage,
    useUploadToast,
} from '@/hooks/use-upload-toast';
import { store } from '@/routes/media';

export const MEDIA_ACCEPT =
    'image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp';

export const MEDIA_MAX_FILES = 20;
export const MEDIA_MAX_BYTES = 2 * 1024 * 1024;

export function imageFilesFrom(list: FileList | File[]): File[] {
    return Array.from(list).filter((file) => file.type.startsWith('image/'));
}

export function useMediaUpload(
    teamSlug: string,
    options: {
        convertUploadsToWebp?: boolean;
        only?: string[];
    } = {},
) {
    const [uploading, setUploading] = useState(false);
    const uploadToast = useUploadToast();

    const upload = (list: FileList | File[]) => {
        if (teamSlug === '' || uploading) {
            return;
        }

        let files = imageFilesFrom(list);

        if (files.length === 0) {
            toast.add({
                type: 'error',
                title: 'Drop JPEG, PNG, GIF, or WebP images.',
            });

            return;
        }

        if (files.length > MEDIA_MAX_FILES) {
            toast.add({
                type: 'info',
                title: `Only the first ${MEDIA_MAX_FILES} images will be uploaded.`,
            });
            files = files.slice(0, MEDIA_MAX_FILES);
        }

        const oversizedCount = files.filter(
            (file) => file.size > MEDIA_MAX_BYTES,
        ).length;

        if (oversizedCount > 0) {
            toast.add({
                type: 'error',
                title: 'Each image must be 2 MB or smaller.',
                description:
                    oversizedCount === 1
                        ? 'One selected image is too large.'
                        : `${oversizedCount} selected images are too large.`,
            });

            return;
        }

        const count = files.length;

        router.post(
            store.url(teamSlug),
            { files },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                only: options.only,
                onStart: () => {
                    setUploading(true);
                    uploadToast.begin({
                        title:
                            count === 1
                                ? 'Uploading image…'
                                : `Uploading ${count} images…`,
                        files,
                        hint: `Up to ${MEDIA_MAX_FILES} images, 2 MB each.`,
                    });
                },
                onProgress: (event) => uploadToast.setProgress(event),
                onSuccess: () =>
                    uploadToast.succeed({
                        title:
                            count === 1
                                ? 'Image uploaded.'
                                : `${count} images uploaded.`,
                        description: options.convertUploadsToWebp
                            ? 'JPEG and PNG are being optimized.'
                            : undefined,
                    }),
                onError: (errors) =>
                    uploadToast.fail({
                        title: firstUploadErrorMessage(
                            errors,
                            'Failed to upload media.',
                        ),
                    }),
                onHttpException: () => {
                    uploadToast.fail({ title: 'Failed to upload media.' });

                    return false;
                },
                onNetworkError: () => {
                    uploadToast.fail({ title: 'Failed to upload media.' });

                    return false;
                },
                onCancel: () =>
                    uploadToast.fail({ title: 'Upload cancelled.' }),
                onFinish: () => setUploading(false),
            },
        );
    };

    return { upload, uploading };
}
