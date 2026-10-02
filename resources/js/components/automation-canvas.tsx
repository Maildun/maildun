import {
    Clock01Icon,
    FilterIcon,
    MailSend01Icon,
    Tag01Icon,
    WorkflowSquare10Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Background,
    BaseEdge,
    Controls,
    EdgeLabelRenderer,
    getSmoothStepPath,
    Handle,
    Position,
    ReactFlow,
    useNodesInitialized,
    useReactFlow,
} from '@xyflow/react';
import type {
    Connection,
    EdgeChange,
    EdgeProps,
    NodeChange,
    NodeProps,
    ReactFlowInstance,
    XYPosition,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { createContext, useContext, useEffect, useMemo, useRef } from 'react';
import type { DragEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from '@/components/ui/toast';
import { useMounted } from '@/hooks/use-mounted';
import {
    AUTOMATION_STEP_DRAG_TYPE,
    automationConnectionError,
    isAutomationPaletteStepType,
} from '@/lib/automation-workflow';
import type { AutomationPaletteStepType } from '@/lib/automation-workflow';
import { cn } from '@/lib/utils';
import type {
    AutomationActionData,
    AutomationConditionData,
    AutomationDelayData,
    AutomationEdge,
    AutomationEmailOption,
    AutomationNode,
    AutomationTagOption,
    AutomationTriggerData,
    AutomationTriggerKind,
} from '@/types';

type Lookups = {
    triggerLabels: Record<string, string>;
    actionLabels: Record<string, string>;
    conditionLabels: Record<string, string>;
    sourceLabels: Record<string, string>;
    delayUnitLabels: Record<string, string>;
    tags: AutomationTagOption[];
    emails: AutomationEmailOption[];
};

type Props = Lookups & {
    nodes: AutomationNode[];
    edges: AutomationEdge[];
    onNodesChange: (changes: NodeChange[]) => void;
    onEdgesChange: (changes: EdgeChange[]) => void;
    onConnect: (connection: Connection) => void;
    onAddStep: (type: AutomationPaletteStepType, position: XYPosition) => void;
    focusStepIds: string[] | null;
    readOnly: boolean;
    testingNodeId?: string | null;
};

function NodeShell({
    icon,
    iconClassName,
    kicker,
    title,
    detail,
    selected,
    testing,
    tone,
    hasBranches,
}: {
    icon: typeof Tag01Icon;
    iconClassName: string;
    kicker: string;
    title: string;
    detail?: string;
    selected?: boolean;
    testing?: boolean;
    tone: 'trigger' | 'action' | 'condition' | 'delay';
    hasBranches?: boolean;
}) {
    const variants = {
        trigger: 'success',
        action: 'info',
        condition: 'violet',
        delay: 'amber',
    } as const;

    return (
        <Card
            size="sm"
            body="seamless"
            className={cn(
                'w-64 gap-0 py-0 text-left shadow-xs transition-shadow hover:shadow-sm has-[>[data-slot=card-content]:last-child]:pb-0 data-[body=seamless]:py-0 data-[size=sm]:gap-0',
                selected && 'border-primary shadow-sm ring-4 ring-primary/20',
                testing && 'border-primary shadow-sm ring-2 ring-primary/30',
            )}
            data-test="automation-node-card"
        >
            <CardHeader className="relative pt-4 pr-16 pb-2 pl-4">
                <div
                    className={cn(
                        'absolute top-4 right-4 flex size-8 items-center justify-center rounded-lg',
                        iconClassName,
                    )}
                >
                    <HugeiconsIcon icon={icon} className="size-4" />
                </div>
                <div className="flex min-w-0 flex-col items-start gap-1.5">
                    <Badge variant={variants[tone]}>
                        {testing ? 'Testing' : kicker}
                    </Badge>
                    <CardTitle
                        className="line-clamp-2 break-words"
                        title={title}
                    >
                        {title}
                    </CardTitle>
                </div>
            </CardHeader>
            <CardContent className="px-4 pb-4">
                <p
                    className="line-clamp-2 text-xs leading-relaxed break-words text-muted-foreground"
                    title={detail}
                >
                    {detail ?? 'Select this step to configure it.'}
                </p>
            </CardContent>
            {hasBranches && (
                <CardFooter className="flex-col items-stretch gap-0 p-0">
                    <Separator />
                    <div className="flex items-stretch">
                        <div className="flex flex-1 justify-center py-2.5">
                            <Badge
                                variant="success"
                                data-test="automation-branch-yes"
                            >
                                True
                            </Badge>
                        </div>
                        <Separator orientation="vertical" />
                        <div className="flex flex-1 justify-center py-2.5">
                            <Badge
                                variant="zinc"
                                data-test="automation-branch-no"
                            >
                                False
                            </Badge>
                        </div>
                    </div>
                </CardFooter>
            )}
        </Card>
    );
}

const HANDLE_CLASS =
    '!size-3 !border-2 !border-background !bg-(--automation-connector) !shadow-sm';

type CanvasContextValue = Lookups & {
    testingNodeId?: string | null;
    tagName: (uuid: unknown) => string;
    emailName: (uuid: unknown) => string;
    sourceName: (source: unknown) => string;
};

const AutomationCanvasContext = createContext<CanvasContextValue | null>(null);

function useAutomationCanvasContext(): CanvasContextValue {
    const context = useContext(AutomationCanvasContext);

    if (!context) {
        throw new Error(
            'Automation nodes must render inside AutomationCanvas.',
        );
    }

    return context;
}

function TriggerNode({ id, data, selected }: NodeProps) {
    const { triggerLabels, tagName, testingNodeId } =
        useAutomationCanvasContext();
    const trigger = data as AutomationTriggerData;

    return (
        <>
            <NodeShell
                tone="trigger"
                icon={WorkflowSquare10Icon}
                iconClassName="bg-primary/15 text-primary"
                kicker="When"
                title={
                    triggerLabels[trigger.kind as AutomationTriggerKind] ??
                    'Choose a trigger'
                }
                detail={
                    trigger.kind === 'subscriber.tagged' && trigger.tag_uuid
                        ? `Tagged ${tagName(trigger.tag_uuid)}`
                        : undefined
                }
                selected={selected}
                testing={testingNodeId === id}
            />
            <Handle
                type="source"
                position={Position.Bottom}
                className={HANDLE_CLASS}
            />
        </>
    );
}

function ActionNode({ id, data, selected }: NodeProps) {
    const { actionLabels, emailName, tagName, testingNodeId } =
        useAutomationCanvasContext();
    const action = data as AutomationActionData;
    const iconClassName =
        action.kind === 'send_email'
            ? 'bg-info/15 text-info'
            : action.kind === 'remove_tag'
              ? 'bg-destructive/15 text-destructive'
              : 'bg-success/15 text-success';

    return (
        <>
            <Handle
                type="target"
                position={Position.Top}
                className={HANDLE_CLASS}
            />
            <NodeShell
                tone="action"
                icon={action.kind === 'send_email' ? MailSend01Icon : Tag01Icon}
                iconClassName={iconClassName}
                kicker="Then"
                title={actionLabels[action.kind] ?? 'Choose an action'}
                detail={
                    action.kind === 'send_email'
                        ? emailName(action.transactional_email_uuid)
                        : action.kind
                          ? tagName(action.tag_uuid)
                          : undefined
                }
                selected={selected}
                testing={testingNodeId === id}
            />
            <Handle
                type="source"
                position={Position.Bottom}
                className={HANDLE_CLASS}
            />
        </>
    );
}

function DelayNode({ id, data, selected }: NodeProps) {
    const { delayUnitLabels, testingNodeId } = useAutomationCanvasContext();
    const delay = data as AutomationDelayData;

    return (
        <>
            <Handle
                type="target"
                position={Position.Top}
                className={HANDLE_CLASS}
            />
            <NodeShell
                tone="delay"
                icon={Clock01Icon}
                iconClassName="bg-warning/15 text-warning"
                kicker="Wait"
                title={`${delay.amount ?? 1} ${
                    delayUnitLabels[delay.unit] ?? 'minutes'
                }`}
                detail="Continue after this wait."
                selected={selected}
                testing={testingNodeId === id}
            />
            <Handle
                type="source"
                position={Position.Bottom}
                className={HANDLE_CLASS}
            />
        </>
    );
}

function ConditionNode({ id, data, selected }: NodeProps) {
    const { conditionLabels, sourceName, tagName, testingNodeId } =
        useAutomationCanvasContext();
    const condition = data as AutomationConditionData;

    return (
        <>
            <Handle
                type="target"
                position={Position.Top}
                className={HANDLE_CLASS}
            />
            <NodeShell
                tone="condition"
                icon={FilterIcon}
                iconClassName="bg-violet-500/15 text-violet-600 dark:text-violet-400"
                kicker="If"
                title={conditionLabels[condition.kind] ?? 'Choose a condition'}
                detail={
                    condition.kind === 'has_tag'
                        ? tagName(condition.tag_uuid)
                        : condition.kind === 'source'
                          ? sourceName(condition.source)
                          : undefined
                }
                selected={selected}
                testing={testingNodeId === id}
                hasBranches
            />
            {/* Each branch is its own handle: an unwired branch ends the run. */}
            <Handle
                id="yes"
                type="source"
                position={Position.Bottom}
                style={{ left: '25%' }}
                className={cn(HANDLE_CLASS, '!bg-success')}
                aria-label="True"
            />
            <Handle
                id="no"
                type="source"
                position={Position.Bottom}
                style={{ left: '75%' }}
                className={cn(HANDLE_CLASS, '!bg-muted-foreground')}
                aria-label="False"
            />
        </>
    );
}

const NODE_TYPES = {
    trigger: TriggerNode,
    action: ActionNode,
    delay: DelayNode,
    condition: ConditionNode,
};

function AutomationEdge({
    id,
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    sourceHandleId,
    style,
    markerEnd,
}: EdgeProps) {
    const [edgePath, labelX, labelY] = getSmoothStepPath({
        sourceX,
        sourceY,
        sourcePosition,
        targetX,
        targetY,
        targetPosition,
    });
    const branch =
        sourceHandleId === 'yes'
            ? { label: 'True', variant: 'success' as const }
            : sourceHandleId === 'no'
              ? { label: 'False', variant: 'zinc' as const }
              : null;

    return (
        <>
            <BaseEdge
                id={id}
                path={edgePath}
                markerEnd={markerEnd}
                style={style}
            />
            {branch && (
                <EdgeLabelRenderer>
                    <div
                        className="nodrag nopan pointer-events-none absolute"
                        style={{
                            transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
                        }}
                        data-test={`automation-edge-${branch.label.toLowerCase()}`}
                    >
                        <Badge variant={branch.variant}>{branch.label}</Badge>
                    </div>
                </EdgeLabelRenderer>
            )}
        </>
    );
}

const EDGE_TYPES = {
    smoothstep: AutomationEdge,
};

const FIT_VIEW_OPTIONS = { padding: 0.24 };
const DEFAULT_EDGE_OPTIONS = {
    type: 'smoothstep' as const,
    style: { strokeWidth: 3 },
};

function FocusAddedStep({ ids }: { ids: string[] | null }) {
    const initialized = useNodesInitialized();
    const { fitView, getNode } = useReactFlow();
    const previous = useRef<string[] | null>(null);

    useEffect(() => {
        if (
            !initialized ||
            !ids ||
            previous.current === ids ||
            ids.some((id) => !getNode(id)?.measured?.width)
        ) {
            return;
        }

        previous.current = ids;
        void fitView({
            nodes: ids.map((id) => ({ id })),
            padding: 0.4,
            maxZoom: 1,
            duration: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 0
                : 250,
        });
    }, [fitView, getNode, ids, initialized]);

    return null;
}

export default function AutomationCanvas({
    nodes,
    edges,
    onNodesChange,
    onEdgesChange,
    onConnect,
    onAddStep,
    focusStepIds,
    readOnly,
    triggerLabels,
    actionLabels,
    conditionLabels,
    sourceLabels,
    delayUnitLabels,
    tags,
    emails,
    testingNodeId,
}: Props) {
    const mounted = useMounted();
    const flow = useRef<Pick<ReactFlowInstance, 'screenToFlowPosition'> | null>(
        null,
    );

    const handleDragOver = (event: DragEvent<HTMLDivElement>) => {
        if (
            readOnly ||
            !event.dataTransfer.types.includes(AUTOMATION_STEP_DRAG_TYPE)
        ) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    };

    const handleDrop = (event: DragEvent<HTMLDivElement>) => {
        const type = event.dataTransfer.getData(AUTOMATION_STEP_DRAG_TYPE);

        if (readOnly || !flow.current || !isAutomationPaletteStepType(type)) {
            return;
        }

        event.preventDefault();
        onAddStep(
            type,
            flow.current.screenToFlowPosition({
                x: event.clientX,
                y: event.clientY,
            }),
        );
    };
    const contextValue = useMemo<CanvasContextValue>(() => {
        const tagName = (uuid: unknown) =>
            tags.find((tag) => tag.uuid === uuid)?.name ?? 'No tag chosen';
        const emailName = (uuid: unknown) =>
            emails.find((email) => email.uuid === uuid)?.name ??
            'No email chosen';
        const sourceName = (source: unknown) =>
            sourceLabels[String(source)] ?? 'No source chosen';

        return {
            triggerLabels,
            actionLabels,
            conditionLabels,
            sourceLabels,
            delayUnitLabels,
            tags,
            emails,
            testingNodeId,
            tagName,
            emailName,
            sourceName,
        };
    }, [
        actionLabels,
        conditionLabels,
        delayUnitLabels,
        emails,
        sourceLabels,
        tags,
        testingNodeId,
        triggerLabels,
    ]);
    const displayEdges = useMemo(
        () =>
            edges.map((edge) => ({
                ...edge,
                type: 'smoothstep' as const,
                animated: testingNodeId !== null,
                style: {
                    stroke:
                        edge.sourceHandle === 'yes'
                            ? 'var(--color-success)'
                            : edge.sourceHandle === 'no'
                              ? 'var(--color-muted-foreground)'
                              : 'var(--automation-connector)',
                    strokeWidth: 3,
                },
            })),
        [edges, testingNodeId],
    );

    if (!mounted) {
        return <Skeleton className="size-full" />;
    }

    return (
        <AutomationCanvasContext.Provider value={contextValue}>
            <ReactFlow
                nodes={nodes}
                edges={displayEdges}
                nodeTypes={NODE_TYPES}
                edgeTypes={EDGE_TYPES}
                onNodesChange={onNodesChange}
                onEdgesChange={onEdgesChange}
                onConnect={onConnect}
                isValidConnection={(connection) =>
                    !readOnly &&
                    automationConnectionError(nodes, edges, connection) === null
                }
                onConnectEnd={(_, { isValid, fromHandle, toHandle }) => {
                    if (
                        readOnly ||
                        isValid ||
                        !fromHandle ||
                        !toHandle ||
                        fromHandle.type === toHandle.type
                    ) {
                        return;
                    }

                    const source =
                        fromHandle.type === 'source' ? fromHandle : toHandle;
                    const target =
                        fromHandle.type === 'target' ? fromHandle : toHandle;
                    const error = automationConnectionError(nodes, edges, {
                        source: source.nodeId,
                        target: target.nodeId,
                        sourceHandle: source.id,
                    });

                    if (error) {
                        toast.add({
                            type: 'error',
                            title: 'Connection not allowed',
                            description: error,
                        });
                    }
                }}
                onInit={(instance) => {
                    flow.current = instance;
                }}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
                nodesDraggable={!readOnly}
                nodesConnectable={!readOnly}
                edgesReconnectable={!readOnly}
                deleteKeyCode={readOnly ? null : ['Backspace', 'Delete']}
                fitView
                fitViewOptions={FIT_VIEW_OPTIONS}
                proOptions={{ hideAttribution: true }}
                defaultEdgeOptions={DEFAULT_EDGE_OPTIONS}
                connectionLineStyle={{
                    stroke: 'var(--automation-connector)',
                    strokeWidth: 3,
                }}
                className="bg-muted [--automation-connector:var(--primary)] [--xy-background-color:var(--muted)] dark:[--automation-connector:var(--info)]"
                data-test="automation-canvas"
            >
                <FocusAddedStep ids={focusStepIds} />
                <Background gap={20} size={1} />
                <Controls
                    showInteractive={false}
                    orientation="horizontal"
                    className="overflow-hidden rounded-md border bg-background shadow-xs [--xy-controls-button-background-color-hover:var(--muted)] [--xy-controls-button-background-color:var(--background)] [--xy-controls-button-border-color:var(--border)] [--xy-controls-button-color-hover:var(--foreground)] [--xy-controls-button-color:var(--foreground)]"
                />
            </ReactFlow>
        </AutomationCanvasContext.Provider>
    );
}
