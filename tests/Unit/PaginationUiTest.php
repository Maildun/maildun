<?php

test('paginator uses the shared pagination composition for Inertia links', function () {
    $root = dirname(__DIR__, 2);
    $paginator = file_get_contents($root.'/resources/js/components/paginator.tsx');
    $pagination = file_get_contents($root.'/resources/js/components/ui/pagination.tsx');

    expect($paginator)->toBeString()
        ->toContain('PaginationPrevious')
        ->toContain('PaginationLink')
        ->toContain('PaginationEllipsis')
        ->toContain('PaginationNext')
        ->toContain('<Link href={link.url} preserveScroll />')
        ->not->toContain("from '@/components/ui/button'");

    expect($pagination)->toBeString()
        ->toContain('render ?? <a {...props} />')
        ->toContain('disabled = false')
        ->toContain('disabled={disabled}');
});
