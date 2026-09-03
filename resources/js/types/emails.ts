/**
 * The document format used by @usewaypoint/email-builder: a flat map of block
 * ids, with `root` holding the layout and the ordered list of child ids.
 */
export type EmailBuilderFontFamily =
    | 'MODERN_SANS'
    | 'BOOK_SANS'
    | 'ORGANIC_SANS'
    | 'GEOMETRIC_SANS'
    | 'HEAVY_SANS'
    | 'ROUNDED_SANS'
    | 'MODERN_SERIF'
    | 'BOOK_SERIF'
    | 'MONOSPACE';

export type EmailBuilderTextAlign = 'left' | 'center' | 'right';

export type EmailBuilderPadding = {
    top: number;
    bottom: number;
    left: number;
    right: number;
};

export type EmailBuilderStyle = {
    color?: string | null;
    backgroundColor?: string | null;
    borderColor?: string | null;
    borderRadius?: number | null;
    fontSize?: number | null;
    fontFamily?: EmailBuilderFontFamily | null;
    fontWeight?: 'bold' | 'normal' | null;
    textAlign?: EmailBuilderTextAlign | null;
    padding?: EmailBuilderPadding | null;
};

export type EmailLayoutBlock = {
    type: 'EmailLayout';
    data: {
        backdropColor?: string | null;
        canvasColor?: string | null;
        borderColor?: string | null;
        borderRadius?: number | null;
        textColor?: string | null;
        fontFamily?: EmailBuilderFontFamily | null;
        childrenIds?: string[] | null;
    };
};

export type HeadingBlock = {
    type: 'Heading';
    data: {
        style?: EmailBuilderStyle | null;
        props?: {
            text?: string | null;
            level?: 'h1' | 'h2' | 'h3' | null;
        } | null;
    };
};

export type TextBlock = {
    type: 'Text';
    data: {
        style?: EmailBuilderStyle | null;
        props?: { text?: string | null; markdown?: boolean | null } | null;
    };
};

export type ButtonBlock = {
    type: 'Button';
    data: {
        style?: EmailBuilderStyle | null;
        props?: {
            text?: string | null;
            url?: string | null;
            buttonBackgroundColor?: string | null;
            buttonTextColor?: string | null;
            buttonStyle?: 'rectangle' | 'pill' | 'rounded' | null;
            size?: 'x-small' | 'small' | 'medium' | 'large' | null;
            fullWidth?: boolean | null;
        } | null;
    };
};

export type ImageBlock = {
    type: 'Image';
    data: {
        style?: Pick<
            EmailBuilderStyle,
            'backgroundColor' | 'padding' | 'textAlign'
        > | null;
        props?: {
            url?: string | null;
            alt?: string | null;
            linkHref?: string | null;
            width?: number | null;
            height?: number | null;
            contentAlignment?: 'top' | 'middle' | 'bottom' | null;
        } | null;
    };
};

export type DividerBlock = {
    type: 'Divider';
    data: {
        style?: Pick<EmailBuilderStyle, 'backgroundColor' | 'padding'> | null;
        props?: {
            lineColor?: string | null;
            lineHeight?: number | null;
        } | null;
    };
};

export type SpacerBlock = {
    type: 'Spacer';
    data: { props?: { height?: number | null } | null };
};

export type HtmlBlock = {
    type: 'Html';
    data: {
        style?: EmailBuilderStyle | null;
        props?: { contents?: string | null } | null;
    };
};

export type AvatarBlock = {
    type: 'Avatar';
    data: {
        style?: Pick<EmailBuilderStyle, 'textAlign' | 'padding'> | null;
        props?: {
            size?: number | null;
            shape?: 'circle' | 'square' | 'rounded' | null;
            imageUrl?: string | null;
            alt?: string | null;
        } | null;
    };
};

export type ContainerBlock = {
    type: 'Container';
    data: {
        style?: Pick<
            EmailBuilderStyle,
            'backgroundColor' | 'borderColor' | 'borderRadius' | 'padding'
        > | null;
        props?: { childrenIds?: string[] | null } | null;
    };
};

export type ColumnsContainerBlock = {
    type: 'ColumnsContainer';
    data: {
        style?: Pick<EmailBuilderStyle, 'backgroundColor' | 'padding'> | null;
        props?: {
            fixedWidths?:
                | [
                      number | null | undefined,
                      number | null | undefined,
                      number | null | undefined,
                  ]
                | null;
            columnsCount?: 2 | 3 | null;
            columnsGap?: number | null;
            contentAlignment?: 'top' | 'middle' | 'bottom' | null;
            columns?:
                | [
                      { childrenIds: string[] },
                      { childrenIds: string[] },
                      { childrenIds: string[] },
                  ]
                | null;
        } | null;
    };
};

export type EmailBuilderBlock =
    | HeadingBlock
    | TextBlock
    | ButtonBlock
    | ImageBlock
    | AvatarBlock
    | ContainerBlock
    | ColumnsContainerBlock
    | DividerBlock
    | SpacerBlock
    | HtmlBlock;

export type EmailBuilderBlockType = EmailBuilderBlock['type'];

export type EmailBuilderDocument = Record<
    string,
    EmailBuilderBlock | EmailLayoutBlock
>;

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
    'draft' | 'queued' | 'sending' | 'sent' | 'partially_failed' | 'failed';

export type EmailDeliveryStatus =
    | 'queued'
    | 'sending'
    | 'sent'
    | 'delivered'
    | 'delayed'
    | 'bounced'
    | 'complained'
    | 'rejected'
    | 'failed';

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
    updated_at: string | null;
};
