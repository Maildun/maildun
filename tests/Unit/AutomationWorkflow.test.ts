import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    automationConnectionError,
    isAutomationPaletteStepType,
    orderAutomationWorkflow,
} from '../../resources/js/lib/automation-workflow.ts';
import type {
    AutomationEdge,
    AutomationNode,
} from '../../resources/js/types/automations.ts';

function node(
    id: string,
    type: AutomationNode['type'] = 'action',
): AutomationNode {
    return { id, type, position: { x: 0, y: 0 }, data: { kind: '' } };
}

function edge(
    source: string,
    target: string,
    sourceHandle?: string,
): AutomationEdge {
    return {
        id: `${source}-${target}-${sourceHandle ?? ''}`,
        source,
        target,
        sourceHandle,
    };
}

test('connections allow a sequence and independent outgoing branches', () => {
    const nodes = [
        node('start', 'trigger'),
        node('tag'),
        node('wait', 'delay'),
        node('finish'),
    ];
    const edges = [edge('start', 'tag')];
    const before = structuredClone({ nodes, edges });

    assert.equal(
        automationConnectionError(nodes, edges, edge('tag', 'wait')),
        null,
    );
    assert.equal(
        automationConnectionError(nodes, edges, edge('start', 'wait')),
        null,
    );
    assert.equal(
        automationConnectionError(nodes, edges, edge('tag', 'finish')),
        null,
    );
    assert.deepEqual({ nodes, edges }, before);
});

test('the subscription and tag paths cannot both connect to one wait', () => {
    const nodes = [
        node('start', 'trigger'),
        node('tag'),
        node('wait', 'delay'),
    ];

    for (const [existing, candidate] of [
        [edge('start', 'wait'), edge('tag', 'wait')],
        [edge('tag', 'wait'), edge('start', 'wait')],
    ]) {
        assert.equal(
            automationConnectionError(
                nodes,
                [edge('start', 'tag'), existing],
                candidate,
            ),
            'Parallel branches cannot merge into the same step. Connect to a separate step or remove the existing connection.',
        );
    }
});

test('True and False can connect to separate steps or directly to one shared step', () => {
    const nodes = [node('check', 'condition'), node('yes'), node('no')];

    for (const [existingHandle, newHandle] of [
        ['yes', 'no'],
        ['no', 'yes'],
    ]) {
        const edges = [edge('check', 'yes', existingHandle)];

        assert.equal(
            automationConnectionError(
                nodes,
                edges,
                edge('check', 'no', newHandle),
            ),
            null,
        );
        assert.equal(
            automationConnectionError(
                nodes,
                edges,
                edge('check', 'yes', newHandle),
            ),
            null,
        );
    }
});

test('a shared condition target refuses an additional incoming branch', () => {
    const nodes = [node('check', 'condition'), node('other'), node('shared')];
    const edges = [
        edge('check', 'shared', 'yes'),
        edge('check', 'shared', 'no'),
    ];

    assert.equal(
        automationConnectionError(nodes, edges, edge('other', 'shared')),
        'Parallel branches cannot merge into the same step. Connect to a separate step or remove the existing connection.',
    );
});

test('connections reject missing steps, self links and links into the trigger', () => {
    const nodes = [node('start', 'trigger'), node('tag')];

    assert.equal(
        automationConnectionError(nodes, [], edge('missing', 'tag')),
        'Connect two steps on the canvas.',
    );
    assert.equal(
        automationConnectionError(nodes, [], edge('tag', 'missing')),
        'Connect two steps on the canvas.',
    );
    assert.equal(
        automationConnectionError(nodes, [], edge('tag', 'tag')),
        'A step cannot connect to itself.',
    );
    assert.equal(
        automationConnectionError(nodes, [], edge('tag', 'start')),
        'The trigger must be the first step.',
    );
});

