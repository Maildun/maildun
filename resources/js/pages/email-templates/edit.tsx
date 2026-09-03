import { Delete02Icon, Mail01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import DeleteEmailTemplateModal from '@/components/delete-email-template-modal';
import { EmailBuilderEditor } from '@/components/email-builder-editor';
import { EmailHtmlEditor } from '@/components/email-html-editor';
import { EmailSourceEditor } from '@/components/email-source-editor';
import EmailTemplatePicker from '@/components/email-template-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    EMPTY_BUILDER_DOCUMENT,
    htmlToBuilderDocument,
    renderBuilderHtml,
    renderSourceHtml,
} from '@/lib/email-builder';
import { edit, index, update } from '@/routes/email_templates';
import type {
    EmailBuilderDocument,
    EmailEditorMode,
    EmailTemplateDetail,
    EmailTemplateSummary,
} from '@/types';

type Props = {
    template: EmailTemplateDetail;
    templates: EmailTemplateSummary[];
    defaultEditor: EmailEditorMode;
    canManage: boolean;
};

type TabValue = 'details' | 'content';

const TABS: { value: TabValue; label: string; fields: string[] }[] = [
    {
        value: 'details',
        label: 'Details',
        fields: ['name', 'description', 'subject', 'preheader'],
    },
    {
        value: 'content',
        label: 'Content',
        fields: ['html', 'source', 'design'],
    },
];

const EDITOR_LABELS: Record<EmailEditorMode, string> = {
    html: 'HTML',
    builder: 'EmailBuilder.js',
    plain_text: 'Plain text',
    markdown: 'Markdown',
};

