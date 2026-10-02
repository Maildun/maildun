import type {
    AutomationEdge,
    AutomationNode,
    AutomationNodeType,
} from '@/types';

export const AUTOMATION_STEP_DRAG_TYPE = 'application/maildun-automation-step';

export type AutomationPaletteStepType = Exclude<AutomationNodeType, 'trigger'>;

type MeasuredAutomationNode = AutomationNode & {
    measured?: { width?: number; height?: number };
};

export function automationStepPlacement(
    nodes: MeasuredAutomationNode[],
    edges: AutomationEdge[],
    selectedId?: string,
): { position: AutomationNode['position']; anchorId?: string } {
    const workflow = orderAutomationWorkflow(nodes, edges);
    const anchor =
        nodes.find((node) => node.id === selectedId) ??
        workflow.connected.at(-1) ??
        nodes.at(-1);

    if (!anchor) {
        return { position: { x: 320, y: 220 } };
    }

    const measuredAnchor = anchor as MeasuredAutomationNode;
    const y =
        anchor.position.y +
        Math.max(220, (measuredAnchor.measured?.height ?? 0) + 64);
    const width = 256;
    const height = 180;
    const clearance = 24;
    let column = 0;
    let position = { x: anchor.position.x, y };

    const overlaps = () =>
        nodes.some(
            (node) =>
                position.x <
                    node.position.x +
                        (node.measured?.width ?? width) +
                        clearance &&
                position.x + width + clearance > node.position.x &&
                position.y <
                    node.position.y +
                        (node.measured?.height ?? height) +
                        clearance &&
                position.y + height + clearance > node.position.y,
        );

    while (overlaps()) {
        column += 1;
        const offset = Math.ceil(column / 2) * (column % 2 === 1 ? 1 : -1);
        position = { x: anchor.position.x + offset * 320, y };
    }

    return { position, anchorId: anchor.id };
}

export function isAutomationPaletteStepType(
    value: string,
): value is AutomationPaletteStepType {
    return value === 'action' || value === 'condition' || value === 'delay';
}

export function automationConnectionError(
    nodes: AutomationNode[],
    edges: AutomationEdge[],
    connection: Pick<AutomationEdge, 'source' | 'target' | 'sourceHandle'>,
): string | null {
    const source = nodes.find((node) => node.id === connection.source);
    const target = nodes.find((node) => node.id === connection.target);

    if (!source || !target) {
        return 'Connect two steps on the canvas.';
    }

    if (source.id === target.id) {
        return 'A step cannot connect to itself.';
    }

    if (target.type === 'trigger') {
        return 'The trigger must be the first step.';
    }

    const handle = connection.sourceHandle ?? '';

    if (
        source.type === 'condition'
            ? handle !== 'yes' && handle !== 'no'
            : handle !== ''
    ) {
        return 'Connect from a valid output on this step.';
    }

    const incoming = edges.filter((edge) => edge.target === target.id);

    if (
        incoming.some(
            (edge) =>
                edge.source === source.id &&
                (edge.sourceHandle ?? '') === handle,
        )
    ) {
        return 'These steps are already connected.';
    }

    if (incoming.length > 0) {
        const other = incoming[0];
        const exclusiveConditionMerge =
            incoming.length === 1 &&
            source.type === 'condition' &&
            other.source === source.id &&
            ((handle === 'yes' && other.sourceHandle === 'no') ||
                (handle === 'no' && other.sourceHandle === 'yes'));

        if (!exclusiveConditionMerge) {
            return 'Parallel branches cannot merge into the same step. Connect to a separate step or remove the existing connection.';
        }
    }

    const outgoing = new Map<string, string[]>();

    for (const edge of edges) {
        const targets = outgoing.get(edge.source) ?? [];
        targets.push(edge.target);
        outgoing.set(edge.source, targets);
    }

    const pending = [target.id];
    const visited = new Set<string>();

    for (let index = 0; index < pending.length; index += 1) {
        const id = pending[index];

        if (id === source.id) {
            return 'This connection would create a loop.';
        }

        if (!visited.has(id)) {
            visited.add(id);
            pending.push(...(outgoing.get(id) ?? []));
        }
    }

    return null;
}

export function orderAutomationWorkflow(
    nodes: AutomationNode[],
    edges: AutomationEdge[],
): { connected: AutomationNode[]; disconnected: AutomationNode[] } {
    const nodesById = new Map(nodes.map((node) => [node.id, node]));
    const trigger = nodes.find((node) => node.type === 'trigger');

    if (!trigger) {
        return { connected: [], disconnected: nodes };
    }

    const outgoing = new Map<string, string[]>();
    const branchRank = (handle: string | null | undefined): number =>
        handle === 'yes' ? 0 : handle === 'no' ? 1 : 2;

    for (const edge of [...edges].sort(
        (first, second) =>
            branchRank(first.sourceHandle) - branchRank(second.sourceHandle),
    )) {
        if (!nodesById.has(edge.source) || !nodesById.has(edge.target)) {
            continue;
        }

        const targets = outgoing.get(edge.source) ?? [];

        if (!targets.includes(edge.target)) {
            targets.push(edge.target);
        }

        outgoing.set(edge.source, targets);
    }

    const reachable = new Set([trigger.id]);
    const discovered = [trigger.id];

    for (let index = 0; index < discovered.length; index += 1) {
        for (const target of outgoing.get(discovered[index]) ?? []) {
            if (!reachable.has(target)) {
                reachable.add(target);
                discovered.push(target);
            }
        }
    }

    const incoming = new Map(discovered.map((id) => [id, 0]));

    for (const source of discovered) {
        for (const target of outgoing.get(source) ?? []) {
            incoming.set(target, (incoming.get(target) ?? 0) + 1);
        }
    }

    const ordered = discovered.filter((id) => incoming.get(id) === 0);

    for (let index = 0; index < ordered.length; index += 1) {
        for (const target of outgoing.get(ordered[index]) ?? []) {
            const remaining = (incoming.get(target) ?? 0) - 1;
            incoming.set(target, remaining);

            if (remaining === 0) {
                ordered.push(target);
            }
        }
    }

    // Draft graphs can contain cycles; keep every reachable step visible once.
    const orderedIds = new Set(ordered);
    const connected = [
        ...ordered,
        ...discovered.filter((id) => !orderedIds.has(id)),
    ].map((id) => nodesById.get(id)!);

    return {
        connected,
        disconnected: nodes.filter((node) => !reachable.has(node.id)),
    };
}
