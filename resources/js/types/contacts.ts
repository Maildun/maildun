import type { Paginated, Tag } from './audiences';

export type CompanyOption = {
    uuid: string;
    name: string;
};

export type ContactImport = {
    uuid: string;
    file_name: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    processed_rows: number;
    imported_contacts: number;
    imported_subscribers: number;
    duplicate_rows: number;
    failed_rows: number;
    errors: string[];
    created_at: string | null;
    completed_at: string | null;
};

export type ContactSummary = {
    uuid: string;
    avatar: string;
    email: string;
    first_name: string | null;
    last_name: string | null;
    company_assignment_mode: 'automatic' | 'manual';
    company: CompanyOption | null;
    tags: Tag[];
    audiences_count: number;
    created_at: string | null;
};

export type ContactMembership = {
    uuid: string;
    audience: CompanyOption;
    status: 'subscribed' | 'unsubscribed';
    source: 'manual' | 'form' | 'api';
    can_manage: boolean;
    subscribed_at: string | null;
    source_form: CompanyOption | null;
    unsubscribed_at: string | null;
    consent_text: string | null;
    consented_at: string | null;
    consent_ip: string | null;
    attributes: ContactAttribute[];
    segments: CompanyOption[];
};

export type ContactAttribute = {
    uuid: string;
    name: string;
    type: string;
    value: string | number | null;
};

export type ContactDelivery = {
    uuid: string;
    campaign: {
        uuid: string | null;
        name: string;
    };
    audience: CompanyOption | null;
    status: string;
    opens: number;
    clicks: number;
    sent_at: string | null;
};

export type ContactActivity = {
    audiences: number;
    subscribed: number;
    received: number;
    opened: number;
    clicked: number;
};

export type ContactAutomation = {
    uuid: string;
    name: string;
    automation_uuid: string | null;
    status: string;
    started_at: string | null;
    completed_at: string | null;
    audience: CompanyOption | null;
};

export type Contact = ContactSummary & {
    company_assignment_mode: 'automatic' | 'manual';
    memberships: ContactMembership[];
    deliveries: ContactDelivery[];
};

export type ContactLookupMembership = {
    audience: CompanyOption;
    status: 'subscribed' | 'unsubscribed';
};

export type ContactLookupContact = {
    uuid: string;
    avatar: string;
    email: string;
    first_name: string | null;
    last_name: string | null;
    company: CompanyOption | null;
    tags: Tag[];
    memberships: ContactLookupMembership[];
};

export type ContactLookupResponse = {
    contact: ContactLookupContact | null;
};

export type ContactIndexFilters = {
    search: string;
    company: string;
    audience: string;
    sort: 'newest' | 'oldest' | 'name';
};

export type ContactIndexProps = {
    contacts: Paginated<ContactSummary>;
    companies: CompanyOption[];
    audiences: CompanyOption[];
    tags: Tag[];
    contactImports: ContactImport[];
    filters: ContactIndexFilters;
    canManage: boolean;
};
