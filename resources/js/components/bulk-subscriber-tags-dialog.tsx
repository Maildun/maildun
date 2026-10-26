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
import { tagsError } from '@/lib/tags';
import type { Tag } from '@/types/audiences';

type BulkTagAction = 'add' | 'remove';

type Props = {
    /** The bulk-tags endpoint: an audience's subscribers or the contacts. */
    url: string;
    ids: string[];
    /** Singular and plural names for the selected rows. */
    noun?: [string, string];
    tags: Tag[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSuccess: () => void;
};

export function BulkSubscriberTagsDialog({
    url,
    ids,
    noun = ['subscriber', 'subscribers'],
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
        form.patch(url, {
            preserveScroll: true,
            onSuccess: () => {
                handleOpenChange(false);
                onSuccess();
            },
        });
    };

    const busy = (action: BulkTagAction) =>
        form.processing && form.data.action === action;
    const idsError =
        form.errors.ids ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('ids.'),
        )?.[1];

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit tags</DialogTitle>
                    <DialogDescription>
                        Add or remove tags for {ids.length.toLocaleString()}{' '}
                        {ids.length === 1 ? noun[0] : noun[1]}. Tags belong to
                        the contact, so they apply in every audience.
                    </DialogDescription>
                </DialogHeader>

                <Field data-invalid={Boolean(tagsError(form.errors))}>
                    <FieldLabel>Tags</FieldLabel>
                    <TagsCombobox
                        value={form.data.tags}
                        availableTags={tags}
                        onValueChange={(next) => form.setData('tags', next)}
                        aria-invalid={Boolean(tagsError(form.errors))}
                    />
                    <FieldError>{tagsError(form.errors)}</FieldError>
                </Field>
                {idsError && (
                    <FieldError data-test="bulk-tags-ids-error">
                        {idsError}
                    </FieldError>
                )}

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
