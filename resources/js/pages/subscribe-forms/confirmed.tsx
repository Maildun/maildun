/**
 * Renders the attribution notice required by the additional terms in LICENSE,
 * added under section 7(b) of the GNU Affero General Public License. Removing
 * or hiding it terminates the rights granted by that license.
 */
import {
    CheckmarkCircle02Icon,
    MailRemove01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head } from '@inertiajs/react';
import { AttributionBadge } from '@/components/attribution-badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    audienceName: string;
    confirmed: boolean;
};

export default function SubscriptionConfirmed({
    audienceName,
    confirmed,
}: Props) {
    return (
        <>
            <Head
                title={
                    confirmed
                        ? 'Subscription confirmed'
                        : 'Subscription not confirmed'
                }
            />
            <main className="flex min-h-svh flex-col items-center justify-center gap-4 bg-muted p-6">
                <Card
                    className="w-full max-w-md"
                    data-test="subscription-confirmation-card"
                >
                    <CardHeader className="items-center text-center">
                        <div className="mx-auto mb-2 flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            {confirmed ? (
                                <HugeiconsIcon
                                    icon={CheckmarkCircle02Icon}
                                    className="size-5"
                                />
                            ) : (
                                <HugeiconsIcon
                                    icon={MailRemove01Icon}
                                    className="size-5"
                                />
                            )}
                        </div>
                        <CardTitle>
                            {confirmed
                                ? 'Your subscription is confirmed'
                                : 'Your subscription could not be confirmed'}
                        </CardTitle>
                        <CardDescription>
                            {confirmed
                                ? 'You are now subscribed to ' +
                                  audienceName +
                                  '.'
                                : 'This subscription is no longer active for ' +
                                  audienceName +
                                  '.'}
                        </CardDescription>
                    </CardHeader>
                </Card>
                <AttributionBadge />
            </main>
        </>
    );
}

SubscriptionConfirmed.layout = null;
