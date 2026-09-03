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
import { destroy as destroyTemplate } from '@/routes/email_templates';
import type { EmailTemplateSummary } from '@/types';

type Props = {
    teamSlug: string;
    template: EmailTemplateSummary | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteEmailTemplateModal({
    teamSlug,
    template,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const deleteTemplate = () => {
        if (!template) {
            return;
        }

        router.visit(destroyTemplate([teamSlug, template.uuid]), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
            onError: () =>
                toast.add({
                    id: 'delete-email-template-failed',
                    type: 'error',
                    title: 'Failed to delete the template.',
                }),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete template</DialogTitle>
                    <DialogDescription>
                        <strong>{template?.name}</strong> will no longer be
                        offered when composing. Campaigns already started from
                        it keep their content.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose render={<Button variant="secondary" />}>
                        Cancel
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="delete-email-template-confirm"
                        disabled={processing}
                        onClick={deleteTemplate}
                    >
                        Delete template
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
