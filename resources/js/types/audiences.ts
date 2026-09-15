import type { EmailDeliveryStatus } from './emails';
import type { TeamBrandTheme } from './teams';

export type SubscriberStatus = 'subscribed' | 'unsubscribed';
export type SubscriberSource = 'manual' | 'form' | 'api';
export type SegmentMatchType = 'all' | 'any';
export type SubscribeFormFieldMode = 'hidden' | 'optional' | 'required';
export type SubscribeFormStyle = 'card' | 'split' | 'minimal' | 'cover';
export type SubscribeFormImageSide = 'left' | 'right';
export type SubscribeFormArtworkType =
    'upload' | 'image-preset' | 'background-preset';
export type SubscribeFormArtworkPreset =
    | 'image-aurora'
    | 'image-horizon'
    | 'image-prism'
    | 'image-nocturne'
    | 'image-drift'
    | 'image-flare'
    | 'background-matrix'
    | 'background-grid'
    | 'background-orbit'
    | 'background-glow';
export type SubscribeFormArtworkPresetOption = {
    value: SubscribeFormArtworkPreset;
    label: string;
    artwork_type: Exclude<SubscribeFormArtworkType, 'upload'>;
};
export type SubscribeFormLogoShape =
    'default' | 'square' | 'rounded-lg' | 'rounded-xl' | 'rounded-full';
export type SubscribeFormLogoSize = 'small' | 'medium' | 'large';
export type SubscribeFormLogoPosition = 'left' | 'center' | 'right';
export type SubscribeFormHeaderSpacing =
    'compact' | 'default' | 'relaxed' | 'spacious';
export type SubscribeFormCardPadding = 'compact' | 'default' | 'spacious';
export type SubscribeFormTextAlignment = 'left' | 'center' | 'right';
export type SubscribeFormPoweredByPosition =
    | 'top-left'
    | 'top-center'
    | 'top-right'
    | 'bottom-left'
    | 'bottom-center'
    | 'bottom-right';
export type SegmentRuleField =
    | 'email'
    | 'first_name'
    | 'last_name'
    | 'status'
    | 'source'
    | 'subscribe_form'
    | 'subscribed_at';
export type SegmentRuleOperator =
    | 'equals'
    | 'not_equals'
    | 'contains'
    | 'does_not_contain'
    | 'before'
    | 'after';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};

export type AudienceSummary = {
    uuid: string;
    name: string;
    description: string | null;
    avatar: string;
    subscribers_count: number;
    subscribed_count: number;
    segments_count: number;
    forms_count: number;
    created_at: string;
};

export type AudienceIndexFilter =
    'all' | 'active' | 'empty' | 'segments' | 'forms';

export type AudienceIndexSort = 'newest' | 'oldest' | 'subscribers';

export type AudienceIndexFilters = {
    search: string;
    filter: AudienceIndexFilter;
    sort: AudienceIndexSort;
};

export type SubscriberIndexFilters = {
    search: string;
    status: string;
    source: string;
};

export type Audience = Pick<
    AudienceSummary,
    'uuid' | 'name' | 'description' | 'avatar'
> & {
    first_name_mode: SubscribeFormFieldMode;
    last_name_mode: SubscribeFormFieldMode;
    double_opt_in: boolean;
    double_opt_in_email_uuid: string | null;
    from_name: string | null;
    from_address: string | null;
    reply_to: string | null;
    notification_email: string | null;
    subscribed_url: string | null;
    already_subscribed_url: string | null;
    unsubscribed_url: string | null;
};

export type AudienceAttributeType = 'text' | 'number' | 'date';

export type AudienceAttributeTypeOption = {
    value: AudienceAttributeType;
    label: string;
};

export type AudienceAttribute = {
    uuid: string;
    name: string;
    key: string;
    type: AudienceAttributeType;
    required: boolean;
};

export type AudienceSenderFallbacks = {
    from_name: string | null;
    from_address: string | null;
    reply_to: string | null;
};

export type AudienceSenderOption = {
    uuid: string;
    name: string | null;
    email: string;
    reply_to: string | null;
};

export type AudienceSettingsPage =
    | 'general'
    | 'sender'
    | 'notifications'
    | 'double-opt-in'
    | 'attributes'
    | 'landing-pages'
    | 'hygiene'
    | 'danger';

