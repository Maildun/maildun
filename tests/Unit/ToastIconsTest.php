<?php

test('toaster uses solid status icons with semantic colors', function () {
    $toaster = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/toast.tsx');

    expect($toaster)->toBeString()
        ->toContain('from "@/components/icons/toast-status-icons"')
        ->toContain('<CheckmarkCircleSolidIcon className="text-success" aria-hidden="true" />')
        ->toContain('<InformationCircleSolidIcon className="text-info" aria-hidden="true" />')
        ->toContain('<AlertCircleSolidIcon className="text-warning" aria-hidden="true" />')
        ->toContain('className="text-destructive"')
        ->toContain('MultiplicationSignCircleSolidIcon')
        ->not->toContain('CheckmarkCircle02Icon')
        ->not->toContain('Alert02Icon')
        ->not->toContain('position="top-center"')
        ->not->toContain('ToastStatusIcon');
});

test('toaster stacks toasts with the Base UI viewport', function () {
    $toaster = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/toast.tsx');

    expect($toaster)->toBeString()
        ->toContain('ToastPrimitive.createToastManager<ToastData>()')
        ->toContain('function ToastList()')
        ->toContain('<Toast key={toastItem.id} toast={toastItem}>')
        ->toContain('toasts.map((toastItem)')
        ->toContain('limit = 5')
        // Collapsed stack: roots are absolute so they overlap, clamped to the
        // frontmost height, then scaled and peeked apart by --toast-index.
        ->toContain('absolute right-0 bottom-0')
        ->toContain('h-[var(--toast-frontmost-height,auto)]')
        ->toContain('z-[calc(1000-var(--toast-index))]')
        ->toContain('scale(calc(1-(var(--toast-index)*0.05)))')
        ->toContain('var(--toast-index)*-20%')
        // Expanded on hover/focus: natural heights, offset by the cumulative
        // height of the toasts in front.
        ->toContain('data-expanded:h-[var(--toast-height,auto)]')
        ->toContain('var(--toast-offset-y,0px)')
        ->toContain('[&[data-behind]:not([data-expanded])]:opacity-0')
        ->not->toContain('Toast.Positioner')
        ->not->toContain('ToastPrimitive.Positioner')
        // The flat flex list cannot stack: every toast renders full size.
        ->not->toContain('flex-col-reverse');
});

test('app mounts the stacked toaster', function () {
    // The toaster lives in AppProviders, not the Inertia entry: a component in
    // app.tsx makes it a Fast Refresh boundary and remounts a second React root.
    // See .ai/rules/js.md "Keep the Inertia entry free of components".
    $providers = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/app-providers.tsx');

    expect($providers)->toBeString()
        ->toContain("from '@/components/ui/toast'")
        ->toContain('<Toaster />')
        ->not->toContain("from '@/components/ui/sonner'");

    $app = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.tsx');

    expect($app)->toBeString()
        ->toContain('<AppProviders>{app}</AppProviders>')
        ->not->toContain("from '@/components/ui/sonner'");
});

test('flash toasts use the stacked toast manager', function () {
    $hook = file_get_contents(dirname(__DIR__, 2).'/resources/js/hooks/use-flash-toast.ts');

    expect($hook)->toBeString()
        ->toContain("from '@/components/ui/toast'")
        ->toContain('toast.add({')
        ->not->toContain("from 'sonner'")
        // Base UI dedupes on id and updates the existing toast in place, so
        // reusing the message as an id collapses repeated actions into one
        // toast instead of stacking them.
        ->not->toContain('id: data.message');
});

test('toast status icons are solid currentColor glyphs', function () {
    $icons = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/icons/toast-status-icons.tsx');

    expect($icons)->toBeString()
        ->toContain('fill="currentColor"')
        ->toContain('export function CheckmarkCircleSolidIcon')
        ->toContain('export function InformationCircleSolidIcon')
        ->toContain('export function AlertCircleSolidIcon')
        ->toContain('export function MultiplicationSignCircleSolidIcon');
});