test('connections reject duplicates and invalid output handles', () => {
    const nodes = [
        node('check', 'condition'),
        node('tag'),
        node('wait', 'delay'),
    ];

    assert.equal(
        automationConnectionError(
            nodes,
            [edge('tag', 'wait')],
            edge('tag', 'wait'),
        ),
        'These steps are already connected.',
    );
    assert.equal(
        automationConnectionError(
            nodes,
            [edge('check', 'tag', 'yes')],
            edge('check', 'tag', 'yes'),
        ),
        'These steps are already connected.',
    );

    for (const connection of [
        edge('check', 'tag'),
        edge('check', 'tag', 'unknown'),
        edge('tag', 'wait', 'yes'),
    ]) {
        assert.equal(
            automationConnectionError(nodes, [], connection),
            'Connect from a valid output on this step.',
        );
    }
});

test('connections cannot close a loop and tolerate an unrelated draft cycle', () => {
    const nodes = [node('first'), node('second'), node('third'), node('other')];
    const edges = [edge('first', 'second'), edge('second', 'third')];

    assert.equal(
        automationConnectionError(nodes, edges, edge('third', 'first')),
        'This connection would create a loop.',
    );
    assert.equal(
        automationConnectionError(
            nodes,
            [...edges, edge('third', 'second')],
            edge('other', 'first'),
        ),
        null,
    );
});

test('workflow follows connections instead of insertion order or canvas positions', () => {
    const nodes = [
        node('finish'),
        node('start', 'trigger'),
        node('wait', 'delay'),
    ];
    const edges = [edge('wait', 'finish'), edge('start', 'wait')];
    const before = structuredClone({ nodes, edges });

    const workflow = orderAutomationWorkflow(nodes, edges);

    assert.deepEqual(
        workflow.connected.map((node) => node.id),
        ['start', 'wait', 'finish'],
    );
    assert.deepEqual(workflow.disconnected, []);
    assert.deepEqual({ nodes, edges }, before);
});

test('True comes before False and a merged step follows both incoming branches', () => {
    const nodes = [
        node('merge'),
        node('false'),
        node('true'),
        node('condition', 'condition'),
        node('start', 'trigger'),
    ];
    const edges = [
        edge('condition', 'false', 'no'),
        edge('false', 'merge'),
        edge('condition', 'true', 'yes'),
        edge('true', 'merge'),
        edge('start', 'condition'),
    ];

    const workflow = orderAutomationWorkflow(nodes, edges);

    assert.deepEqual(
        workflow.connected.map((node) => node.id),
        ['start', 'condition', 'true', 'false', 'merge'],
    );
});

test('detached steps remain separate and edges to missing nodes are ignored', () => {
    const nodes = [node('detached'), node('start', 'trigger'), node('next')];
    const edges = [
        edge('start', 'missing'),
        edge('missing', 'detached'),
        edge('start', 'next'),
    ];

    const workflow = orderAutomationWorkflow(nodes, edges);

    assert.deepEqual(
        workflow.connected.map((node) => node.id),
        ['start', 'next'],
    );
    assert.deepEqual(
        workflow.disconnected.map((node) => node.id),
        ['detached'],
    );
    assert.deepEqual(orderAutomationWorkflow([node('detached')], []), {
        connected: [],
        disconnected: [node('detached')],
    });
});

test('a draft cycle lists each reachable step once without losing detached steps', () => {
    const nodes = [
        node('start', 'trigger'),
        node('first'),
        node('second'),
        node('detached'),
    ];

    const workflow = orderAutomationWorkflow(nodes, [
        edge('start', 'first'),
        edge('first', 'second'),
        edge('second', 'first'),
    ]);

    assert.deepEqual(
        workflow.connected.map((node) => node.id),
        ['start', 'first', 'second'],
    );
    assert.deepEqual(
        workflow.disconnected.map((node) => node.id),
        ['detached'],
    );
});

test('drops accept only palette step types and never a second trigger or unknown data', () => {
    for (const type of ['action', 'condition', 'delay']) {
        assert.equal(isAutomationPaletteStepType(type), true);
    }

    for (const type of ['trigger', '', 'file', 'unknown']) {
        assert.equal(isAutomationPaletteStepType(type), false);
    }
});
