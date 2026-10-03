import {
    Cancel01Icon,
    CloudUploadIcon,
    Csv01Icon,
    Download04Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { DragEvent, FormEvent } from 'react';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatFileSize } from '@/lib/format';
import { cn } from '@/lib/utils';
import { store } from '@/routes/contacts/imports';

type Props = {
    audiences: { uuid: string; name: string }[];
    selectedAudience: string | null;
    canImportContacts: boolean;
};

const WORKSPACE_ONLY = 'workspace';

const SAMPLE_CSV = [
    'email,first_name,last_name,tags',
    'ada@example.com,Ada,Lovelace,"customer,newsletter"',
    'grace@example.com,Grace,Hopper,customer',
].join('\n');

export default function ContactImportCreate({
    audiences,
    selectedAudience,
    canImportContacts,
}: Props) {
    const { currentTeam } = usePage().props;
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const form = useForm<{ file: File | null; audience: string }>({
        file: null,
        audience: selectedAudience ?? '',
    });

    if (!currentTeam) {
        return null;
    }

    const audienceItems = [
        ...(canImportContacts
            ? [{ value: WORKSPACE_ONLY, label: 'Workspace contacts only' }]
            : []),
        ...audiences.map((audience) => ({
            value: audience.uuid,
            label: audience.name,
        })),
    ];

    const pickFile = (files: FileList | null) => {
        const file = files?.[0];

        if (file) {
            form.setData('file', file);
            form.clearErrors('file');
        }
    };

    const onDrop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setDragging(false);
        pickFile(event.dataTransfer.files);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(currentTeam.slug), { forceFormData: true });
    };

    return (
        <>
            <Head title="New import" />

            <form
                onSubmit={submit}
                className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6"
            >
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New import
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Upload a CSV, then match its columns to contact fields
                        before anything is imported.
                    </p>
                </div>

                <SettingsPanel
                    variant="inset"
                    title="Choose a file"
                    description="Comma, semicolon, tab and pipe separated files are detected automatically. Up to 10 MB."
                >
                    <FieldGroup className="gap-5 p-6 sm:p-7">
                        <Field data-invalid={Boolean(form.errors.file)}>
                            <input
                                ref={inputRef}
                                id="contact-import-file"
                                type="file"
                                accept=".csv,text/csv,text/plain"
                                className="sr-only"
                                onChange={(event) =>
                                    pickFile(event.currentTarget.files)
                                }
                            />
                            {form.data.file ? (
                                <div className="flex items-center gap-3 rounded-lg border p-4">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                        <HugeiconsIcon icon={Csv01Icon} />
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span className="truncate font-medium">
                                            {form.data.file.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatFileSize(
                                                form.data.file.size,
                                            )}
                                        </span>
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remove file"
                                        disabled={form.processing}
                                        onClick={() => {
                                            form.setData('file', null);

                                            if (inputRef.current) {
                                                inputRef.current.value = '';
                                            }
                                        }}
                                    >
                                        <HugeiconsIcon icon={Cancel01Icon} />
                                    </Button>
                                </div>
                            ) : (
                                <label
                                    htmlFor="contact-import-file"
                                    onDragOver={(event) => {
                                        event.preventDefault();
                                        setDragging(true);
                                    }}
                                    onDragLeave={() => setDragging(false)}
                                    onDrop={onDrop}
                                    className={cn(
                                        'flex cursor-pointer flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-10 text-center transition-colors hover:bg-muted/50',
                                        dragging &&
                                            'border-primary bg-muted/50',
                                    )}
                                    data-test="import-dropzone"
                                >
                                    <span className="flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                        <HugeiconsIcon icon={CloudUploadIcon} />
                                    </span>
                                    <span className="flex flex-col gap-0.5">
                                        <span className="font-medium">
                                            Drop a CSV here, or choose a file
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            The first row must hold column
                                            names.
                                        </span>
                                    </span>
                                </label>
                            )}
                            <FieldError>{form.errors.file}</FieldError>
                        </Field>

                        {audienceItems.length > 1 ||
                        (audienceItems.length === 1 && !canImportContacts) ? (
                            <Field data-invalid={Boolean(form.errors.audience)}>
                                <FieldLabel htmlFor="contact-import-audience">
                                    Add to audience
                                </FieldLabel>
                                <Select
                                    items={audienceItems}
                                    value={form.data.audience || WORKSPACE_ONLY}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'audience',
                                            typeof value === 'string' &&
                                                value !== WORKSPACE_ONLY
                                                ? value
                                                : '',
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="contact-import-audience"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select an audience" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {audienceItems.map((item) => (
                                                <SelectItem
                                                    key={item.value}
                                                    value={item.value}
                                                >
                                                    {item.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    Contacts are always added to the workspace.
                                    Choose an audience to subscribe them too.
                                </FieldDescription>
                                <FieldError>{form.errors.audience}</FieldError>
                            </Field>
                        ) : null}

                        {form.progress ? (
                            <div className="flex flex-col gap-1.5">
                                <Progress
                                    value={form.progress.percentage ?? 0}
                                    aria-label="Upload progress"
                                />
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    Uploading {form.progress.percentage ?? 0}%
                                </span>
                            </div>
                        ) : null}
                    </FieldGroup>
                </SettingsPanel>

                <SettingsPanel
                    variant="inset"
                    title="Format tips"
                    description="Only an email column is required. You will choose what each column means on the next step."
                    actions={
                        <Button
                            variant="outline"
                            size="sm"
                            nativeButton={false}
                            render={
                                <a
                                    href={`data:text/csv;charset=utf-8,${encodeURIComponent(SAMPLE_CSV)}`}
                                    download="maildun-contacts-sample.csv"
                                />
                            }
                        >
                            <HugeiconsIcon
                                icon={Download04Icon}
                                data-icon="inline-start"
                            />
                            Sample CSV
                        </Button>
                    }
                >
                    <ul className="flex list-disc flex-col gap-1.5 py-5 pr-6 pl-11 text-sm text-muted-foreground sm:pr-7 sm:pl-12">
                        <li>
                            Map columns to email, first name, last name, tags,
                            and the audience&apos;s custom fields.
                        </li>
                        <li>
                            Separate several tags in one cell with commas,
                            semicolons or pipes.
                        </li>
                        <li>
                            Existing contacts are matched by email. You decide
                            whether to leave them alone or fill their empty
                            fields.
                        </li>
                    </ul>
                </SettingsPanel>

                <div className="flex justify-end gap-2">
                    <Button
                        type="submit"
                        disabled={!form.data.file || form.processing}
                        data-test="import-upload"
                    >
                        {form.processing ? (
                            <Spinner data-icon="inline-start" />
                        ) : null}
                        Continue to mapping
                    </Button>
                </div>
            </form>
        </>
    );
}
