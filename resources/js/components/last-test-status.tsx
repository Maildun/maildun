import { usePoll } from '@inertiajs/react';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { LastTestSend } from '@/types';

/**
 * Says how the latest test copy actually went. While the test is still
 * queued it polls the page prop that carries it, so the line turns into
 * "sent" or "failed" without a reload.
 */
export function LastTestStatus({
    test,
    pollProp,
    className,
}: {
    test: LastTestSend | null;
    pollProp: string;
    className?: string;
}) {
    if (!test) {
        return null;
    }

    return (
        <p
            className={cn(
                'text-sm',
                test.status === 'failed'
                    ? 'text-destructive'
                    : 'text-muted-foreground',
                className,
            )}
            data-test="last-test-status"
        >
            {test.status === 'queued' && <LastTestPoller prop={pollProp} />}
            {test.status === 'queued'
                ? `Sending a test to ${test.recipient}…`
                : test.status === 'sent'
                  ? `Test sent to ${test.recipient}${test.tested_at ? ` ${formatRelativeTime(test.tested_at)} ago` : ''}.`
                  : `Test to ${test.recipient} failed: ${test.error ?? 'it could not be sent.'}`}
        </p>
    );
}

function LastTestPoller({ prop }: { prop: string }) {
    usePoll(3000, { only: [prop] }, { mode: 'rest' });

    return null;
}
