import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { update as updateSubscriberAttributes } from '@/routes/audiences/subscribers/attributes';
import type { ContactAttribute } from '@/types/contacts';

/**
 * A date picker cannot show a value that is not Y-m-d, so such a value stays
 * in a text input instead of being cleared on save.
 */
function inputType(attribute: ContactAttribute): string {
    if (attribute.type === 'number') {
        return 'number';
    }

    const isIsoDate =
        attribute.value === null ||
        /^\d{4}-\d{2}-\d{2}$/.test(String(attribute.value));

    return attribute.type === 'date' && isIsoDate ? 'date' : 'text';
}

const PLACEHOLDERS: Record<string, string> = {
    number: '42',
    date: '2026-12-31',
};

export function SubscriberAttributesDialog({
    open,
    onOpenChange,
    routeArgs,
    audienceName,
    attributes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    routeArgs: [string, string, string];
    audienceName: string;
    attributes: ContactAttribute[];
}) {
    const form = useForm<{ attributes: Record<string, string> }>({
        attributes: Object.fromEntries(
            attributes.map((attribute) => [
                attribute.key,
                attribute.value === null ? '' : String(attribute.value),
            ]),
        ),
    });
    const errors = form.errors as Record<string, string | undefined>;

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            form.reset();
            form.clearErrors();
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(updateSubscriberAttributes.url(routeArgs), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit custom attributes</DialogTitle>
                    <DialogDescription>
                        These values belong to the {audienceName} membership and
                        are used in its merge tags.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit}>
                    <FieldGroup className="gap-5">
                        {attributes.map((attribute) => {
                            const id = `attribute-${routeArgs[2]}-${attribute.key}`;
                            const error = errors[`attributes.${attribute.key}`];

                            return (
                                <Field
                                    key={attribute.uuid}
                                    data-invalid={Boolean(error)}
                                >
                                    <FieldLabel htmlFor={id}>
                                        {attribute.name}
                                        {attribute.required ? ' *' : ''}
                                    </FieldLabel>
                                    <Input
                                        id={id}
                                        type={inputType(attribute)}
                                        placeholder={
                                            PLACEHOLDERS[attribute.type] ??
                                            attribute.name
                                        }
                                        value={
                                            form.data.attributes[
                                                attribute.key
                                            ] ?? ''
                                        }
                                        onChange={(event) =>
                                            form.setData('attributes', {
                                                ...form.data.attributes,
                                                [attribute.key]:
                                                    event.target.value,
                                            })
                                        }
                                        aria-invalid={Boolean(error)}
                                        data-test="subscriber-attribute-input"
                                    />
                                    <FieldError>{error}</FieldError>
                                </Field>
                            );
                        })}
                        {errors.attributes && (
                            <FieldError>{errors.attributes}</FieldError>
                        )}
                    </FieldGroup>
                    <DialogFooter className="mt-6">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Spinner data-icon="inline-start" />
                            )}
                            Save changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
