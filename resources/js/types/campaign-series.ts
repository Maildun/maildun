import type {
    EmailCampaignStatus,
    EmailEditorMode,
    EmailTemplateSummary,
} from './emails';

export type CampaignSeriesGoal =
    | 'generate_leads'
    | 'book_meetings'
    | 'start_trials'
    | 'drive_purchases'
    | 'upgrade_customers'
    | 'win_back_customers';

export type CampaignSeriesGoalOption = {
    value: CampaignSeriesGoal;
    label: string;
};

export type CampaignSeriesSummary = {
    uuid: string;
    name: string;
    description: string | null;
    goal: CampaignSeriesGoal;
    goal_label: string;
    objective: string | null;
    primary_cta_url: string | null;
    campaigns_count: number;
    sent_campaigns_count: number;
    updated_at: string | null;
};

export type CampaignSeriesDetail = Omit<
    CampaignSeriesSummary,
    'campaigns_count' | 'sent_campaigns_count'
>;

export type CampaignSeriesReportSummary = {
    campaigns: number;
    sent_campaigns: number;
    unique_recipients: number;
    processed: number;
    delivered: number;
    opened: number;
    clicked: number;
    cta_clicks: number;
    bounced: number;
    complained: number;
    failed: number;
    delivery_feedback: 'available' | 'partial' | 'unavailable';
    feedback_recipient_count: number;
    delivery_rate: number | null;
    open_rate: number;
    click_rate: number;
    click_to_open_rate: number;
};

export type CampaignSeriesCampaign = {
    uuid: string;
    name: string;
    subject: string;
    status: EmailCampaignStatus;
    audience: string | null;
    segment: string | null;
    recipient_count: number;
    processed: number;
    delivered: number;
    opened: number;
    clicked: number;
    cta_clicks: number;
    bounced: number;
    complained: number;
    failed: number;
    delivery_feedback: 'available' | 'partial' | 'unavailable';
    delivery_rate: number | null;
    open_rate: number;
    click_rate: number;
    click_to_open_rate: number;
    sent_at: string | null;
    updated_at: string | null;
};

export type AvailableSeriesCampaign = Pick<
    CampaignSeriesCampaign,
    'uuid' | 'name' | 'subject' | 'status'
>;

export type CampaignSeriesReport = {
    summary: CampaignSeriesReportSummary;
    campaigns: CampaignSeriesCampaign[];
    has_mixed_recipients: boolean;
};

export type CampaignSeriesShowProps = {
    series: CampaignSeriesDetail;
    report: CampaignSeriesReport;
    availableCampaigns: AvailableSeriesCampaign[];
    templates: EmailTemplateSummary[];
    defaultEditor: EmailEditorMode;
    goals: CampaignSeriesGoalOption[];
    canManage: boolean;
};
