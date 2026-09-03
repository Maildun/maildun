import { EmailReportLayout } from '@/components/email-report-layout';
import type {
    CampaignReportCampaign,
    CampaignReportMetrics,
    CampaignTrackedLink,
} from '@/components/email-report-layout';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Props = {
    campaign: CampaignReportCampaign;
    metrics: CampaignReportMetrics;
    links: CampaignTrackedLink[];
    canManage: boolean;
};

export default function EmailLinks({
    campaign,
    metrics,
    links,
    canManage,
}: Props) {
    return (
        <EmailReportLayout
            campaign={campaign}
            metrics={metrics}
            canManage={canManage}
            activePage="links"
            pollProps={['campaign', 'metrics', 'links']}
        >
            <Card>
                <CardHeader>
                    <CardTitle>Tracked links</CardTitle>
                    <CardDescription>
                        Total clicks across every recipient.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {links.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            This campaign has no trackable links.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Destination</TableHead>
                                    <TableHead className="text-right">
                                        Clicks
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {links.map((link) => (
                                    <TableRow key={link.uuid}>
                                        <TableCell className="max-w-0 truncate">
                                            {link.url}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {link.clicks}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </EmailReportLayout>
    );
}
