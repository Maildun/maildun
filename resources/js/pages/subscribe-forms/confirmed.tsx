/**
 * Renders the attribution notice required by the additional terms in LICENSE,
 * added under section 7(b) of the GNU Affero General Public License. Removing
 * or hiding it terminates the rights granted by that license.
 */
import {
    CheckmarkCircle02Icon,
    Mail01Icon,
    MailRemove01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Head, useForm } from '@inertiajs/react';
import { AttributionBadge } from '@/components/attribution-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    audienceName: string;
    confirmed: boolean;
    canConfirm: boolean;
    confirmUrl: string;
};

export default function SubscriptionConfirmed({
    audienceName,
    confirmed,
    canConfirm,
    confirmUrl,
}: Props) {
    const form = useForm({});

    const submit = (event: React.FormEvent): void => {
        event.preventDefault();
        form.post(confirmUrl);
    };

    const icon = confirmed
        ? CheckmarkCircle02Icon
        : canConfirm
          ? Mail01Icon
          : MailRemove01Icon;
    const title = confirmed
        ? 'Your subscription is confirmed'
        : canConfirm
          ? 'Confirm your subscription'
          : 'Your subscription could not be confirmed';
    const description = confirmed
        ? `You are now subscribed to ${audienceName}.`
        : canConfirm
          ? `Press the button below to start receiving email from ${audienceName}.`
          : `This subscription is no longer active for ${audienceName}.`;

    return (
        <>
            <Head title={title} />
            <main className="flex min-h-svh flex-col items-center justify-center gap-4 bg-muted p-6">
                <Card
                    className="w-full max-w-md"
                    data-test="subscription-confirmation-card"
                >
                    <CardHeader className="items-center text-center">
                        <div className="mx-auto mb-2 flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <HugeiconsIcon icon={icon} className="size-5" />
                        </div>
                        <CardTitle>{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </CardHeader>
                    {canConfirm && !confirmed ? (
                        <CardContent>
                            <form onSubmit={submit}>
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={form.processing}
                                    data-test="confirm-subscription-button"
                                >
                                    {form.processing && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Confirm subscription
                                </Button>
                            </form>
                        </CardContent>
                    ) : null}
                </Card>
                <AttributionBadge />
            </main>
        </>
    );
}

SubscriptionConfirmed.layout = null;
