import { Form, Head } from '@inertiajs/react';
import type { KeyboardEvent } from 'react';
import { useRef, useState } from 'react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { UnsavedChangesGuard } from '@/hooks/use-unsaved-changes';
import { focusFirstInvalidField } from '@/lib/focus-first-invalid';
import { cn } from '@/lib/utils';
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
    const editorOptionRefs = useRef<
        Partial<Record<EmailEditorMode, HTMLButtonElement | null>>
    >({});

    const canManage = permissions.canManageEmails;

    const selectEditor = (direction: 1 | -1) => {
        const index = editors.findIndex((option) => option.value === editor);
        const next = (index + direction + editors.length) % editors.length;
        const option = editors[next];

        if (option) {
            setEditor(option.value);
            requestAnimationFrame(() =>
                editorOptionRefs.current[option.value]?.focus(),
            );
        }
    };

    const onEditorKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            event.preventDefault();
            selectEditor(1);
        }

        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            event.preventDefault();
            selectEditor(-1);
        }
    };

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
                                    <div className="p-6 sm:p-7">
                                        <input
                                            type="hidden"
                                            name="email_editor"
                                            value={editor}
                                        />

                                        <div
                                            className="grid gap-3 sm:grid-cols-2"
                                            role="radiogroup"
                                            aria-label="Default editor"
                                            onKeyDown={
                                                canManage
                                                    ? onEditorKeyDown
                                                    : undefined
                                            }
                                        >
                                            {editors.map((option) => (
                                                <button
                                                    key={option.value}
                                                    type="button"
                                                    role="radio"
                                                    ref={(node) => {
                                                        editorOptionRefs.current[
                                                            option.value
                                                        ] = node;
                                                    }}
                                                    tabIndex={
                                                        editor === option.value
                                                            ? 0
                                                            : -1
                                                    }
                                                    data-test="editor-option"
                                                    aria-checked={
                                                        editor === option.value
                                                    }
                                                    disabled={!canManage}
                                                    onClick={() =>
                                                        setEditor(option.value)
                                                    }
                                                    className={cn(
                                                        'flex flex-col items-start gap-1 rounded-lg border p-4 text-left transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                                        canManage &&
                                                            'hover:bg-accent',
                                                        editor ===
                                                            option.value &&
                                                            'border-primary ring-1 ring-primary',
                                                        !canManage &&
                                                            'cursor-not-allowed opacity-70',
                                                    )}
                                                >
                                                    <span className="text-sm font-medium">
                                                        {option.label}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {option.description}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>

                                        <FieldError className="mt-2">
                                            {errors.email_editor}
                                        </FieldError>
                                    </div>
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
