import { useForm } from '@inertiajs/react';
import { TagsCombobox } from '@/components/tags-combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { bulkTags } from '@/routes/audiences/subscribers';
import type { Tag } from '@/types/audiences';

type BulkTagAction = 'add' | 'remove';

type Props = {
    routeArgs: [string, string];
    ids: string[];
    tags: Tag[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess: () => void;
};

export function BulkSubscriberTagsDialog({
    routeArgs,
    ids,
    tags,
    open,
    onOpenChange,
    onSuccess,
}: Props) {
    const form = useForm<{
        ids: string[];
        action: BulkTagAction;
        tags: string[];
    }>({ ids: [], action: 'add', tags: [] });

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            form.reset();
            form.clearErrors();
        }
    };

    const submit = (action: BulkTagAction) => {
        form.transform((data) => ({ ...data, ids, action }));
        form.setData('action', action);
        form.patch(bulkTags.url(routeArgs), {
            preserveScroll: true,
            onSuccess: () => {
                handleOpenChange(false);
                onSuccess();
            },
        });
    };

    const busy = (action: BulkTagAction) =>
        form.processing && form.data.action === action;

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit tags</DialogTitle>
                    <DialogDescription>
                        Add or remove tags for {ids.length.toLocaleString()}{' '}
                        {ids.length === 1 ? 'subscriber' : 'subscribers'}. Tags
                        belong to the contact, so they apply in every audience.
                    </DialogDescription>
                </DialogHeader>

                <Field data-invalid={Boolean(form.errors.tags)}>
                    <FieldLabel>Tags</FieldLabel>
                    <TagsCombobox
                        value={form.data.tags}
                        availableTags={tags}
                        onValueChange={(next) => form.setData('tags', next)}
                        aria-invalid={Boolean(form.errors.tags)}
                    />
                    <FieldError>{form.errors.tags}</FieldError>
                </Field>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={
                            form.processing || form.data.tags.length === 0
                        }
                        onClick={() => submit('remove')}
                        data-test="bulk-tags-remove"
                    >
                        {busy('remove') && <Spinner />}
                        Remove tags
                    </Button>
                    <Button
                        type="button"
                        disabled={
                            form.processing || form.data.tags.length === 0
                        }
                        onClick={() => submit('add')}
                        data-test="bulk-tags-add"
                    >
                        {busy('add') && <Spinner />}
                        Add tags
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
