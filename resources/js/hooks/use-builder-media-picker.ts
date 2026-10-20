import type {
    EmailEditorHandle,
    ImageResult,
} from '@maildun/email-builder/editor';
import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import { toast } from '@/components/ui/toast';
import { useUnmountSignal } from '@/hooks/use-unmount-signal';
import { uploadEditorImage } from '@/lib/editor-media-upload';
import { insertBuilderMedia } from '@/lib/email-builder';
import type { MediaItem } from '@/types/media';

type BuilderMediaPickerOptions = {
    builderRef: RefObject<EmailEditorHandle | null>;
    /** Whether the design can be changed right now. */
    canEdit: boolean;
    /** Whether the page loaded the media library to pick from. */
    hasMediaLibrary: boolean;
    /** The workspace that image blocks upload to, or null when uploads are off. */
    uploadTeamSlug: string | null;
};

/**
 * The media wiring every builder page shares: the media library dialog, an
 * image block asking for a picture, inserting a library image into the
 * design, and uploading from an image block.
 */
export function useBuilderMediaPicker({
    builderRef,
    canEdit,
    hasMediaLibrary,
    uploadTeamSlug,
}: BuilderMediaPickerOptions) {
    const [mediaDialogOpen, setMediaDialogOpen] = useState(false);
    const imagePickerResolve = useRef<
        ((image: ImageResult | null) => void) | null
    >(null);
    const unmountSignal = useUnmountSignal();

    useEffect(() => {
        return () => imagePickerResolve.current?.(null);
    }, []);

    const changeMediaDialogOpen = (open: boolean) => {
        if (!open) {
            imagePickerResolve.current?.(null);
            imagePickerResolve.current = null;
        }

        setMediaDialogOpen(open);
    };

    const pickBuilderImage = (): Promise<ImageResult | null> => {
        if (!canEdit || !hasMediaLibrary) {
            return Promise.resolve(null);
        }

        imagePickerResolve.current?.(null);

        return new Promise((resolve) => {
            imagePickerResolve.current = resolve;
            setMediaDialogOpen(true);
        });
    };

    const insertMedia = (image: MediaItem): boolean => {
        if (!canEdit || image.status !== 'ready' || !image.absolute_url) {
            return false;
        }

        if (imagePickerResolve.current) {
            imagePickerResolve.current({
                url: image.absolute_url,
                alt: image.alt ?? image.name,
            });
            imagePickerResolve.current = null;

            return true;
        }

        if (!builderRef.current) {
            return false;
        }

        const inserted = insertBuilderMedia(builderRef.current.store, image);

        if (!inserted) {
            toast.add({
                type: 'error',
                title: 'Could not insert this image. Try again.',
            });
        }

        return inserted;
    };

    const uploadBuilderImage =
        uploadTeamSlug === null
            ? undefined
            : (file: File) =>
                  uploadEditorImage(uploadTeamSlug, file, unmountSignal());

    return {
        mediaDialogOpen,
        setMediaDialogOpen,
        changeMediaDialogOpen,
        pickBuilderImage,
        insertMedia,
        uploadBuilderImage,
    };
}
