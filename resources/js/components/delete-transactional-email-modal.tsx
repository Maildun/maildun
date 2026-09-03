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
import { destroy as destroyTransactionalEmail } from '@/routes/transactional_emails';

type Props = {
    teamSlug: string;
    email: { uuid: string; name: string } | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteTransactionalEmailModal({
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

        router.visit(destroyTransactionalEmail([teamSlug, email.uuid]), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
            onError: () =>
                toast.add({
                    type: 'error',
                    title: 'Failed to delete the transactional email.',
                }),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete transactional email</DialogTitle>
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
                        data-test="delete-transactional-email-confirm"
                        disabled={processing}
                        onClick={deleteEmail}
                    >
                        Delete transactional email
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