export default function EmailTemplatesEdit({
    template,
    templates,
    defaultEditor,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [tab, setTab] = useState<TabValue>('details');
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [useOpen, setUseOpen] = useState(false);

    const form = useForm<{
        name: string;
        description: string;
        subject: string;
        preheader: string;
        html: string;
        source: string;
        design: EmailBuilderDocument | null;
    }>({
        name: template.name,
        description: template.description ?? '',
        subject: template.subject ?? '',
        preheader: template.preheader ?? '',
        html: template.html ?? '',
        source: template.source ?? '',
        design:
            template.editor === 'builder'
                ? (template.design ??
                  htmlToBuilderDocument(template.html ?? ''))
                : null,
    });

    if (!currentTeam) {
        return null;
    }

    const errorFor = (value: TabValue) =>
        TABS.find((entry) => entry.value === value)?.fields.some(
            (field) => form.errors[field as keyof typeof form.errors],
        ) ?? false;

    const save = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            html:
                template.editor === 'builder' && data.design
                    ? renderBuilderHtml(data.design)
                    : template.editor === 'plain_text' ||
                        template.editor === 'markdown'
                      ? renderSourceHtml(data.source, template.editor)
                      : data.html,
            source:
                template.editor === 'plain_text' ||
                template.editor === 'markdown'
                    ? data.source
                    : '',
            design: template.editor === 'builder' ? data.design : null,
        }));

        form.patch(update.url([currentTeam.slug, template.uuid]), {
            preserveScroll: true,
            onError: (errors) => {
                const firstTab = TABS.find((entry) =>
                    entry.fields.some((field) => errors[field]),
                );

                if (firstTab) {
                    setTab(firstTab.value);
                }
            },
        });
    };

    const canUse = canManage && template.editor === defaultEditor;

    return (
        <>
            <Head title={`Edit ${template.name}`} />

            <form onSubmit={save} className="flex flex-1 flex-col gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {template.name}
                            </h1>
                            <Badge variant="secondary">
                                {EDITOR_LABELS[template.editor]}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Compose the email campaigns will start from.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {canUse && (
                            <Button
                                type="button"
                                variant="outline"
                                data-test="use-template-button"
                                onClick={() => setUseOpen(true)}
                            >
                                <HugeiconsIcon
                                    icon={Mail01Icon}
                                    data-icon="inline-start"
                                />
                                Use
                            </Button>
                        )}
                        {canManage && (
                            <>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    aria-label="Delete template"
                                    data-test="delete-template-button"
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    <HugeiconsIcon icon={Delete02Icon} />
                                </Button>
                                <Button
                                    type="submit"
                                    data-test="save-template-button"
                                    disabled={form.processing}
                                >
                                    {form.processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Save
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <Tabs
                    value={tab}
                    className="flex-1"
                    onValueChange={(value) => setTab(value as TabValue)}
                >
                    <TabsList variant="sliding">
                        {TABS.map((entry) => (
                            <TabsTrigger
                                key={entry.value}
                                value={entry.value}
                                data-test={`template-tab-${entry.value}`}
                            >
                                {entry.label}
                                {errorFor(entry.value) && (
                                    <span
                                        aria-label="has errors"
                                        className="size-1.5 rounded-full bg-destructive"
                                    />
                                )}
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="details">
                        <Card className="max-w-2xl">
                            <CardHeader>
                                <CardTitle>Details</CardTitle>
                                <CardDescription>
                                    How this template appears in the library and
                                    what a new campaign copies.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        data-invalid={Boolean(form.errors.name)}
                                    >
                                        <FieldLabel htmlFor="name">
                                            Name
                                        </FieldLabel>
                                        <Input
                                            id="name"
                                            data-test="email-template-name"
                                            disabled={!canManage}
                                            value={form.data.name}
                                            onChange={(event) =>
                                                form.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Weekly digest"
                                            aria-invalid={Boolean(
                                                form.errors.name,
                                            )}
                                        />
                                        <FieldDescription>
                                            Only your team sees this.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.errors.name}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.description,
                                        )}
                                    >
                                        <FieldLabel htmlFor="description">
                                            Description
                                        </FieldLabel>
                                        <Input
                                            id="description"
                                            disabled={!canManage}
                                            value={form.data.description}
                                            onChange={(event) =>
                                                form.setData(
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Our usual layout"
                                            aria-invalid={Boolean(
                                                form.errors.description,
                                            )}
                                        />
                                        <FieldError>
                                            {form.errors.description}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.subject,
                                        )}
                                    >
                                        <FieldLabel htmlFor="subject">
                                            Subject
                                        </FieldLabel>
                                        <Input
                                            id="subject"
                                            data-test="email-template-subject"
                                            disabled={!canManage}
                                            value={form.data.subject}
                                            onChange={(event) =>
                                                form.setData(
                                                    'subject',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="What's new this month"
                                            aria-invalid={Boolean(
                                                form.errors.subject,
                                            )}
                                        />
                                        <FieldDescription>
                                            Copied into campaigns started from
                                            this template.
                                        </FieldDescription>
                                        <FieldError>
                                            {form.errors.subject}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.preheader,
                                        )}
                                    >
                                        <FieldLabel htmlFor="preheader">
                                            Preheader
                                        </FieldLabel>
                                        <Input
                                            id="preheader"
                                            data-test="email-template-preheader"
                                            disabled={!canManage}
                                            value={form.data.preheader}
                                            onChange={(event) =>
                                                form.setData(
                                                    'preheader',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="A quick look at this month's updates"
                                            aria-invalid={Boolean(
                                                form.errors.preheader,
                                            )}
                                        />
                                        <FieldError>
                                            {form.errors.preheader}
                                        </FieldError>
                                    </Field>
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent
                        value="content"
                        className="flex min-h-full flex-col gap-4"
                    >
                        {template.editor === 'builder' ? (
                            <>
                                <EmailBuilderEditor
                                    document={
                                        form.data.design ??
                                        EMPTY_BUILDER_DOCUMENT
                                    }
                                    disabled={!canManage}
                                    onChange={(design) =>
                                        form.setData('design', design)
                                    }
                                />
                                <FieldError>{form.errors.design}</FieldError>
                            </>
                        ) : template.editor === 'html' ? (
                            <>
                                <EmailHtmlEditor
                                    value={form.data.html}
                                    disabled={!canManage}
                                    aria-invalid={Boolean(form.errors.html)}
                                    onChange={(html) =>
                                        form.setData('html', html)
                                    }
                                />
                                <FieldError>{form.errors.html}</FieldError>
                            </>
                        ) : (
                            <>
                                <EmailSourceEditor
                                    editor={template.editor}
                                    value={form.data.source}
                                    disabled={!canManage}
                                    aria-invalid={Boolean(form.errors.source)}
                                    onChange={(source) =>
                                        form.setData('source', source)
                                    }
                                />
                                <FieldError>{form.errors.source}</FieldError>
                            </>
                        )}
                    </TabsContent>
                </Tabs>
            </form>

            {canManage && (
                <>
                    <EmailTemplatePicker
                        teamSlug={currentTeam.slug}
                        templates={templates}
                        defaultEditor={defaultEditor}
                        initialTemplate={template.uuid}
                        open={useOpen}
                        onOpenChange={setUseOpen}
                    />

                    <DeleteEmailTemplateModal
                        teamSlug={currentTeam.slug}
                        template={template}
                        open={deleteOpen}
                        onOpenChange={setDeleteOpen}
                    />
                </>
            )}
        </>
    );
}

EmailTemplatesEdit.layout = (props: {
    template: { name: string; uuid: string };
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Templates',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.template.name,
            href: props.currentTeam
                ? edit([props.currentTeam.slug, props.template.uuid])
                : '/',
        },
    ],
});
