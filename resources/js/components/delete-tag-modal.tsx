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
import { bulkDestroy, destroy as destroyTag } from '@/routes/tags';
import type { Team, TeamTag } from '@/types';

type Props = {
    team: Team;
    tags: TeamTag[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDeleted?: () => void;
};

export default function DeleteTagModal({
    team,
    tags,
    open,
    onOpenChange,
    onDeleted,
}: Props) {
    const [processing, setProcessing] = useState(false);
    const isBulk = tags.length > 1;
    const tag = tags[0];

    const deleteTags = () => {
        if (!tag) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                onDeleted?.();
                onOpenChange(false);
            },
            onError: () =>
                toast.add({
                    type: 'error',
                    title: isBulk
                        ? 'Failed to delete the selected tags.'
                        : 'Failed to delete the tag.',
                }),
        };

        if (isBulk) {
            router.delete(bulkDestroy.url(team.slug), {
                data: { ids: tags.map((item) => item.uuid) },
                ...options,
            });

            return;
        }

        router.visit(destroyTag([team.slug, tag.uuid]), options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isBulk ? 'Delete tags' : 'Delete tag'}
                    </DialogTitle>
                    <DialogDescription>
                        {isBulk ? (
                            <>
                                {tags.length} tags will be removed from any
                                subscribers using them. The subscribers
                                themselves are not deleted.
                            </>
                        ) : (
                            <>
                                <strong>{tag?.name}</strong> will be removed
                                from {tag?.subscribers_count ?? 0} subscriber
                                {tag?.subscribers_count === 1 ? '' : 's'}. The
                                subscribers themselves are not deleted.
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose render={<Button variant="secondary" />}>
                        Cancel
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="delete-tag-confirm"
                        disabled={processing}
                        onClick={deleteTags}
                    >
                        {isBulk ? 'Delete tags' : 'Delete tag'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
