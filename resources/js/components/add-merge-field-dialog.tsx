import { Form } from '@inertiajs/react';
import type { MergeTag } from '@maildun/email-builder/editor';
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
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store as storeAttribute } from '@/routes/audiences/attributes';
import type {
    AudienceAttributeType,
    AudienceAttributeTypeOption,
} from '@/types/audiences';

type AudienceFields = {
    uuid: string;
    name: string;
    attributes: { name: string; key: string }[];
};

/**
 * Adds a custom field to the campaign's audience from the email builder's
 * "/" menu, then hands the new merge tag back to the editor to insert.
 */
export function AddMergeFieldDialog({
    teamSlug,
    audience,
    types,
    open,
    onOpenChange,
    onCreated,
}: {
    teamSlug: string;
    audience: AudienceFields;
    types: AudienceAttributeTypeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (tag: MergeTag) => void;
}) {
    const [type, setType] = useState<AudienceAttributeType>(
        types[0]?.value ?? 'text',
    );
    const existingKeys = new Set(
        audience.attributes.map((attribute) => attribute.key),
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...storeAttribute.form([teamSlug, audience.uuid])}
                    options={{
                        preserveScroll: true,
                        preserveState: true,
                        only: ['audiences'],
                    }}
                    className="space-y-6"
                    onSuccess={(page) => {
                        const audiences = page.props.audiences as
                            AudienceFields[] | undefined;
                        const created = audiences
                            ?.find(
                                (candidate) => candidate.uuid === audience.uuid,
                            )
                            ?.attributes.find(
                                (attribute) => !existingKeys.has(attribute.key),
                            );

                        if (created) {
                            onCreated({
                                key: created.key,
                                label: created.name,
                            });
                        }
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Add a field</DialogTitle>
                                <DialogDescription>
                                    Stored with each subscriber in{' '}
                                    {audience.name}, and inserted here as a
                                    merge tag.
                                </DialogDescription>
                            </DialogHeader>

                            <input type="hidden" name="type" value={type} />

                            <FieldGroup className="gap-5">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="merge-field-name">
                                        Name
                                    </FieldLabel>
                                    <Input
                                        id="merge-field-name"
                                        name="name"
                                        autoFocus
                                        required
                                        maxLength={50}
                                        placeholder="Plan"
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>

                                <Field data-invalid={Boolean(errors.key)}>
                                    <FieldLabel htmlFor="merge-field-key">
                                        Key
                                    </FieldLabel>
                                    <Input
                                        id="merge-field-key"
                                        name="key"
                                        maxLength={50}
                                        placeholder="plan"
                                        aria-invalid={Boolean(errors.key)}
                                    />
                                    <FieldDescription>
                                        The merge tag, e.g. {'{{ plan }}'}.
                                        Generated from the name if you leave it
                                        blank.
                                    </FieldDescription>
                                    <FieldError>{errors.key}</FieldError>
                                </Field>

                                <Field data-invalid={Boolean(errors.type)}>
                                    <FieldLabel htmlFor="merge-field-type">
                                        Type
                                    </FieldLabel>
                                    <Select
                                        value={type}
                                        onValueChange={(value) =>
                                            setType(
                                                value as AudienceAttributeType,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="merge-field-type"
                                            className="w-full"
                                            aria-invalid={Boolean(errors.type)}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {types.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldError>{errors.type}</FieldError>
                                </Field>
                            </FieldGroup>

                            <DialogFooter className="gap-2">
                                <DialogClose
                                    render={
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        />
                                    }
                                >
                                    Cancel
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Add field
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
