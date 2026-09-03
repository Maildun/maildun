import {
    Alert02Icon,
    CheckmarkCircle02Icon,
    RefreshIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head } from '@inertiajs/react';
import { SettingsPageHeader } from '@/components/settings-page-header';
import { SettingsPanel } from '@/components/settings-panel';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { refresh as refreshAppUpdate } from '@/routes/app-update';
import { test as runSystemCheck } from '@/routes/system-check';
import type { AppUpdateStatus } from '@/types';

type SystemCheck = {
    key: string;
    label: string;
    description: string;
    status: 'ready' | 'failed' | 'pending';
};

type Props = {
    checks: SystemCheck[];
    appUpdate: AppUpdateStatus;
};

export default function SystemCheck({ checks, appUpdate }: Props) {
    const hasFailures = checks.some((check) => check.status === 'failed');

    return (
        <>
            <Head title="System Check" />

            <div className="flex flex-col gap-8">
                <SettingsPageHeader
                    title="System Check"
                    description="Review the health of this Maildun deployment. Results never include credentials."
                />

                <SettingsPanel
                    variant="inset"
                    title="System status"
                    description="Current checks for the database, cache, queues, workers, and storage."
                >
                    <ul className="divide-y divide-border">
                        {checks.map((check) => (
                            <li
                                key={check.key}
                                className="flex items-start justify-between gap-4 px-6 py-5 sm:px-7"
                            >
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <p className="text-sm font-medium">
                                        {check.label}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {check.description}
                                    </p>
                                </div>
                                <Badge
                                    className="shrink-0"
                                    variant={
                                        check.status === 'ready'
                                            ? 'success'
                                            : check.status === 'failed'
                                              ? 'destructive'
                                              : 'secondary'
                                    }
                                >
                                    {check.status === 'ready'
                                        ? 'Ready'
                                        : check.status === 'failed'
                                          ? 'Failed'
                                          : 'Not run'}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                </SettingsPanel>

                <SettingsPanel
                    variant="inset"
                    title="Maildun updates"
                    description="Compare this deployment with the latest official release. Checking never installs an update."
                    actions={
                        <Form
                            {...refreshAppUpdate.form()}
                            disableWhileProcessing
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <HugeiconsIcon
                                            icon={RefreshIcon}
                                            data-icon="inline-start"
                                        />
                                    )}
                                    Check for updates
                                </Button>
                            )}
                        </Form>
                    }
                >
                    <div className="flex flex-col gap-3 px-6 py-5 sm:px-7">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-medium">
                                Installed version {appUpdate.current_version}
                            </p>
                            <Badge
                                variant={
                                    appUpdate.status === 'unsupported'
                                        ? 'destructive'
                                        : appUpdate.status ===
                                            'update_available'
                                          ? 'orange'
                                          : appUpdate.status === 'current'
                                            ? 'success'
                                            : 'secondary'
                                }
                            >
                                {appUpdate.status === 'unsupported'
                                    ? 'Update required'
                                    : appUpdate.status === 'update_available'
                                      ? 'Update available'
                                      : appUpdate.status === 'current'
                                        ? 'Up to date'
                                        : appUpdate.status === 'disabled'
                                          ? 'Disabled'
                                          : 'Not checked'}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {appUpdate.status === 'unsupported'
                                ? `This deployment is below the minimum supported version${appUpdate.minimum_version ? ` (${appUpdate.minimum_version})` : ''}.`
                                : appUpdate.status === 'update_available'
                                  ? `Version ${appUpdate.latest_version} is available.`
                                  : appUpdate.status === 'current'
                                    ? `Version ${appUpdate.latest_version} is the latest release.`
                                    : appUpdate.status === 'disabled'
                                      ? 'Release checks are disabled for this deployment.'
                                      : 'Maildun has not received a release check result yet.'}
                        </p>
                        {appUpdate.notes_url ? (
                            <a
                                href={appUpdate.notes_url}
                                target="_blank"
                                rel="noreferrer"
                                className="w-fit text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                View release notes
                            </a>
                        ) : null}
                    </div>
                </SettingsPanel>

                <Alert variant={hasFailures ? 'destructive' : 'default'}>
                    <HugeiconsIcon
                        icon={hasFailures ? Alert02Icon : CheckmarkCircle02Icon}
                    />
                    <AlertTitle>
                        {hasFailures
                            ? 'Some services need attention'
                            : 'Core services are ready'}
                    </AlertTitle>
                    <AlertDescription>
                        The system test creates temporary storage files and
                        removes them immediately. It does not change
                        infrastructure settings.
                    </AlertDescription>
                </Alert>

                <SettingsPanel
                    variant="inset"
                    title="Run a system test"
                    description="Verify storage by writing, reading, and removing a temporary file from both storage roles."
                >
                    <div className="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <p className="text-sm text-muted-foreground">
                            This test is safe to run while Maildun is in use.
                        </p>
                        <Form {...runSystemCheck.form()} disableWhileProcessing>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto"
                                >
                                    {processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <HugeiconsIcon
                                            icon={RefreshIcon}
                                            data-icon="inline-start"
                                        />
                                    )}
                                    Run system test
                                </Button>
                            )}
                        </Form>
                    </div>
                </SettingsPanel>
            </div>
        </>
    );
}
