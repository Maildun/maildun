export type AutomationStatus = 'draft' | 'active' | 'paused';

export type AutomationTriggerKind =
    | 'subscriber.subscribed'
    | 'subscriber.unsubscribed'
    | 'subscriber.resubscribed'
    | 'subscriber.tagged'
    | 'api';

export type AutomationActionKind = 'send_email' | 'add_tag' | 'remove_tag';

export type AutomationConditionKind = 'has_tag' | 'is_subscribed' | 'source';

export type AutomationDelayUnit = 'minutes' | 'hours' | 'days';

export type AutomationNodeType = 'trigger' | 'action' | 'delay' | 'condition';

export type AutomationTriggerData = {
    kind: AutomationTriggerKind;
    audience_uuid?: string | null;
    tag_uuid?: string | null;
};

export type AutomationActionData = {
    kind: AutomationActionKind | '';
    transactional_email_uuid?: string | null;
    tag_uuid?: string | null;
};

export type AutomationDelayData = {
    amount: number;
    unit: AutomationDelayUnit;
};

export type AutomationConditionData = {
    kind: AutomationConditionKind | '';
    tag_uuid?: string | null;
    source?: 'manual' | 'form' | null;
};

export type AutomationNodeData =
    | AutomationTriggerData
    | AutomationActionData
    | AutomationDelayData
    | AutomationConditionData;

export type AutomationNode = {
    id: string;
    type: AutomationNodeType;
    position: { x: number; y: number };
    deletable?: boolean;
    data: AutomationNodeData;
};

export type AutomationEdge = {
    id: string;
    source: string;
    target: string;
    sourceHandle?: string | null;
    targetHandle?: string | null;
};

export type AutomationGraph = {
    nodes: AutomationNode[];
    edges: AutomationEdge[];
};

export type AutomationSummary = {
    uuid: string;
    name: string;
    description: string | null;
    status: AutomationStatus;
    trigger: AutomationTriggerKind;
    trigger_label: string;
    enrolled_count: number;
    running_count: number;
    updated_at: string | null;
};

export type AutomationDetail = {
    uuid: string;
    name: string;
    description: string | null;
    status: AutomationStatus;
    trigger: AutomationTriggerKind;
    graph: AutomationGraph;
    trigger_token: string | null;
    updated_at: string | null;
};

export type AutomationCatalogOption = { value: string; label: string };

export type AutomationCatalog = {
    triggers: AutomationCatalogOption[];
    actions: AutomationCatalogOption[];
    conditions: AutomationCatalogOption[];
    sources: AutomationCatalogOption[];
    delayUnits: AutomationCatalogOption[];
};

export type AutomationAudienceOption = { uuid: string; name: string };

export type AutomationTagOption = { uuid: string; name: string; color: string };

export type AutomationEmailOption = {
    uuid: string;
    name: string;
    subject: string;
};

export type AutomationRunStatus =
    'pending' | 'running' | 'waiting' | 'completed' | 'failed' | 'cancelled';

export type AutomationRunStep = {
    uuid: string;
    label: string;
    detail: string | null;
    status: string;
    processed_at: string | null;
};

export type AutomationRunRow = {
    uuid: string;
    status: AutomationRunStatus;
    status_label: string;
    subscriber: {
        uuid: string;
        audience_uuid: string | null;
        email: string;
        name: string | null;
    } | null;
    current_step: string | null;
    failure_reason: string | null;
    started_at: string | null;
    scheduled_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    steps: AutomationRunStep[];
};

export type AutomationActivityFilters = {
    q: string;
    status: string;
};

export type AutomationActivitySummary = {
    enrolled: number;
    in_flight: number;
    completed: number;
    failed: number;
};
