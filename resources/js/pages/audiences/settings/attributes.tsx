import {
    Add01Icon,
    Delete02Icon,
    Edit03Icon,
    LeftToRightListBulletIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { SettingsPanel } from '@/components/settings-panel';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AudienceSettingsLayout from '@/layouts/audiences/settings-layout';
import { update as updateAudience } from '@/routes/audiences';
import {
    destroy as destroyAttribute,
    store as storeAttribute,
    update as updateAttribute,
} from '@/routes/audiences/attributes';
import type {
    Audience,
    AudienceAttribute,
    AudienceAttributeType,
    AudienceAttributeTypeOption,
    SubscribeFormFieldMode,
} from '@/types/audiences';

type Props = {
    audience: Audience;
    attributes: AudienceAttribute[];
    attributeTypes: AudienceAttributeTypeOption[];
};

type RouteArgs = [string, string];

export default function AudienceAttributeSettings({
    audience,
    attributes,
    attributeTypes,
}: Props) {
    const { currentTeam } = usePage().props;
    const [attributeOpen, setAttributeOpen] = useState(false);
    const [attributeToEdit, setAttributeToEdit] =
        useState<AudienceAttribute | null>(null);
    const [attributeToDelete, setAttributeToDelete] =
        useState<AudienceAttribute | null>(null);

    if (!currentTeam) {
        return null;
    }

    const routeArgs: RouteArgs = [currentTeam.slug, audience.uuid];

    const openCreateAttribute = () => {
        setAttributeToEdit(null);
        setAttributeOpen(true);
    };

    const openEditAttribute = (attribute: AudienceAttribute) => {
        setAttributeToEdit(attribute);
        setAttributeOpen(true);
    };

    return (
        <AudienceSettingsLayout audience={audience}>
            <Head title={`Attributes · ${audience.name}`} />

            <div className="flex flex-col gap-8">
                <Heading
                    title="Attributes"
                    description="Configure the subscriber fields and custom data collected by this audience."
                />

                <MainAttributeSettings
                    audience={audience}
                    routeArgs={routeArgs}
                />

                <SettingsPanel
                    variant="inset"
                    title="Custom fields"
                    description="Keys are unique in this audience and cannot reuse reserved subscriber columns."
                    actions={
                        attributes.length > 0 ? (
                            <Button
                                type="button"
                                size="sm"
                                data-test="create-attribute-button"
                                onClick={openCreateAttribute}
                            >
                                <HugeiconsIcon
                                    icon={Add01Icon}
                                    data-icon="inline-start"
                                />
                                Add attribute
                            </Button>
                        ) : null
                    }
                >
                    {attributes.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <HugeiconsIcon
                                        icon={LeftToRightListBulletIcon}
                                    />
                                </EmptyMedia>
                                <EmptyTitle>No attributes yet</EmptyTitle>
                                <EmptyDescription>
                                    Add custom fields to collect extra data on
                                    subscribers in this audience.
                                </EmptyDescription>
                            </EmptyHeader>
                            <EmptyContent>
                                <Button
                                    type="button"
                                    data-test="create-attribute-button"
                                    onClick={openCreateAttribute}
                                >
                                    Add attribute
                                </Button>
                            </EmptyContent>
                        </Empty>
                    ) : (
                        <div className="p-3 sm:p-4">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Key</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Required</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {attributes.map((attribute) => (
                                        <TableRow
                                            key={attribute.uuid}
                                            data-test="attribute-row"
                                        >
                                            <TableCell className="font-medium">
                                                {attribute.name}
                                            </TableCell>
                                            <TableCell className="font-mono text-muted-foreground">
                                                {attribute.key}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {attributeTypes.find(
                                                    (option) =>
                                                        option.value ===
                                                        attribute.type,
                                                )?.label ?? attribute.type}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {attribute.required
                                                    ? 'Yes'
                                                    : 'No'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            render={
                                                                <Button
                                                                    type="button"
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    data-test="edit-attribute-button"
                                                                    aria-label={`Edit ${attribute.name}`}
                                                                    onClick={() =>
                                                                        openEditAttribute(
                                                                            attribute,
                                                                        )
                                                                    }
                                                                />
                                                            }
                                                        >
                                                            <HugeiconsIcon
                                                                icon={
                                                                    Edit03Icon
                                                                }
                                                            />
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>
                                                                Edit attribute
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            render={
                                                                <Button
                                                                    type="button"
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    data-test="delete-attribute-button"
                                                                    aria-label={`Delete ${attribute.name}`}
                                                                    onClick={() =>
                                                                        setAttributeToDelete(
                                                                            attribute,
                                                                        )
                                                                    }
                                                                />
                                                            }
                                                        >
                                                            <HugeiconsIcon
                                                                icon={
                                                                    Delete02Icon
                                                                }
                                                            />
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>
                                                                Delete attribute
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </SettingsPanel>
            </div>

            <AttributeDialog
                key={attributeToEdit?.uuid ?? 'create'}
                routeArgs={routeArgs}
                attribute={attributeToEdit}
                types={attributeTypes}
                open={attributeOpen}
                onOpenChange={setAttributeOpen}
            />

            <DeleteAttributeDialog
                routeArgs={routeArgs}
                attribute={attributeToDelete}
                open={attributeToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAttributeToDelete(null);
                    }
                }}
            />
        </AudienceSettingsLayout>
    );
}

function MainAttributeSettings({
    audience,
    routeArgs,
}: {
    audience: Audience;
    routeArgs: RouteArgs;
}) {
    const [firstNameMode, setFirstNameMode] = useState<SubscribeFormFieldMode>(
        audience.first_name_mode,
    );
    const [lastNameMode, setLastNameMode] = useState<SubscribeFormFieldMode>(
        audience.last_name_mode,
    );

    return (
        <Form
            {...updateAudience.form(routeArgs)}
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
        >
            {({ errors, processing }) => (
                <SettingsPanel
                    variant="inset"
                    title="Subscriber fields"
                    description="These core fields are shared by every subscribe form in this audience."
                >
                    <FieldGroup className="p-6 sm:p-7">
                        <input
                            type="hidden"
                            name="first_name_mode"
                            value={firstNameMode}
                        />
                        <input
                            type="hidden"
                            name="last_name_mode"
                            value={lastNameMode}
                        />

                        <Field>
                            <FieldLabel htmlFor="attribute-email">
                                Email
                            </FieldLabel>
                            <div
                                id="attribute-email"
                                className="rounded-md border bg-muted/30 px-3 py-2 text-sm"
                            >
                                Required
                            </div>
                            <FieldDescription>
                                Email is always required to identify a
                                subscriber.
                            </FieldDescription>
                        </Field>

                        <Field data-invalid={Boolean(errors.first_name_mode)}>
                            <FieldLabel htmlFor="attribute-first-name">
                                First name
                            </FieldLabel>
                            <FieldModeSelect
                                id="attribute-first-name"
                                value={firstNameMode}
                                onValueChange={setFirstNameMode}
                                disabled={processing}
                            />
                            <FieldError>{errors.first_name_mode}</FieldError>
                        </Field>

                        <Field data-invalid={Boolean(errors.last_name_mode)}>
                            <FieldLabel htmlFor="attribute-last-name">
                                Last name
                            </FieldLabel>
                            <FieldModeSelect
                                id="attribute-last-name"
                                value={lastNameMode}
                                onValueChange={setLastNameMode}
                                disabled={processing}
                            />
                            <FieldError>{errors.last_name_mode}</FieldError>
                        </Field>
                    </FieldGroup>
                    <div className="flex justify-end border-t px-6 py-5 sm:px-7">
                        <Button
                            type="submit"
                            data-test="save-subscriber-fields"
                            disabled={processing}
                        >
                            {processing && <Spinner data-icon="inline-start" />}
                            Save changes
                        </Button>
                    </div>
                </SettingsPanel>
            )}
        </Form>
    );
}

function AttributeDialog({
    routeArgs,
    attribute,
    types,
    open,
    onOpenChange,
}: {
    routeArgs: RouteArgs;
    attribute: AudienceAttribute | null;
    types: AudienceAttributeTypeOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const defaultType = attribute?.type ?? types[0]?.value ?? 'text';
    const [type, setType] = useState<AudienceAttributeType>(defaultType);

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setType(defaultType);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={`${String(open)}:${attribute?.uuid ?? 'new'}`}
                    {...(attribute
                        ? updateAttribute.form.patch([
                              ...routeArgs,
                              attribute.uuid,
                          ])
                        : storeAttribute.form(routeArgs))}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {attribute
                                        ? 'Edit attribute'
                                        : 'Add attribute'}
                                </DialogTitle>
                                <DialogDescription>
                                    Custom fields are stored with each
                                    subscriber in this audience.
                                </DialogDescription>
                            </DialogHeader>

                            <input type="hidden" name="type" value={type} />

                            <FieldGroup className="gap-5">
                                <Field data-invalid={Boolean(errors.name)}>
                                    <FieldLabel htmlFor="attribute-name">
                                        Name
                                    </FieldLabel>
                                    <Input
                                        id="attribute-name"
                                        name="name"
                                        data-test="attribute-name-input"
                                        autoFocus
                                        required
                                        maxLength={50}
                                        placeholder="Company"
                                        defaultValue={attribute?.name ?? ''}
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>

                                <Field data-invalid={Boolean(errors.key)}>
                                    <FieldLabel htmlFor="attribute-key">
                                        Key
                                    </FieldLabel>
                                    <Input
                                        id="attribute-key"
                                        name="key"
                                        data-test="attribute-key-input"
                                        maxLength={50}
                                        placeholder="company"
                                        defaultValue={attribute?.key ?? ''}
                                        aria-invalid={Boolean(errors.key)}
                                    />
                                    <FieldDescription>
                                        Used when storing this field. Generated
                                        from the name if you leave it blank.
                                    </FieldDescription>
                                    <FieldError>{errors.key}</FieldError>
                                </Field>

                                <Field data-invalid={Boolean(errors.type)}>
                                    <FieldLabel htmlFor="attribute-type">
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
                                            id="attribute-type"
                                            className="w-full"
                                            data-test="attribute-type-select"
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

                                <Field orientation="horizontal">
                                    <Checkbox
                                        id="attribute-required"
                                        name="required"
                                        defaultChecked={
                                            attribute?.required ?? false
                                        }
                                        data-test="attribute-required-checkbox"
                                    />
                                    <div className="flex flex-col gap-1">
                                        <FieldLabel
                                            htmlFor="attribute-required"
                                            className="font-normal"
                                        >
                                            Required
                                        </FieldLabel>
                                        <FieldDescription>
                                            Subscribers must provide a value
                                            when they use this form.
                                        </FieldDescription>
                                    </div>
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
                                <Button
                                    type="submit"
                                    data-test="attribute-submit"
                                    disabled={processing}
                                >
                                    {processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    {attribute
                                        ? 'Save changes'
                                        : 'Add attribute'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function FieldModeSelect({
    id,
    value,
    onValueChange,
    disabled,
}: {
    id: string;
    value: SubscribeFormFieldMode;
    onValueChange: (value: SubscribeFormFieldMode) => void;
    disabled: boolean;
}) {
    const items = [
        { value: 'hidden', label: 'Hidden' },
        { value: 'optional', label: 'Optional' },
        { value: 'required', label: 'Required' },
    ];

    return (
        <Select
            value={value}
            onValueChange={(next) => {
                if (next) {
                    onValueChange(next as SubscribeFormFieldMode);
                }
            }}
            disabled={disabled}
        >
            <SelectTrigger id={id} className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    {items.map((item) => (
                        <SelectItem key={item.value} value={item.value}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}

function DeleteAttributeDialog({
    routeArgs,
    attribute,
    open,
    onOpenChange,
}: {
    routeArgs: RouteArgs;
    attribute: AudienceAttribute | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Delete {attribute?.name}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        This removes the field from the audience. Existing
                        subscriber values for this field will no longer be
                        available.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        data-test="delete-attribute-confirm"
                        disabled={!attribute}
                        onClick={() => {
                            if (!attribute) {
                                return;
                            }

                            router.delete(
                                destroyAttribute.url([
                                    ...routeArgs,
                                    attribute.uuid,
                                ]),
                                { preserveScroll: true },
                            );
                        }}
                    >
                        Delete attribute
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
