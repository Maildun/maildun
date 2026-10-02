import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes';
import { focusFirstInvalidField } from '@/lib/focus-first-invalid';
import { update } from '@/routes/teams/email';
import type {
    EmailEditorMode,
    EmailEditorOption,
    Team,
    TeamEmailSettings,
    TeamPermissions,
} from '@/types';

type Props = {
    team: Team;
    settings: TeamEmailSettings;
    editors: EmailEditorOption[];
    permissions: TeamPermissions;
};

export default function TeamEmailSettingsPage({
    team,
    settings,
    editors,
    permissions,
}: Props) {
    const [editor, setEditor] = useState<EmailEditorMode>(
        settings.email_editor,
    );
    const selectedEditor = editors.find((option) => option.value === editor);
    const canManage = permissions.canManageEmails;

    return (
        <>
            <Head title={`Email Editor · ${team.name}`} />

            <div className="flex flex-col gap-8">
                <SettingsPageHeader title="Email Editor" />
                <Form
                    {...update.form.patch(team.slug)}
                    options={{ preserveScroll: true }}
                    setDefaultsOnSuccess
                    onSuccess={() =>
                        toast.add({
                            type: 'success',
                            title: 'Changes saved.',
                        })
                    }
                    onError={() => focusFirstInvalidField()}
                >
                    {({ errors, processing, isDirty }) => {
                        const hasEditorChanges =
                            editor !== settings.email_editor;
                        const formIsDirty = isDirty || hasEditorChanges;

                        return (
                            <div className="flex flex-col gap-6">
                                <UnsavedChangesGuard isDirty={formIsDirty} />
                                <SettingsPanel
                                    variant="inset"
                                    title="Email editor"
                                    description="All email composition in this workspace uses this editor."
                                >
                                    <FieldGroup className="p-6 sm:p-7">
                                        <input
                                            type="hidden"
                                            name="email_editor"
                                            value={editor}
                                        />
                                        <Field
                                            data-invalid={Boolean(
                                                errors.email_editor,
                                            )}
                                            data-disabled={!canManage}
                                        >
                                            <FieldLabel htmlFor="email-editor">
                                                Default editor
                                            </FieldLabel>
                                            <Select
                                                items={editors}
                                                value={editor}
                                                disabled={!canManage}
                                                onValueChange={(value) => {
                                                    if (value !== null) {
                                                        setEditor(value);
                                                    }
                                                }}
                                            >
                                                <SelectTrigger
                                                    id="email-editor"
                                                    data-test="editor-select"
                                                    className="w-full"
                                                    aria-invalid={Boolean(
                                                        errors.email_editor,
                                                    )}
                                                    aria-describedby="email-editor-description email-editor-error"
                                                >
                                                    <SelectValue placeholder="Select an editor" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {editors.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <FieldDescription id="email-editor-description">
                                                {selectedEditor?.description}
                                            </FieldDescription>
                                            <FieldError id="email-editor-error">
                                                {errors.email_editor}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>
                                    {canManage ? (
                                        <div className="flex items-center justify-end border-t border-border px-6 py-5 sm:px-7">
                                            <Button
                                                type="submit"
                                                data-test="save-email-settings"
                                                disabled={
                                                    processing || !formIsDirty
                                                }
                                                className="w-full sm:w-auto"
                                            >
                                                {processing && (
                                                    <Spinner data-icon="inline-start" />
                                                )}
                                                Save changes
                                            </Button>
                                        </div>
                                    ) : (
                                        <p className="border-t border-border px-6 py-5 text-sm text-muted-foreground sm:px-7">
                                            Only workspace owners and admins can
                                            change these settings.
                                        </p>
                                    )}
                                </SettingsPanel>
                            </div>
                        );
                    }}
                </Form>
            </div>
        </>
    );
}
