import { router } from '@inertiajs/react';
import { useState } from 'react';
import { GoogleIcon } from '@/components/icons/google-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    connect as connectGoogle,
    disconnect as disconnectGoogle,
} from '@/routes/google';

export type Props = {
    googleConnected: boolean;
    hasPassword: boolean;
};

/**
 * The Google row of the "Connected accounts" panel. Connecting is a full page
 * navigation because the server redirects to accounts.google.com, while the
 * server flashes the toast for disconnecting, including when it is refused.
 */
export default function ManageGoogleAccount({
    googleConnected,
    hasPassword,
}: Props) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    function disconnect(): void {
        router.delete(disconnectGoogle.url(), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirmOpen(false);
            },
        });
    }

    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-8">
            <div className="flex items-center gap-4">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                    <GoogleIcon className="size-5" />
                </div>
                <div className="space-y-1">
                    <div className="flex items-center gap-2.5">
                        <p className="font-medium tracking-tight">Google</p>
                        <Badge
                            variant={googleConnected ? 'success' : 'secondary'}
                            data-test="google-connection-status"
                        >
                            {googleConnected ? 'Connected' : 'Not connected'}
                        </Badge>
                    </div>
                    <p
                        id="google-account-help"
                        className="text-sm text-muted-foreground"
                    >
                        {!googleConnected
                            ? 'Log in with your Google account instead of your password.'
                            : hasPassword
                              ? 'You can log in with your Google account or your password.'
                              : 'Set a password above before disconnecting Google, so you can still log in.'}
                    </p>
                </div>
            </div>

            {!googleConnected ? (
                <Button
                    variant="outline"
                    nativeButton={false}
                    render={<a href={connectGoogle.url()} />}
                    className="w-full shrink-0 sm:w-auto"
                    data-test="connect-google-button"
                >
                    Connect
                </Button>
            ) : !hasPassword ? (
                <Button
                    variant="outline"
                    disabled
                    aria-describedby="google-account-help"
                    className="w-full shrink-0 sm:w-auto"
                    data-test="disconnect-google-button"
                >
                    Disconnect
                </Button>
            ) : (
                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogTrigger
                        render={
                            <Button
                                variant="outline"
                                className="w-full shrink-0 sm:w-auto"
                                data-test="disconnect-google-button"
                            />
                        }
                    >
                        Disconnect
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Disconnect Google account</DialogTitle>
                            <DialogDescription>
                                You will no longer be able to log in with
                                Google. You can still log in with your email
                                address and password, and connect Google again
                                at any time.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <DialogClose
                                render={<Button variant="secondary" />}
                            >
                                Cancel
                            </DialogClose>
                            <Button
                                variant="destructive"
                                disabled={processing}
                                onClick={disconnect}
                                data-test="confirm-disconnect-google-button"
                            >
                                {processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                Disconnect
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
