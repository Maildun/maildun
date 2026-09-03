import {
    Alert02Icon,
    ArrowLeft01Icon,
    CheckmarkCircle02Icon,
    RefreshIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { Form, Head, Link } from '@inertiajs/react';
import InstallationController from '@/actions/App/Http/Controllers/InstallationController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type SystemCheck = {
    key: string;
    label: string;
    description: string;
    status: 'ready' | 'failed' | 'pending';
};

type Props = {
    checks: SystemCheck[];
    installUrl: string;
    signedQuery: {
        expires: string;
        signature: string;
    };
};

export default function InstallSystem({
    checks,
    installUrl,
    signedQuery,
}: Props) {
    const hasFailures = checks.some((check) => check.status === 'failed');

    return (
        <>
            <Head title="Test installation" />

            <div className="flex flex-col gap-6">
                <Card size="sm">
                    <CardHeader>
                        <CardTitle>System status</CardTitle>
                        <CardDescription>
                            Results are sanitized and never include credentials.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="flex flex-col gap-4">
                            {checks.map((check) => (
                                <li
                                    key={check.key}
                                    className="flex items-start justify-between gap-3"
                                >
                                    <div className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-sm font-medium">
                                            {check.label}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {check.description}
                                        </span>
                                    </div>
                                    <Badge
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
                    </CardContent>
                </Card>

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
                        The storage test creates temporary files and removes
                        them immediately. No infrastructure settings are
                        changed.
                    </AlertDescription>
                </Alert>

                <Form
                    {...InstallationController.testSystem.form({
                        query: signedQuery,
                    })}
                    disableWhileProcessing
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            className="w-full"
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
                            Run system test
                        </Button>
                    )}
                </Form>

                <Button
                    variant="ghost"
                    className="w-full"
                    render={<Link href={installUrl} />}
                >
                    <HugeiconsIcon
                        icon={ArrowLeft01Icon}
                        data-icon="inline-start"
                    />
                    Back to administrator setup
                </Button>
            </div>
        </>
    );
}

InstallSystem.layout = {
    title: 'Test this Maildun installation',
    description:
        'Check the database, cache, queues, workers, and storage before creating the first administrator.',
};
