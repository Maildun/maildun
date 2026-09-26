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
                        Unique clicks count each recipient once; total clicks
                        count every click. Click rate is unique clicks out of
                        all recipients.
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
                                        Unique clicks
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total clicks
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Click rate
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
                                            {link.unique_clicks.toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {link.clicks.toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {campaign.recipient_count > 0
                                                ? `${Math.round((link.unique_clicks / campaign.recipient_count) * 1000) / 10}%`
                                                : '—'}
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
