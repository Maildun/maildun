import {
    Activity03Icon,
    Add01Icon,
    ArrowLeft01Icon,
    Cancel01Icon,
    Clock01Icon,
    Copy01Icon,
    Edit03Icon,
    FilterIcon,
    Key01Icon,
    PlayIcon,
    RefreshIcon,
    Tag01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { addEdge, applyEdgeChanges, applyNodeChanges } from '@xyflow/react';
import type { Connection, EdgeChange, NodeChange } from '@xyflow/react';
import { useMemo, useState } from 'react';
import AutomationCanvas from '@/components/automation-canvas';
import AutomationStepPanel from '@/components/automation-step-panel';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverDescription,
    PopoverHeader,
    PopoverTitle,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { toast } from '@/components/ui/toast';
import {
    activate,
    activity,
    index,
    pause,
    regenerateToken,
    update,
} from '@/routes/automations';
import type {
    AutomationAudienceOption,
    AutomationCatalog,
    AutomationDetail,
    AutomationEdge,
    AutomationEmailOption,
    AutomationNode,
    AutomationNodeData,
    AutomationNodeType,
    AutomationStatus,
    AutomationTagOption,
} from '@/types';

type Props = {
    automation: AutomationDetail;
    audiences: AutomationAudienceOption[];
    tags: AutomationTagOption[];
    transactionalEmails: AutomationEmailOption[];
    catalog: AutomationCatalog;
    canManage: boolean;
};

const STATUS_LABELS: Record<AutomationStatus, string> = {
    draft: 'Draft',
    active: 'Active',
    paused: 'Paused',
};

const STATUS_VARIANTS: Record<
    AutomationStatus,
    'success' | 'secondary' | 'amber'
> = {
    draft: 'secondary',
    active: 'success',
    paused: 'amber',
};

const NEW_STEPS: {
    type: Exclude<AutomationNodeType, 'trigger'>;
    label: string;
    testId: string;
    icon: typeof Tag01Icon;
    iconClassName: string;
    data: AutomationNodeData;
}[] = [
    {
        type: 'action',
        label: 'Action',
        testId: 'add-action-step',
        icon: Tag01Icon,
        iconClassName: 'bg-info/15 text-info',
        data: { kind: '' },
    },
    {
        type: 'condition',
        label: 'Condition',
        testId: 'add-condition-step',
        icon: FilterIcon,
        iconClassName: 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
        data: { kind: '' },
    },
    {
        type: 'delay',
        label: 'Wait',
        testId: 'add-delay-step',
        icon: Clock01Icon,
        iconClassName: 'bg-warning/15 text-warning',
        data: { amount: 1, unit: 'hours' },
    },
];

function toLabelMap(options: { value: string; label: string }[]) {
    return Object.fromEntries(
        options.map((option) => [option.value, option.label]),
    );
}

function toGraphPayload(nodes: AutomationNode[], edges: AutomationEdge[]) {
    return {
        nodes: nodes.map((node) => ({
            id: node.id,
            type: node.type,
            position: node.position,
            data: node.data,
        })),
        edges: edges.map((edge) => ({
            id: edge.id,
            source: edge.source,
            target: edge.target,
            sourceHandle: edge.sourceHandle ?? null,
            targetHandle: edge.targetHandle ?? null,
        })),
    };
}

export default function AutomationEdit({
    automation,
    audiences,
    tags,
    transactionalEmails,
    catalog,
    canManage,
}: Props) {
    const { currentTeam } = usePage().props;
    const [name, setName] = useState(automation.name);
    const [description, setDescription] = useState(
        automation.description ?? '',
    );
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [detailsName, setDetailsName] = useState(automation.name);
    const [detailsDescription, setDetailsDescription] = useState(
        automation.description ?? '',
    );
    const [nodes, setNodes] = useState<AutomationNode[]>(
        automation.graph.nodes,
    );
    const [edges, setEdges] = useState<AutomationEdge[]>(
        automation.graph.edges,
    );
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testingNodeId, setTestingNodeId] = useState<string | null>(null);
    const [elementsOpen, setElementsOpen] = useState(false);

    const readOnly = !canManage;

    const selected = useMemo(
        () =>
            nodes.find(
                (node) => (node as { selected?: boolean }).selected === true,
            ) ?? null,
        [nodes],
    );
    const triggerLabels = useMemo(
        () => toLabelMap(catalog.triggers),
        [catalog.triggers],
    );
    const actionLabels = useMemo(
        () => toLabelMap(catalog.actions),
        [catalog.actions],
    );
    const conditionLabels = useMemo(
        () => toLabelMap(catalog.conditions),
        [catalog.conditions],
    );
    const sourceLabels = useMemo(
        () => toLabelMap(catalog.sources),
        [catalog.sources],
    );
    const delayUnitLabels = useMemo(
        () => toLabelMap(catalog.delayUnits),
        [catalog.delayUnits],
    );

    if (!currentTeam) {
        return null;
    }

    const handleNodesChange = (changes: NodeChange[]) => {
        if (
            changes.some(
                (change) =>
                    change.type === 'select' && change.selected === true,
            )
        ) {
            setElementsOpen(false);
        }

        setNodes(
            (current) =>
                applyNodeChanges(
                    changes,
                    current as never,
                ) as unknown as AutomationNode[],
        );
    };

    const handleEdgesChange = (changes: EdgeChange[]) =>
        setEdges(
            (current) =>
                applyEdgeChanges(
                    changes,
                    current as never,
                ) as unknown as AutomationEdge[],
        );

    const handleConnect = (connection: Connection) =>
        setEdges(
            (current) =>
                addEdge(
                    { ...connection, type: 'smoothstep' },
                    current as never,
                ) as unknown as AutomationEdge[],
        );

    const addStep = (step: (typeof NEW_STEPS)[number]) => {
        // Derived from the graph rather than a random id, so the same canvas
        // always produces the same next id.
        const taken = new Set(nodes.map((node) => node.id));
        let suffix = 1;

        while (taken.has(`${step.type}-${suffix}`)) {
            suffix += 1;
        }

        const id = `${step.type}-${suffix}`;
        const lowest = nodes.reduce(
            (max, node) => Math.max(max, node.position.y),
            0,
        );

        setNodes((current) => [
            ...current.map((node) => ({ ...node, selected: false })),
            {
                id,
                type: step.type,
                position: { x: 320, y: lowest + 140 },
                data: { ...step.data },
                selected: true,
            },
        ]);
        setElementsOpen(false);
    };

    const updateNodeData = (id: string, data: AutomationNodeData) =>
        setNodes((current) =>
            current.map((node) => (node.id === id ? { ...node, data } : node)),
        );

    const deleteNode = (id: string) => {
        setNodes((current) => current.filter((node) => node.id !== id));
        setEdges((current) =>
            current.filter((edge) => edge.source !== id && edge.target !== id),
        );
    };

    const closeInspector = () =>
        setNodes((current) =>
            current.map((node) => ({ ...node, selected: false })),
        );

    /** Strip the view-only keys ReactFlow adds so the saved graph stays the contract. */
    const graphPayload = toGraphPayload(nodes, edges);
    const savedGraphPayload = toGraphPayload(
        automation.graph.nodes,
        automation.graph.edges,
    );
    const hasUnsavedChanges =
        name !== automation.name ||
        description !== (automation.description ?? '') ||
        JSON.stringify(graphPayload) !== JSON.stringify(savedGraphPayload);

    const save = () =>
        router.put(
            update.url([currentTeam.slug, automation.uuid]),
            {
                name,
                description: description === '' ? null : description,
                graph: graphPayload,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onError: (errors) =>
                    toast.add({
                        type: 'error',
                        title: 'Failed to save the automation.',
                        description:
                            Object.values(errors).flat().join(' ') || undefined,
                    }),
            },
        );

    const testWorkflow = async () => {
        if (testing || nodes.length === 0) {
            return;
        }

        setTesting(true);

        for (const node of [...nodes].sort(
            (first, second) => first.position.y - second.position.y,
        )) {
            setTestingNodeId(node.id);
            await new Promise((resolve) => window.setTimeout(resolve, 450));
        }

        setTestingNodeId(null);
        setTesting(false);
        toast.add({
            type: 'success',
            title: 'Visual test completed.',
            description: 'No emails were sent and no subscribers were changed.',
        });
    };

    const changeStatus = (next: 'activate' | 'pause') => {
        const route = next === 'activate' ? activate : pause;

        router.post(
            route.url([currentTeam.slug, automation.uuid]),
            {},
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.add({
                        type: 'error',
                        title:
                            next === 'activate'
                                ? 'This automation is not ready to run yet.'
                                : 'Failed to pause the automation.',
                        description:
                            Object.values(errors).flat().join(' ') || undefined,
                    }),
            },
        );
    };

    const handleDetailsOpenChange = (open: boolean) => {
        if (open) {
            setDetailsName(name);
            setDetailsDescription(description);
        }

        setDetailsOpen(open);
    };

    const applyDetails = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const nextName = detailsName.trim();

        if (nextName === '') {
            return;
        }

        setName(nextName);
        setDescription(detailsDescription.trim());
        setDetailsOpen(false);
    };

    return (
        <>
            <Head title={name} />
            <div
                className="relative flex h-dvh min-h-0 w-full flex-col overflow-hidden bg-background"
                data-test="automation-editor-shell"
            >
                <header
                    className="flex h-14 shrink-0 items-center justify-between gap-3 border-b bg-background px-3 sm:px-4"
                    data-test="automation-editor-navbar"
                >
                    <div className="flex min-w-0 items-center gap-2">
                        <Link
                            href={index(currentTeam.slug)}
                            aria-label="Back to automations"
                            className={buttonVariants({
                                variant: 'ghost',
                                size: 'icon-sm',
                            })}
                        >
                            <HugeiconsIcon icon={ArrowLeft01Icon} />
                        </Link>
                        <Separator
                            orientation="vertical"
                            className="hidden h-5 sm:block"
                        />
                        {canManage ? (
                            <Dialog
                                open={detailsOpen}
                                onOpenChange={handleDetailsOpenChange}
                            >
                                <DialogTrigger
                                    render={
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="group max-w-36 min-w-0 justify-start px-2 sm:max-w-56 lg:max-w-80"
                                            data-test="edit-automation-details-button"
                                        />
                                    }
                                >
                                    <span className="truncate">{name}</span>
                                    <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground transition-colors group-hover:bg-accent group-hover:text-foreground">
                                        <HugeiconsIcon
                                            icon={Edit03Icon}
                                            className="size-3.5"
                                            data-icon="inline-end"
                                        />
                                    </span>
                                </DialogTrigger>
                                <DialogContent>
                                    <form
                                        className="grid gap-6"
                                        onSubmit={applyDetails}
                                    >
                                        <DialogHeader>
                                            <DialogTitle>
                                                Edit automation details
                                            </DialogTitle>
                                            <DialogDescription>
                                                Update the name and description.
                                                Use Save in the navbar to store
                                                these changes.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="automation-name">
                                                    Name
                                                </FieldLabel>
                                                <Input
                                                    id="automation-name"
                                                    value={detailsName}
                                                    autoFocus
                                                    required
                                                    placeholder="Welcome series"
                                                    data-test="automation-name-input"
                                                    onChange={(event) =>
                                                        setDetailsName(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>

                                            <Field>
                                                <FieldLabel htmlFor="automation-description">
                                                    Description
                                                </FieldLabel>
                                                <Textarea
                                                    id="automation-description"
                                                    value={detailsDescription}
                                                    rows={3}
                                                    placeholder="Greets everyone who joins the newsletter."
                                                    onChange={(event) =>
                                                        setDetailsDescription(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </FieldGroup>

                                        <DialogFooter className="gap-2">
                                            <DialogClose
                                                render={
                                                    <Button variant="secondary" />
                                                }
                                            >
                                                Cancel
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    detailsName.trim() === ''
                                                }
                                                data-test="apply-automation-details"
                                            >
                                                Apply
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        ) : (
                            <p className="max-w-36 truncate px-2 text-sm font-medium sm:max-w-56 lg:max-w-80">
                                {name}
                            </p>
                        )}
                        <Badge
                            className="hidden md:inline-flex"
                            data-test="automation-status"
                            variant={STATUS_VARIANTS[automation.status]}
                        >
                            {STATUS_LABELS[automation.status]}
                        </Badge>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        {canManage && (
                            <>
                                <p
                                    className="hidden text-xs text-muted-foreground lg:block"
                                    aria-live="polite"
                                >
                                    {saving
                                        ? 'Saving…'
                                        : hasUnsavedChanges
                                          ? 'Unsaved changes'
                                          : 'Saved'}
                                </p>
                                <Switch
                                    id="automation-active"
                                    className="hidden md:inline-flex"
                                    checked={automation.status === 'active'}
                                    disabled={saving || hasUnsavedChanges}
                                    data-test={
                                        automation.status === 'active'
                                            ? 'pause-automation-button'
                                            : 'activate-automation-button'
                                    }
                                    aria-label={
                                        automation.status === 'active'
                                            ? 'Pause automation'
                                            : 'Activate automation'
                                    }
                                    onCheckedChange={(checked) => {
                                        changeStatus(
                                            checked ? 'activate' : 'pause',
                                        );
                                    }}
                                />
                            </>
                        )}

                        <Button
                            size="sm"
                            variant="outline"
                            data-test="automation-activity-link"
                            render={
                                <Link
                                    href={activity([
                                        currentTeam.slug,
                                        automation.uuid,
                                    ])}
                                    prefetch
                                />
                            }
                        >
                            <HugeiconsIcon
                                icon={Activity03Icon}
                                data-icon="inline-start"
                            />
                            <span className="hidden sm:inline">Activity</span>
                        </Button>

                        <Button
                            size="sm"
                            variant="outline"
                            disabled={testing || saving}
                            aria-label="Test workflow"
                            data-test="test-automation-button"
                            onClick={() => void testWorkflow()}
                        >
                            {testing ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <HugeiconsIcon
                                    icon={PlayIcon}
                                    data-icon="inline-start"
                                />
                            )}
                            <span className="hidden sm:inline">
                                {testing ? 'Testing…' : 'Test'}
                            </span>
                        </Button>

                        {canManage && (
                            <Button
                                size="sm"
                                disabled={saving || !hasUnsavedChanges}
                                aria-busy={saving}
                                data-test="save-automation-button"
                                onClick={() => save()}
                            >
                                Save
                            </Button>
                        )}
                    </div>
                </header>

                <div className="relative flex min-h-0 flex-1 overflow-hidden">
                    <main className="relative min-w-0 flex-1 overflow-hidden">
                        {(canManage ||
                            (automation.trigger === 'api' &&
                                automation.trigger_token)) && (
                            <div
                                className="absolute top-4 left-4 z-10 flex items-center gap-2"
                                data-test="automation-canvas-toolbar"
                            >
                                {canManage && (
                                    <Popover
                                        open={elementsOpen}
                                        onOpenChange={(open) => {
                                            if (open && selected) {
                                                closeInspector();
                                            }

                                            setElementsOpen(open);
                                        }}
                                    >
                                        <PopoverTrigger
                                            render={
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    data-test="toggle-automation-elements"
                                                />
                                            }
                                        >
                                            <HugeiconsIcon
                                                icon={Add01Icon}
                                                data-icon="inline-start"
                                            />
                                            Add step
                                        </PopoverTrigger>
                                        <PopoverContent
                                            align="start"
                                            side="bottom"
                                            className="w-44 p-1"
                                            data-test="automation-step-palette"
                                        >
                                            <div className="flex flex-col gap-1">
                                                {NEW_STEPS.map((step) => (
                                                    <Button
                                                        key={step.type}
                                                        variant="ghost"
                                                        className="h-9 w-full justify-start gap-2 px-2 text-sm font-medium"
                                                        data-test={step.testId}
                                                        onClick={() =>
                                                            addStep(step)
                                                        }
                                                    >
                                                        <span
                                                            className={`flex size-6 shrink-0 items-center justify-center rounded-md ${step.iconClassName}`}
                                                        >
                                                            <HugeiconsIcon
                                                                icon={step.icon}
                                                                className="size-3.5"
                                                                data-icon="inline-start"
                                                            />
                                                        </span>
                                                        {step.label}
                                                    </Button>
                                                ))}
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                )}

                                {automation.trigger === 'api' &&
                                    automation.trigger_token && (
                                        <Popover>
                                            <PopoverTrigger
                                                render={
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        data-test="automation-api-trigger-button"
                                                    />
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={Key01Icon}
                                                    data-icon="inline-start"
                                                />
                                                API trigger
                                            </PopoverTrigger>
                                            <PopoverContent
                                                align="start"
                                                side="bottom"
                                                className="w-80"
                                            >
                                                <PopoverHeader>
                                                    <PopoverTitle>
                                                        API trigger
                                                    </PopoverTitle>
                                                    <PopoverDescription>
                                                        Send this token as{' '}
                                                        <code>
                                                            X-Automation-Token
                                                        </code>
                                                        . Anyone holding it can
                                                        enrol your subscribers.
                                                    </PopoverDescription>
                                                </PopoverHeader>
                                                <code className="rounded-md bg-muted p-3 font-mono text-xs break-all text-muted-foreground">
                                                    {automation.trigger_token}
                                                </code>
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        data-test="copy-automation-token"
                                                        onClick={() => {
                                                            void navigator.clipboard.writeText(
                                                                automation.trigger_token ??
                                                                    '',
                                                            );
                                                            toast.add({
                                                                type: 'success',
                                                                title: 'Token copied.',
                                                            });
                                                        }}
                                                    >
                                                        <HugeiconsIcon
                                                            icon={Copy01Icon}
                                                            data-icon="inline-start"
                                                        />
                                                        Copy
                                                    </Button>
                                                    {canManage && (
                                                        <Button
                                                            size="sm"
                                                            variant="secondary"
                                                            data-test="regenerate-automation-token"
                                                            onClick={() =>
                                                                router.post(
                                                                    regenerateToken.url(
                                                                        [
                                                                            currentTeam.slug,
                                                                            automation.uuid,
                                                                        ],
                                                                    ),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <HugeiconsIcon
                                                                icon={
                                                                    RefreshIcon
                                                                }
                                                                data-icon="inline-start"
                                                            />
                                                            Regenerate
                                                        </Button>
                                                    )}
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    )}
                            </div>
                        )}
                        <AutomationCanvas
                            nodes={nodes}
                            edges={edges}
                            onNodesChange={handleNodesChange}
                            onEdgesChange={handleEdgesChange}
                            onConnect={handleConnect}
                            readOnly={readOnly}
                            triggerLabels={triggerLabels}
                            actionLabels={actionLabels}
                            conditionLabels={conditionLabels}
                            sourceLabels={sourceLabels}
                            delayUnitLabels={delayUnitLabels}
                            tags={tags}
                            emails={transactionalEmails}
                            testingNodeId={testingNodeId}
                        />
                    </main>

                    {selected && (
                        <aside
                            className="absolute inset-y-0 right-0 z-20 w-full overflow-hidden border-l bg-background shadow-sm motion-safe:animate-in motion-safe:duration-200 motion-safe:fade-in-0 motion-safe:slide-in-from-right-4 sm:w-96 lg:relative lg:inset-auto lg:z-auto lg:shrink-0 lg:shadow-none"
                            data-test="automation-step-inspector"
                        >
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                className="absolute top-3 right-3"
                                aria-label="Close step settings"
                                data-test="close-automation-step-inspector"
                                onClick={closeInspector}
                            >
                                <HugeiconsIcon icon={Cancel01Icon} />
                            </Button>
                            <AutomationStepPanel
                                node={selected}
                                catalog={catalog}
                                audiences={audiences}
                                tags={tags}
                                emails={transactionalEmails}
                                readOnly={readOnly}
                                onChange={updateNodeData}
                                onDelete={deleteNode}
                            />
                        </aside>
                    )}
                </div>
            </div>
        </>
    );
}
