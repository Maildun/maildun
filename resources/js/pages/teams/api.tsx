import {
    Copy01Icon,
    Delete02Icon,
    Key01Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/components/ui/toast';
import { focusFirstInvalidField } from '@/lib/focus-first-invalid';
import { formatRelativeTime } from '@/lib/format';
import { destroy, store } from '@/routes/teams/api';
import type { Team } from '@/types';

type ApiKey = {
    uuid: string;
    name: string;
    prefix: string;
    last_used_at: string | null;
    created_at: string | null;
};

type PlainApiKey = {
    name: string;
    token: string;
};

type Props = {
    team: Team;
    apiKeys: ApiKey[];
    apiBaseUrl: string;
    canManage: boolean;
};

function CodeSnippet({ code }: { code: string }) {
    const normalizedCode = code.replaceAll('\n+', '\n');

    const copy = async () => {
        await navigator.clipboard.writeText(normalizedCode);
        toast.add({ type: 'success', title: 'Copied to clipboard.' });
    };

    return (
        <div className="relative min-w-0">
            <pre className="overflow-x-auto rounded-md bg-muted px-4 py-3 pr-20 font-mono text-xs leading-relaxed">
                <code>{normalizedCode}</code>
            </pre>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="absolute top-2 right-2"
                onClick={copy}
            >
                <HugeiconsIcon icon={Copy01Icon} data-icon="inline-start" />
                Copy
            </Button>
        </div>
    );
}

export default function TeamApiSettings({
    team,
    apiKeys,
    apiBaseUrl,
    canManage,
}: Props) {
    const [createKeyOpen, setCreateKeyOpen] = useState(false);
    const [newApiKey, setNewApiKey] = useState<PlainApiKey | null>(null);
    const [keyToRevoke, setKeyToRevoke] = useState<ApiKey | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const apiKey = flash?.apiKey as PlainApiKey | undefined;

            if (apiKey?.token) {
                setCreateKeyOpen(false);
                setNewApiKey(apiKey);
            }
        });
    }, []);

    const revokeKey = () => {
        if (!keyToRevoke) {
            return;
        }

        router.delete(destroy.url([team.slug, keyToRevoke.uuid]), {
            preserveScroll: true,
            onStart: () => setKeyToRevoke(null),
        });
    };

    return (
        <>
            <Head title={`API Key · ${team.name}`} />

            <div className="flex flex-col gap-8">
                <SettingsPageHeader title="API Key" />
                <SettingsPanel
                    variant="inset"
                    title="API keys"
                    description="Authenticate server-to-server requests for this workspace. New API keys are shown only once."
                    actions={
                        canManage ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => setCreateKeyOpen(true)}
                            >
                                Create API key
                            </Button>
                        ) : null
                    }
                >
                    <div>
                        {apiKeys.length === 0 ? (
                            <Empty className="border-0 py-10">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <HugeiconsIcon icon={Key01Icon} />
                                    </EmptyMedia>
                                    <EmptyTitle>No API keys</EmptyTitle>
                                    <EmptyDescription>
                                        Create a key to start calling the
                                        Maildun API for this workspace.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <div className="divide-y divide-border">
                                {apiKeys.map((apiKey) => (
                                    <div
                                        key={apiKey.uuid}
                                        className="flex flex-col gap-3 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium">
                                                {apiKey.name}
                                            </p>
                                            <code className="block truncate text-xs text-muted-foreground">
                                                {apiKey.prefix}
                                            </code>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {apiKey.last_used_at
                                                    ? `Used ${formatRelativeTime(apiKey.last_used_at)} ago`
                                                    : 'Never used'}
                                                {apiKey.created_at
                                                    ? ` · Created ${formatRelativeTime(apiKey.created_at)} ago`
                                                    : ''}
                                            </p>
                                        </div>
                                        {canManage ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setKeyToRevoke(apiKey)
                                                }
                                            >
                                                <HugeiconsIcon
                                                    icon={Delete02Icon}
                                                    data-icon="inline-start"
                                                />
                                                Revoke
                                            </Button>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </SettingsPanel>

                <SettingsPanel
                    variant="inset"
                    title="Authentication"
                    description="Send the workspace key as a bearer token on every request."
                >
                    <div className="flex flex-col gap-4 p-6 sm:p-7">
                        <CodeSnippet
                            code={`Authorization: Bearer $MAILDUN_API_KEY\nAccept: application/json\nContent-Type: application/json`}
                        />
                        <p className="text-xs text-muted-foreground">
                            The base URL is <code>{apiBaseUrl}</code>. Requests
                            are limited to 120 per minute per API key. A revoked
                            or invalid key returns 401.
                        </p>
                    </div>
                </SettingsPanel>
            </div>

            <Dialog open={createKeyOpen} onOpenChange={setCreateKeyOpen}>
                <DialogContent>
                    <Form
                        key={String(createKeyOpen)}
                        {...store.form(team.slug)}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                        onSuccess={() => setCreateKeyOpen(false)}
                        onError={() => focusFirstInvalidField()}
                    >
                        {({ errors, processing }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>Create API key</DialogTitle>
                                    <DialogDescription>
                                        Name this key so your workspace knows
                                        which server or environment uses it.
                                    </DialogDescription>
                                </DialogHeader>

                                <FieldGroup className="gap-5">
                                    <Field data-invalid={Boolean(errors.name)}>
                                        <FieldLabel htmlFor="api-key-name">
                                            Name
                                        </FieldLabel>
                                        <Input
                                            id="api-key-name"
                                            name="name"
                                            autoFocus
                                            required
                                            placeholder="Production server"
                                            aria-invalid={Boolean(errors.name)}
                                        />
                                        <FieldError>{errors.name}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <DialogFooter>
                                    <DialogClose
                                        render={<Button variant="secondary" />}
                                    >
                                        Cancel
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : null}
                                        Create API key
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={newApiKey !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setNewApiKey(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Copy {newApiKey?.name ?? 'your API key'}
                        </DialogTitle>
                        <DialogDescription>
                            Store this key securely before closing this dialog.
                        </DialogDescription>
                    </DialogHeader>

                    <Alert>
                        <HugeiconsIcon icon={Key01Icon} />
                        <AlertTitle>This API key is shown only once</AlertTitle>
                        <AlertDescription>
                            You will not be able to view it again. Store it in
                            your server's secret manager and never expose it in
                            browser code.
                        </AlertDescription>
                    </Alert>

                    <code className="block rounded-md bg-muted px-3 py-3 text-xs break-all">
                        {newApiKey?.token}
                    </code>

                    <DialogFooter>
                        <DialogClose render={<Button variant="secondary" />}>
                            I've saved it
                        </DialogClose>
                        <Button
                            type="button"
                            onClick={async () => {
                                if (!newApiKey) {
                                    return;
                                }

                                await navigator.clipboard.writeText(
                                    newApiKey.token,
                                );
                                toast.add({
                                    type: 'success',
                                    title: 'API key copied.',
                                });
                            }}
                        >
                            <HugeiconsIcon
                                icon={Copy01Icon}
                                data-icon="inline-start"
                            />
                            Copy API key
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={keyToRevoke !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setKeyToRevoke(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Revoke API key?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {keyToRevoke?.name} will stop working immediately.
                            Applications using it will receive 401 responses.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            type="button"
                            variant="destructive"
                            onClick={revokeKey}
                        >
                            Revoke key
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
