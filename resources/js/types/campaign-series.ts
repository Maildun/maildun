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

export type ConversionEventOption = {
    value: string;
    label: string;
    goal: CampaignSeriesGoal;
};

export type ConversionValue = {
    currency: string;
    value_minor: number;
};

export type CampaignSeriesContactGroupKey =
    | 'converted'
    | 'clicked_not_converted'
    | 'opened_not_clicked'
    | 'not_engaged';

export type CampaignSeriesContact = {
    email: string;
    name: string | null;
    conversions: number;
    last_activity_at: string | null;
};

export type CampaignSeriesContactGroup = {
    group: CampaignSeriesContactGroupKey;
    label: string;
    total: number;
    limit: number;
    contacts: CampaignSeriesContact[];
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
> & {
    conversion_event_key: string | null;
    attribution_window_days: number;
    conversion_target: number | null;
    value_currency: string | null;
};

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
    conversion_tracking: boolean;
    conversions: number;
    attributed_conversions: number;
    assisted_conversions: number;
    explicit_conversions: number;
    conversion_rate: number;
    conversion_value: ConversionValue[];
    conversion_target: number | null;
    target_progress: number | null;
    contact_groups: Record<CampaignSeriesContactGroupKey, number>;
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
    conversions: number;
    conversion_rate: number;
    conversion_value: ConversionValue[];
    is_conversion_leader: boolean;
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
    conversionEvents: ConversionEventOption[];
    contactGroup: CampaignSeriesContactGroup | null;
    canManage: boolean;
};
