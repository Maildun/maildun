<?php

function automationFile(string $path): string
{
    return file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

test('sidebar lists automations after transactional', function () {
    $sidebar = automationFile('resources/js/components/app-sidebar.tsx');

    expect($sidebar)->toBeString()
        ->toContain("title: 'Automations'")
        ->toContain('NodeEditIcon')
        ->toContain("import { index as automations } from '@/routes/automations';")
        ->toMatch("/title: 'Transactional'[\s\S]*title: 'Automations'/");
});

test('the automation list shows status, counts and lifecycle actions', function () {
    $index = automationFile('resources/js/pages/automations/index.tsx');

    expect($index)->toBeString()
        ->toContain('<Head title="Automations" />')
        ->toContain('<EmptyTitle>No automations yet</EmptyTitle>')
        ->toContain('data-test="automation-row"')
        ->toContain('data-test="automation-name-link"')
        ->toContain('className="font-medium underline-offset-4 hover:underline"')
        ->toContain('data-test="automation-status"')
        ->toContain('data-test="create-automation-button"')
        ->toContain('data-test="activate-automation-button"')
        ->toContain('data-test="pause-automation-button"')
        ->toContain('data-test="delete-automation-button"')
        ->toContain('Edit03Icon')
        ->toContain('In flight');
});

test('the editor renders a full screen xyflow canvas with workflow controls', function () {
    $app = automationFile('resources/js/app.tsx');
    $edit = automationFile('resources/js/pages/automations/edit.tsx');
    $canvas = automationFile('resources/js/components/automation-canvas.tsx');

    expect($app)->toContain("case name === 'automations/edit':");

    expect($canvas)->toBeString()
        ->toContain("from '@xyflow/react'")
        ->toContain("import '@xyflow/react/dist/style.css';")
        ->toContain('data-test="automation-canvas"')
        ->toContain('<Background gap={20} size={1} />')
        ->toContain("type: 'smoothstep' as const")
        ->toContain('strokeWidth: 3')
        ->toContain('animated: testingNodeId !== null')
        ->toContain('orientation="horizontal"')
        ->toContain('[--xy-controls-button-background-color:var(--background)]')
        ->toContain('[--xy-controls-button-background-color-hover:var(--muted)]')
        ->toContain('[--xy-controls-button-border-color:var(--border)]')
        ->toContain('[--xy-controls-button-color:var(--foreground)]');

    expect($edit)->toBeString()
        ->toContain('className="relative flex h-dvh min-h-0 w-full')
        ->toContain('data-test="automation-editor-navbar"')
        ->toContain('data-test="automation-step-palette"')
        ->toContain('data-test="automation-canvas-toolbar"')
        ->toContain('data-test="toggle-automation-elements"')
        ->toContain('data-test="close-automation-step-inspector"')
        ->toContain('data-test="test-automation-button"')
        ->toContain('data-test="edit-automation-details-button"')
        ->toContain("title: 'Visual test completed.'")
        ->toContain("testId: 'add-action-step'")
        ->toContain("testId: 'add-condition-step'")
        ->toContain("testId: 'add-delay-step'")
        ->toContain('data-test={step.testId}')
        ->toContain('data-test="save-automation-button"')
        ->toContain("? 'pause-automation-button'")
        ->toContain(": 'activate-automation-button'");
});

test('automation nodes use the full card composition and support test state', function () {
    $canvas = automationFile('resources/js/components/automation-canvas.tsx');

    expect($canvas)->toBeString()
        ->toContain('<Card')
        ->toContain('<CardHeader')
        ->toContain('<CardContent')
        ->toContain('<CardFooter')
        ->toContain('data-test="automation-node-card"')
        ->toContain('w-64 gap-0 py-0 text-left shadow-xs')
        ->toContain("detail ?? 'Select this step to configure it.'")
        ->toContain('testingNodeId?: string | null;')
        ->toContain("testing ? 'Testing' : kicker")
        ->toContain('bg-primary/15 text-primary')
        ->toContain('bg-success/15 text-success')
        ->toContain('bg-info/15 text-info')
        ->toContain('bg-destructive/15 text-destructive')
        ->toContain('bg-warning/15 text-warning')
        ->toContain('bg-violet-500/15 text-violet-600')
        ->not->toContain('rounded-lg bg-muted/60 p-3')
        ->not->toContain("testing ? 'Testing' : 'Ready'");
});

test('the editor uses a contextual step menu and docked inspector', function () {
    $edit = automationFile('resources/js/pages/automations/edit.tsx');

    expect($edit)->toBeString()
        ->toContain('const [elementsOpen, setElementsOpen] = useState(false);')
        ->toContain('<Popover')
        ->toContain('className="w-44 p-1"')
        ->toContain('className="h-9 w-full justify-start gap-2 px-2 text-sm font-medium"')
        ->toContain('setElementsOpen(false);')
        ->toContain('lg:relative lg:inset-auto')
        ->toContain('lg:shrink-0 lg:shadow-none')
        ->toContain('motion-safe:slide-in-from-right-4')
        ->toContain('motion-safe:duration-200')
        ->toContain("iconClassName: 'bg-info/15 text-info'")
        ->toContain("iconClassName: 'bg-warning/15 text-warning'")
        ->toContain('size-6 shrink-0 items-center justify-center rounded-md')
        ->not->toContain('absolute top-1/2 left-4')
        ->not->toContain('MagicWand02Icon')
        ->not->toContain('Grid2X2Icon');
});

test('the canvas is gated behind useMounted so SSR paints a placeholder', function () {
    $canvas = automationFile('resources/js/components/automation-canvas.tsx');

    expect($canvas)->toBeString()
        ->toContain("import { useMounted } from '@/hooks/use-mounted';")
        ->toContain('const mounted = useMounted();')
        ->toContain('if (!mounted) {')
        ->toMatch('/if \(!mounted\) \{\s*return <Skeleton/');
});

test('a condition node exposes separate yes and no handles', function () {
    $canvas = automationFile('resources/js/components/automation-canvas.tsx');
    $panel = automationFile('resources/js/components/automation-step-panel.tsx');

    expect($canvas)->toBeString()
        ->toContain('id="yes"')
        ->toContain('id="no"')
        ->toContain('hasBranches')
        ->toContain('data-test="automation-branch-yes"')
        ->toContain('data-test="automation-branch-no"')
        ->toContain('True')
        ->toContain('False')
        ->toContain('aria-label="True"')
        ->toContain('aria-label="False"')
        ->toContain("sourceHandleId === 'yes'")
        ->toContain("sourceHandleId === 'no'")
        ->toContain('data-test={`automation-edge-${branch.label.toLowerCase()}`}')
        ->toContain("edge.sourceHandle === 'yes'")
        ->toContain("edge.sourceHandle === 'no'");

    expect($panel)->toBeString()
        ->toContain('The left handle is True, the right one is')
        ->toContain('False. A branch you leave unwired ends the');
});

test('the saved graph drops the view-only keys xyflow adds', function () {
    $edit = automationFile('resources/js/pages/automations/edit.tsx');

    expect($edit)->toBeString()
        ->toContain('function toGraphPayload(')
        ->toContain('const graphPayload = toGraphPayload(nodes, edges);')
        ->toContain('sourceHandle: edge.sourceHandle ?? null,')
        ->toContain("{ ...connection, type: 'smoothstep' }")
        ->not->toContain('graph: { nodes, edges }');
});

test('the editor navbar has one save notification source and clear save states', function () {
    $edit = automationFile('resources/js/pages/automations/edit.tsx');

    expect($edit)->toBeString()
        ->toContain('const hasUnsavedChanges =')
        ->toContain("? 'Unsaved changes'")
        ->toContain('disabled={saving || !hasUnsavedChanges}')
        ->toContain('disabled={saving || hasUnsavedChanges}')
        ->toMatch('/data-test="save-automation-button"[\s\S]*?>\s*Save\s*<\/Button>/')
        ->not->toContain('FloppyDiskIcon')
        ->not->toContain("title: 'Automation saved.'");
});

test('automation details are edited in a dialog before the workflow is saved', function () {
    $edit = automationFile('resources/js/pages/automations/edit.tsx');

    expect($edit)->toBeString()
        ->toContain('<DialogTitle>')
        ->toContain('Edit automation details')
        ->toContain('data-test="edit-automation-details-button"')
        ->toContain('data-test="apply-automation-details"')
        ->toContain('<FieldGroup>')
        ->toContain('icon={Edit03Icon}')
        ->toContain('group-hover:bg-accent group-hover:text-foreground')
        ->toContain('setName(nextName)')
        ->toContain('setDescription(detailsDescription.trim())')
        ->not->toContain('className="w-40 sm:w-52"')
        ->not->toContain('className="hidden w-72 xl:flex"');
});

test('the api token panel warns about sharing and can be regenerated', function () {
    $edit = automationFile('resources/js/pages/automations/edit.tsx');

    expect($edit)->toBeString()
        ->toContain('data-test="copy-automation-token"')
        ->toContain('data-test="regenerate-automation-token"')
        ->toContain('X-Automation-Token')
        ->toMatch('/Anyone holding it can\s+enrol your subscribers\./');
});

test('every automation form input carries a placeholder', function () {
    $panel = automationFile('resources/js/components/automation-step-panel.tsx');
    $dialog = automationFile('resources/js/components/create-automation-dialog.tsx');
    $edit = automationFile('resources/js/pages/automations/edit.tsx');

    expect($panel)->toBeString()
        ->toContain('placeholder="Choose a tag"')
        ->toContain('placeholder="Choose an action"')
        ->toContain('placeholder="Choose a condition"')
        ->toContain('data-test="automation-source-select"')
        ->toContain('placeholder="Choose a source"')
        ->toContain('catalog.sources.map(')
        ->toContain('placeholder="Choose a trigger"')
        ->toContain('placeholder="Any audience"')
        ->toContain('placeholder="2"');

    expect($dialog)->toBeString()
        ->toContain('placeholder="Welcome series"')
        ->toContain('placeholder="Greets everyone who joins the newsletter."')
        ->toContain('type="submit"');

    expect($edit)->toBeString()
        ->toContain('placeholder="Welcome series"')
        ->toContain('placeholder="Greets everyone who joins the newsletter."')
        ->toContain('type="submit"');
});

test('the activity page shows run rows with an expandable step trail', function () {
    $activity = automationFile('resources/js/pages/automations/activity.tsx');

    expect($activity)->toBeString()
        ->toContain('data-test="automation-run-row"')
        ->toContain('data-test="automation-run-steps"')
        ->toContain('data-test="automation-run-step"')
        ->toContain('data-test="toggle-run-steps"')
        ->toContain('data-test="run-status"')
        // The shared filter bar, not a hand-rolled one.
        ->toContain("import { useListFilters } from '@/hooks/use-list-filters';")
        ->toContain('<FilterMenu')
        ->toContain('<ListSearch')
        ->toContain('<ActiveFilters')
        ->toContain('StatusIcon')
        ->toContain('<Paginator paginator={runs} />');
});

test('both the list and the editor link to activity', function () {
    expect(automationFile('resources/js/pages/automations/index.tsx'))
        ->toContain('data-test="automation-activity-link"')
        ->toContain('activate, activity, edit, index, pause');

    expect(automationFile('resources/js/pages/automations/edit.tsx'))
        ->toContain('data-test="automation-activity-link"')
        ->toContain('Activity03Icon');
});

test('the activity page uses a future-facing formatter for a parked run', function () {
    // formatRelativeTime measures value -> now, so a future scheduled_at would
    // collapse to "Now" and a parked run would claim it resumes immediately.
    $format = automationFile('resources/js/lib/format.ts');
    $activity = automationFile('resources/js/pages/automations/activity.tsx');

    expect($format)->toContain('export function formatTimeUntil')
        ->toContain('new Date(value).getTime() - Date.now()');

    expect($activity)->toContain('formatTimeUntil(')
        ->toMatch('/Resumes\{\' \'\}\s*\{formatTimeUntil\(/');
});
