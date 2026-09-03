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
import { destroy as destroyEmail } from '@/routes/emails';

type Props = {
    teamSlug: string;
    email: { uuid: string; name: string } | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteEmailModal({
    teamSlug,
    email,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const deleteEmail = () => {
        if (!email) {
            return;
        }

        router.visit(destroyEmail([teamSlug, email.uuid]), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
            onError: () =>
                toast.add({
                    id: 'delete-email-failed',
                    type: 'error',
                    title: 'Failed to delete the campaign.',
                }),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete campaign</DialogTitle>
                    <DialogDescription>
                        <strong>{email?.name}</strong> and its content will be
                        removed. This cannot be undone from the app.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose render={<Button variant="secondary" />}>
                        Cancel
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="delete-email-confirm"
                        disabled={processing}
                        onClick={deleteEmail}
                    >
                        Delete campaign
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
