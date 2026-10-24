import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createDesignAssistantParser } from '../../resources/js/lib/design-assistant-stream.ts';
import type { DesignAssistantEvent } from '../../resources/js/lib/design-assistant-stream.ts';

function parse(chunks: string[]): DesignAssistantEvent[] {
    const events: DesignAssistantEvent[] = [];
    const feed = createDesignAssistantParser((event) => events.push(event));

    for (const chunk of chunks) {
        feed(chunk);
    }

    return events;
}

test('parses assistant events split across network chunks', () => {
    const events = parse([
        'event: text\ndata: {"delta":"Hel',
        'lo"}\n\nevent: tool\ndata: {"name":"insert_section","detail":"hero"}\n\nevent: tool\ndata: {"name":"check_email"}\n\n',
        'event: proposal\ndata: {"ops":[{"op":"remove","id":"a"}],"warnings":[]}\n\nevent: done\ndata: {"ok":true}\n\n',
    ]);

    assert.deepEqual(events, [
        { type: 'text', delta: 'Hello' },
        { type: 'tool', name: 'insert_section', detail: 'hero' },
        { type: 'tool', name: 'check_email', detail: null },
        { type: 'proposal', ops: [{ op: 'remove', id: 'a' }], warnings: [] },
        { type: 'done' },
    ]);
});

test('keeps JSON-escaped markup and ignores unknown or malformed events', () => {
    const events = parse([
        'event: text\ndata: {"delta":"\\u003Cb\\u003E"}\r\n\r\n',
        'event: ping\ndata: {}\n\n',
        'event: text\ndata: not json\n\n',
    ]);

    assert.deepEqual(events, [{ type: 'text', delta: '<b>' }]);
});
