import { Badge } from '@/components/ui/badge';
import type { SubscriberStatus } from '@/types/audiences';

/**
 * A member waiting to confirm by email (a double opt-in signup or a
 * re-signup after an opt-out) is not mailed yet, so it shows as Pending.
 */
export function SubscriberStatusBadge({
    status,
    pendingConfirmation = false,
}: {
    status: SubscriberStatus;
    pendingConfirmation?: boolean;
}) {
    if (pendingConfirmation) {
        return (
            <Badge
                variant="orange"
                title="Waiting for this person to confirm by email"
            >
                Pending
            </Badge>
        );
    }

    return status === 'subscribed' ? (
        <Badge variant="success">Subscribed</Badge>
    ) : (
        <Badge variant="secondary">Unsubscribed</Badge>
    );
}
