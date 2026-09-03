import { useState } from 'react';
import { EmailReportLayout } from '@/components/email-report-layout';
import type {
    CampaignReportCampaign,
    CampaignReportMetrics,
} from '@/components/email-report-layout';
import PreviewWidthTabs from '@/components/preview-width-tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    campaign: CampaignReportCampaign;
    metrics: CampaignReportMetrics;
    canManage: boolean;
};

export default function EmailPreview({ campaign, metrics, canManage }: Props) {
    const [previewWidth, setPreviewWidth] = useState<'desktop' | 'mobile'>(
        'desktop',
    );

    return (
        <EmailReportLayout
            campaign={campaign}
            metrics={metrics}
            canManage={canManage}
            activePage="preview"
            pollProps={['campaign', 'metrics']}
        >
            <Card>
                <CardHeader className="flex-row items-center justify-between">
                    <div className="flex flex-col gap-1.5">
                        <CardTitle>Campaign preview</CardTitle>
                        <CardDescription>
                            Inspect the exact saved HTML that was delivered.
                        </CardDescription>
                    </div>
                    <PreviewWidthTabs
                        value={previewWidth}
                        onValueChange={setPreviewWidth}
                    />
                </CardHeader>
                <CardContent className="overflow-auto rounded-lg bg-muted p-6">
                    <iframe
                        title={`${campaign.name} preview`}
                        srcDoc={campaign.html}
                        sandbox=""
                        className="mx-auto min-h-[640px] rounded-lg border bg-white transition-[width]"
                        style={{
                            width: previewWidth === 'desktop' ? 600 : 375,
                            maxWidth: '100%',
                        }}
                    />
                </CardContent>
            </Card>
        </EmailReportLayout>
    );
}
