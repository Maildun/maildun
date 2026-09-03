import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from '@/components/ui/toast';
import { destroy as destroyMedia } from '@/routes/media';

type Props = {
    teamSlug: string;
    media: { uuid: string; name: string } | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteMediaModal({
    teamSlug,
    media,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const deleteMedia = () => {
        if (!media) {
            return;
        }

        router.visit(destroyMedia([teamSlug, media.uuid]), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
            onError: () =>
                toast.add({
                    type: 'error',
                    title: 'Failed to delete the file.',
                }),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete media</DialogTitle>
                    <DialogDescription>
                        <strong>{media?.name}</strong> will be removed from this
                        team. This cannot be undone from the app.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose render={<Button variant="secondary" />}>
                        Cancel
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="delete-media-confirm"
                        disabled={processing}
                        onClick={deleteMedia}
                    >
                        Delete media
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
