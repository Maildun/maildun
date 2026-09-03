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
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AttributionBadge } from '@/components/attribution-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    email: string;
    audience: string | null;
    unsubscribed: boolean;
    action: string;
};

export default function Unsubscribe({
    email,
    audience,
    unsubscribed,
    action,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        router.post(
            action,
            {},
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const source = audience ?? 'these emails';

    return (
        <>
            <Head title={unsubscribed ? 'Unsubscribed' : 'Unsubscribe'} />
            <main className="flex min-h-svh flex-col items-center justify-center gap-4 bg-muted p-6">
                <Card className="w-full max-w-md" data-test="unsubscribe-card">
                    <CardHeader className="items-center text-center">
                        <div className="mx-auto mb-2 flex size-11 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            {unsubscribed ? (
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
                            {unsubscribed
                                ? 'You have been unsubscribed'
                                : 'Unsubscribe'}
                        </CardTitle>
                        <CardDescription>
                            {unsubscribed
                                ? `${email} will no longer receive ${source}.`
                                : `Stop sending ${source} to ${email}?`}
                        </CardDescription>
                    </CardHeader>

                    {!unsubscribed && (
                        <CardContent>
                            <Button
                                type="button"
                                className="w-full"
                                onClick={submit}
                                disabled={processing}
                                data-test="unsubscribe-confirm"
                            >
                                {processing
                                    ? 'Unsubscribing…'
                                    : 'Confirm unsubscribe'}
                            </Button>
                        </CardContent>
                    )}
                </Card>
                <AttributionBadge />
            </main>
        </>
    );
}

Unsubscribe.layout = null;
