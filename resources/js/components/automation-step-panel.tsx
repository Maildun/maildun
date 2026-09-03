import {
    Clock01Icon,
    Delete02Icon,
    FilterIcon,
    Tag01Icon,
    WorkflowSquare10Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
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
import { Separator } from '@/components/ui/separator';
import type {
    AutomationAudienceOption,
    AutomationCatalog,
    AutomationEmailOption,
    AutomationNode,
    AutomationNodeData,
    AutomationTagOption,
} from '@/types';

type Props = {
    node: AutomationNode | null;
    catalog: AutomationCatalog;
    audiences: AutomationAudienceOption[];
    tags: AutomationTagOption[];
    emails: AutomationEmailOption[];
    readOnly: boolean;
    onChange: (id: string, data: AutomationNodeData) => void;
    onDelete: (id: string) => void;
};

const NONE = '__none__';

const PANEL_META = {
    trigger: {
        title: 'Trigger',
        description: 'Choose what starts this automation.',
        icon: WorkflowSquare10Icon,
    },
    action: {
        title: 'Action',
        description: 'Choose what Maildun should do next.',
        icon: Tag01Icon,
    },
    condition: {
        title: 'Condition',
        description: 'Split the workflow using subscriber data.',
        icon: FilterIcon,
    },
    delay: {
        title: 'Wait',
        description: 'Pause before moving to the next step.',
        icon: Clock01Icon,
    },
} as const;

export default function AutomationStepPanel({
    node,
    catalog,
    audiences,
    tags,
    emails,
    readOnly,
    onChange,
    onDelete,
}: Props) {
    if (!node) {
        return (
            <div className="flex h-full items-center justify-center p-6 text-center text-muted-foreground">
                Select a step on the canvas to configure it.
            </div>
        );
    }

    const data = node.data as Record<string, unknown>;
    const meta = PANEL_META[node.type];
    const patch = (changes: Record<string, unknown>) =>
        onChange(node.id, { ...data, ...changes } as AutomationNodeData);

    const tagField = (label: string, key: string) => (
        <Field>
            <FieldLabel htmlFor={`${node.id}-${key}`}>{label}</FieldLabel>
            <Select
                value={(data[key] as string) ?? ''}
                onValueChange={(value) => patch({ [key]: value })}
                disabled={readOnly}
            >
                <SelectTrigger
                    id={`${node.id}-${key}`}
                    className="w-full"
                    data-test="automation-tag-select"
                >
                    <SelectValue placeholder="Choose a tag" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        {tags.map((tag) => (
                            <SelectItem key={tag.uuid} value={tag.uuid}>
                                {tag.name}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
            {tags.length === 0 && (
                <FieldDescription>
                    This team has no tags yet. Create one in team settings
                    first.
                </FieldDescription>
            )}
        </Field>
    );

    return (
        <div className="flex h-full min-h-0 flex-col">
            <div className="flex shrink-0 items-start gap-3 p-4 pr-12">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-foreground">
                    <HugeiconsIcon icon={meta.icon} className="size-4" />
                </div>
                <div className="min-w-0">
                    <p className="font-medium">{meta.title}</p>
                    <p className="text-sm text-muted-foreground">
                        {meta.description}
                    </p>
                </div>
            </div>
            <Separator />

            <div className="min-h-0 flex-1 overflow-y-auto p-4">
                <FieldGroup className="gap-5">
                    {node.type === 'trigger' && (
                        <>
                            <Field>
                                <FieldLabel htmlFor={`${node.id}-kind`}>
                                    Trigger
                                </FieldLabel>
                                <Select
                                    value={(data.kind as string) ?? ''}
                                    onValueChange={(kind) =>
                                        patch({
                                            kind,
                                            tag_uuid:
                                                kind === 'subscriber.tagged'
                                                    ? (data.tag_uuid ?? null)
                                                    : null,
                                        })
                                    }
                                    disabled={readOnly}
                                >
                                    <SelectTrigger
                                        id={`${node.id}-kind`}
                                        className="w-full"
                                        data-test="automation-trigger-select"
                                    >
                                        <SelectValue placeholder="Choose a trigger" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {catalog.triggers.map((option) => (
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
                                <FieldDescription>
                                    An automation has exactly one trigger, and
                                    it is what starts every run.
                                </FieldDescription>
                            </Field>

                            <Field>
                                <FieldLabel htmlFor={`${node.id}-audience`}>
                                    Audience
                                </FieldLabel>
                                <Select
                                    value={
                                        (data.audience_uuid as string) ?? NONE
                                    }
                                    onValueChange={(value) =>
                                        patch({
                                            audience_uuid:
                                                value === NONE ? null : value,
                                        })
                                    }
                                    disabled={readOnly}
                                >
                                    <SelectTrigger
                                        id={`${node.id}-audience`}
                                        className="w-full"
                                        data-test="automation-audience-select"
                                    >
                                        <SelectValue placeholder="Any audience" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value={NONE}>
                                                Any audience
                                            </SelectItem>
                                            {audiences.map((audience) => (
                                                <SelectItem
                                                    key={audience.uuid}
                                                    value={audience.uuid}
                                                >
                                                    {audience.name}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>

                            {data.kind === 'subscriber.tagged' &&
                                tagField('Tag', 'tag_uuid')}
                        </>
                    )}

                    {node.type === 'action' && (
                        <>
                            <Field>
                                <FieldLabel htmlFor={`${node.id}-kind`}>
                                    Action
                                </FieldLabel>
                                <Select
                                    value={(data.kind as string) ?? ''}
                                    onValueChange={(kind) => patch({ kind })}
                                    disabled={readOnly}
                                >
                                    <SelectTrigger
                                        id={`${node.id}-kind`}
                                        className="w-full"
                                        data-test="automation-action-select"
                                    >
                                        <SelectValue placeholder="Choose an action" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {catalog.actions.map((option) => (
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
                            </Field>

                            {data.kind === 'send_email' && (
                                <Field>
                                    <FieldLabel htmlFor={`${node.id}-email`}>
                                        Transactional email
                                    </FieldLabel>
                                    <Select
                                        value={
                                            (data.transactional_email_uuid as string) ??
                                            ''
                                        }
                                        onValueChange={(value) =>
                                            patch({
                                                transactional_email_uuid: value,
                                            })
                                        }
                                        disabled={readOnly}
                                    >
                                        <SelectTrigger
                                            id={`${node.id}-email`}
                                            className="w-full"
                                            data-test="automation-email-select"
                                        >
                                            <SelectValue placeholder="Choose an email" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {emails.map((email) => (
                                                    <SelectItem
                                                        key={email.uuid}
                                                        value={email.uuid}
                                                    >
                                                        {email.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <FieldDescription>
                                        {emails.length === 0
                                            ? 'Only published transactional emails can be sent from an automation.'
                                            : 'Merge tags resolve against the subscriber when the step runs.'}
                                    </FieldDescription>
                                </Field>
                            )}

                            {(data.kind === 'add_tag' ||
                                data.kind === 'remove_tag') &&
                                tagField('Tag', 'tag_uuid')}
                        </>
                    )}

                    {node.type === 'delay' && (
                        <>
                            <Field>
                                <FieldLabel htmlFor={`${node.id}-amount`}>
                                    Wait for
                                </FieldLabel>
                                <Input
                                    id={`${node.id}-amount`}
                                    type="number"
                                    min={1}
                                    max={365}
                                    placeholder="2"
                                    value={String(data.amount ?? 1)}
                                    disabled={readOnly}
                                    data-test="automation-delay-amount"
                                    onChange={(event) =>
                                        patch({
                                            amount: Number(event.target.value),
                                        })
                                    }
                                />
                            </Field>

                            <Field>
                                <FieldLabel htmlFor={`${node.id}-unit`}>
                                    Unit
                                </FieldLabel>
                                <Select
                                    value={(data.unit as string) ?? 'minutes'}
                                    onValueChange={(unit) => patch({ unit })}
                                    disabled={readOnly}
                                >
                                    <SelectTrigger
                                        id={`${node.id}-unit`}
                                        className="w-full"
                                        data-test="automation-delay-unit"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {catalog.delayUnits.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                        </>
                    )}

                    {node.type === 'condition' && (
                        <>
                            <Field>
                                <FieldLabel htmlFor={`${node.id}-kind`}>
                                    Condition
                                </FieldLabel>
                                <Select
                                    value={(data.kind as string) ?? ''}
                                    onValueChange={(kind) =>
                                        patch({
                                            kind,
                                            tag_uuid:
                                                kind === 'has_tag'
                                                    ? (data.tag_uuid ?? null)
                                                    : null,
                                            source:
                                                kind === 'source'
                                                    ? (data.source ?? null)
                                                    : null,
                                        })
                                    }
                                    disabled={readOnly}
                                >
                                    <SelectTrigger
                                        id={`${node.id}-kind`}
                                        className="w-full"
                                        data-test="automation-condition-select"
                                    >
                                        <SelectValue placeholder="Choose a condition" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {catalog.conditions.map(
                                                (option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    The left handle is True, the right one is
                                    False. A branch you leave unwired ends the
                                    run.
                                </FieldDescription>
                            </Field>

                            {data.kind === 'has_tag' &&
                                tagField('Tag', 'tag_uuid')}

                            {data.kind === 'source' && (
                                <Field>
                                    <FieldLabel htmlFor={`${node.id}-source`}>
                                        Source
                                    </FieldLabel>
                                    <Select
                                        value={(data.source as string) ?? ''}
                                        onValueChange={(source) =>
                                            patch({ source })
                                        }
                                        disabled={readOnly}
                                    >
                                        <SelectTrigger
                                            id={`${node.id}-source`}
                                            className="w-full"
                                            data-test="automation-source-select"
                                        >
                                            <SelectValue placeholder="Choose a source" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                {catalog.sources.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </Field>
                            )}
                        </>
                    )}
                </FieldGroup>
            </div>

            {!readOnly && node.type !== 'trigger' && (
                <>
                    <Separator />
                    <div className="shrink-0 p-4">
                        <Button
                            variant="destructive"
                            className="w-full"
                            data-test="delete-automation-step"
                            onClick={() => onDelete(node.id)}
                        >
                            <HugeiconsIcon
                                icon={Delete02Icon}
                                data-icon="inline-start"
                            />
                            Remove this step
                        </Button>
                    </div>
                </>
            )}
        </div>
    );
}
