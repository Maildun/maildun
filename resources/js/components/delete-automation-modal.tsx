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
import { destroy as destroyAutomation } from '@/routes/automations';

type Props = {
    teamSlug: string;
    automation: { uuid: string; name: string } | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteAutomationModal({
    teamSlug,
    automation,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const deleteAutomation = () => {
        if (!automation) {
            return;
        }

        router.visit(destroyAutomation([teamSlug, automation.uuid]), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
            onError: () =>
                toast.add({
                    type: 'error',
                    title: 'Failed to delete the automation.',
                }),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete automation</DialogTitle>
                    <DialogDescription>
                        <strong>{automation?.name}</strong> will be removed and
                        every run still in flight is cancelled. This cannot be
                        undone from the app.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose render={<Button variant="secondary" />}>
                        Cancel
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="delete-automation-confirm"
                        disabled={processing}
                        onClick={deleteAutomation}
                    >
                        Delete automation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
