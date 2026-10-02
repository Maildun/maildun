import type { EmailDocument } from '@maildun/email-builder';

export type EmailBuilderBlock = {
    type: string;
    props?: object;
    style?: object;
    children?: string[];
};

/**
 * The block editor's document (@maildun/email-builder): settings, a theme, a
 * flat map of blocks and the ordered ids of the top-level blocks in `root`.
 *
 * Blocks stay loosely typed here because Inertia's useForm inference recurses
 * into the package's full block union past TypeScript's depth limit. Hand it
 * to the package through toEmailDocument().
 */
export type EmailBuilderDocument = Omit<EmailDocument, 'blocks'> & {
    blocks: Record<string, EmailBuilderBlock>;
};

export type EmailEditorMode = 'html' | 'builder' | 'plain_text' | 'markdown';

export type EmailSourceMode = Extract<
    EmailEditorMode,
    'plain_text' | 'markdown'
>;

export type EmailEditorOption = {
    value: EmailEditorMode;
    label: string;
    description: string;
};

export type EmailTemplateIndexFilters = {
    q: string;
    editor: string;
    type: string;
};

export type EmailTemplateSummary = {
    uuid: string;
    name: string;
    description: string | null;
    editor: EmailEditorMode;
    is_starter: boolean;
};

export type EmailTemplateDetail = EmailTemplateSummary & {
    subject: string | null;
    preheader: string | null;
    html: string | null;
    source: string | null;
    design: EmailBuilderDocument | null;
    updated_at: string | null;
};

export type EmailRecipientRef = {
    uuid: string;
    name: string;
};

export type EmailCampaignStatus =
    | 'draft'
    | 'queued'
    | 'sending'
    | 'sent'
    | 'partially_failed'
    | 'failed'
    | 'stopped'
    /** Display only: a draft with a scheduled send time. */
    | 'scheduled';

export type EmailDeliveryStatus =
    | 'queued'
    | 'sending'
    | 'sent'
    | 'delivered'
    | 'delayed'
    | 'bounced'
    | 'complained'
    | 'rejected'
    | 'failed'
    | 'cancelled';

export type CampaignRecipientFilter =
    'retryable' | 'opened' | 'clicked' | EmailDeliveryStatus;

export type EmailRecipientPreview = {
    uuid: string;
    avatar: string;
    email: string;
};

export type EmailIndexFilters = {
    q: string;
    status: string;
    editor: string;
    audience: string;
};

export type EmailIndexAudienceOption = {
    uuid: string;
    name: string;
};

export type RecentCampaign = {
    uuid: string;
    name: string;
    status: EmailCampaignStatus;
    updated_at: string | null;
};

export type EmailSummary = {
    uuid: string;
    name: string;
    subject: string;
    editor: EmailEditorMode;
    status: EmailCampaignStatus;
    /** Percent of recipients processed, only while queued or sending. */
    progress: number | null;
    scheduled_at: string | null;
    audience: EmailRecipientRef | null;
    segment: EmailRecipientRef | null;
    recipient_count: number;
    recipients: EmailRecipientPreview[];
    last_tested_at: string | null;
    updated_at: string | null;
};

export type EmailDetail = {
    uuid: string;
    name: string;
    subject: string;
    preheader: string | null;
    from_name: string | null;
    from_address: string | null;
    reply_to: string | null;
    editor: EmailEditorMode;
    html: string | null;
    source: string;
    plain_text: string;
    query_string: string;
    track_clicks: boolean;
    track_opens: boolean;
    design: EmailBuilderDocument | null;
    audience: string | null;
    segment: string | null;
    last_tested_at: string | null;
    last_test: LastTestSend | null;
    scheduled_at: string | null;
    /** Why the last scheduled send did not start; cleared when rescheduled. */
    schedule_error: string | null;
    updated_at: string | null;
    attachments: EmailAttachment[];
};

export type EmailAttachment = {
    uuid: string;
    name: string;
    mime_type: string;
    size: number;
    size_label: string;
};

export type EmailAudienceAttribute = {
    name: string;
    key: string;
};

export type EmailSegmentOption = EmailRecipientRef & {
    subscribed_count: number;
};

export type EmailAudienceOption = {
    uuid: string;
    name: string;
    from_name: string | null;
    from_address: string | null;
    reply_to: string | null;
    subscribed_count: number;
    attributes: EmailAudienceAttribute[];
    segments: EmailSegmentOption[];
};

export type EmailSenderOption = {
    uuid: string;
    name: string | null;
    email: string;
    reply_to: string | null;
};

export type EmailSenderDefaults = {
    from_name: string | null;
    from_address: string | null;
    reply_to: string | null;
};

export type TeamEmailSettings = {
    email_editor: EmailEditorMode;
};

export type TeamSender = {
    uuid: string;
    name: string | null;
    email: string;
    reply_to: string | null;
    is_verified: boolean;
    was_verified: boolean;
    verification_sent_at: string | null;
    is_default: boolean;
};

export type TeamSenderDomain = {
    uuid: string;
    domain: string;
    dns_record_name: string;
    dns_record_value: string;
    is_verified: boolean;
    was_verified: boolean;
    verified_at: string | null;
    verification_checked_at: string | null;
};

export type TransactionalIndexFilters = {
    q: string;
    status: string;
    editor: string;
};

export type TransactionalEmailStatus = 'draft' | 'published';

export type TransactionalVariable = {
    key: string;
    example: string;
};

export type TransactionalEmailSummary = {
    uuid: string;
    name: string;
    slug: string;
    subject: string;
    editor: EmailEditorMode;
    status: TransactionalEmailStatus;
    last_tested_at: string | null;
    updated_at: string | null;
};

export type TransactionalEmailDetail = {
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    subject: string;
    preheader: string | null;
    from_name: string | null;
    from_address: string | null;
    reply_to: string | null;
    editor: EmailEditorMode;
    status: TransactionalEmailStatus;
    html: string | null;
    source: string;
    design: EmailBuilderDocument | null;
    variables: TransactionalVariable[];
    slug_frozen: boolean;
    last_tested_at: string | null;
    last_test: LastTestSend | null;
    updated_at: string | null;
};

/** One server-side condition StartEmailSend enforces; see BuildSendReadiness. */
export type SendReadinessCheck = {
    key: 'provider' | 'sender' | 'recipients' | 'content';
    passed: boolean;
    message: string;
    action_url: string | null;
};

export type SendReadiness = {
    ready: boolean;
    checks: SendReadinessCheck[];
};

/** How the latest test copy actually went; see the test-send jobs. */
export type LastTestSend = {
    status: 'queued' | 'sent' | 'failed';
    recipient: string | null;
    error: string | null;
    tested_at: string | null;
};

/** Amazon SES sending limits; see SesAccountLimits. Loaded as a deferred prop. */
export type SesAccountLimits =
    | {
          available: true;
          max_24_hour_send: number;
          sent_last_24_hours: number;
          remaining: number;
          max_send_rate: number;
          sandbox: boolean;
      }
    | { available: false; reason: string };
