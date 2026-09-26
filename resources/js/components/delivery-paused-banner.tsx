import { Alert02Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Link, usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { edit as editEmailDelivery } from '@/routes/teams/email-provider';

/**
 * Shown on every app page while the workspace's delivery connection exists
 * but no longer passes its test, because nothing sends until it does.
 */
export function DeliveryPausedBanner() {
    const { deliveryPaused, currentTeam } = usePage().props;

    if (!deliveryPaused || !currentTeam) {
        return null;
    }

    return (
        <Alert
            variant="warning"
            className="mb-6"
            data-test="delivery-paused-banner"
        >
            <HugeiconsIcon icon={Alert02Icon} aria-hidden="true" />
            <AlertTitle>Sending is paused</AlertTitle>
            <AlertDescription>
                The workspace email delivery connection needs to be tested
                again. Campaigns, automations and transactional emails will not
                send until it passes.{' '}
                <Link
                    href={editEmailDelivery(currentTeam.slug)}
                    className="font-medium text-foreground underline underline-offset-4"
                >
                    Open email delivery
                </Link>
            </AlertDescription>
        </Alert>
    );
}