export type AudienceSubscriberMetric = {
    value: number;
    change: number | null;
};

export type AudienceSubscriberStatKey =
    | 'total'
    | 'subscribed'
    | 'unsubscribed'
    | 'new_this_week'
    | 'subscribe_rate';

export type AudienceSubscriberSeriesPoint = {
    date: string;
    total: number;
    previous_total: number;
    subscribed: number;
    previous_subscribed: number;
    unsubscribed: number;
    previous_unsubscribed: number;
    new: number;
    previous_new: number;
    subscribe_rate: number;
    previous_subscribe_rate: number;
};

export type AudienceSubscriberStats = {
    total: AudienceSubscriberMetric;
    subscribed: AudienceSubscriberMetric;
    unsubscribed: AudienceSubscriberMetric;
    new_this_week: AudienceSubscriberMetric;
    subscribe_rate: AudienceSubscriberMetric;
    series: AudienceSubscriberSeriesPoint[];
};

export type Tag = {
    uuid: string;
    name: string;
    color: string | null;
};

export type Subscriber = {
    uuid: string;
    avatar: string;
    email: string;
    first_name: string | null;
    last_name: string | null;
    status: SubscriberStatus;
    source: SubscriberSource;
    source_form: { uuid: string; name: string } | null;
    tags: Tag[];
    subscribed_at: string | null;
};

export type SubscriberProfile = Subscriber & {
    unsubscribed_at: string | null;
    created_at: string | null;
    consent_text: string | null;
    consented_at: string | null;
    consent_ip: string | null;
};

export type SubscriberAttributeValue = {
    uuid: string;
    name: string;
    key: string;
    type: AudienceAttributeType;
    required: boolean;
    value: string | number | null;
};

export type SubscriberEmailFilter =
    'all' | 'opened' | 'clicked' | 'failed' | EmailDeliveryStatus;

export type SubscriberShowFilters = {
    tab: 'details' | 'emails' | 'automations';
    status: SubscriberEmailFilter;
};

export type SubscriberEmailStats = {
    received: number;
    opened: number;
    clicked: number;
    bounced: number;
    last_sent_at: string | null;
    last_opened_at: string | null;
    last_clicked_at: string | null;
};

export type SubscriberReceivedEmail = {
    uuid: string;
    campaign: { uuid: string | null; name: string; subject: string } | null;
    status: EmailDeliveryStatus;
    opens: number;
    clicks: number;
    failure_reason: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    last_opened_at: string | null;
    last_clicked_at: string | null;
};

export type SubscriberAutomationRun = {
    uuid: string;
    name: string;
    automation_uuid: string | null;
    status: string;
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    failure_reason: string | null;
};

export type SegmentRule = {
    field: SegmentRuleField;
    operator: SegmentRuleOperator;
    value: string;
};

export type Segment = {
    uuid: string;
    name: string;
    description: string | null;
    match_type: SegmentMatchType;
    rules: SegmentRule[];
    rules_synced_at?: string | null;
    subscribers_count?: number;
};

export type SubscribeFormSummary = {
    uuid: string;
    name: string;
    headline: string;
    published: boolean;
    subscribers_count: number;
    public_url: string;
};

export type SubscribeForm = {
    uuid: string;
    name: string;
    headline: string;
    description: string | null;
    text_alignment: SubscribeFormTextAlignment;
    button_label: string;
    success_heading: string;
    success_message: string;
    redirect_enabled: boolean;
    redirect_url: string | null;
    powered_by_enabled: boolean;
    powered_by_form_position: SubscribeFormPoweredByPosition;
    consent_text: string;
    style: SubscribeFormStyle;
    image_side: SubscribeFormImageSide;
    artwork_type: SubscribeFormArtworkType;
    artwork_preset: SubscribeFormArtworkPreset | null;
    image_url: string | null;
    image_processing: boolean;
    logo: string | null;
    logo_shape: SubscribeFormLogoShape;
    logo_size: SubscribeFormLogoSize;
    logo_position: SubscribeFormLogoPosition;
    header_spacing: SubscribeFormHeaderSpacing;
    card_padding: SubscribeFormCardPadding;
    theme: TeamBrandTheme;
    attributes: AudienceAttribute[];
    published: boolean;
    public_url: string;
    embed_code: string;
};
