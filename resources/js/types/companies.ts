import type { Paginated } from './audiences';
import type { CompanyOption, ContactSummary } from './contacts';

export type CompanySummary = {
    uuid: string;
    name: string;
    favicon: string | null;
    domains: string[];
    contacts_count: number;
    created_at: string | null;
};

export type CompanyContact = ContactSummary & {
    audiences: CompanyOption[];
};

export type Company = CompanySummary & {
    contacts: Paginated<CompanyContact>;
};

export type CompanyContactFilters = {
    search: string;
    audience: string;
};

export type CompanyIndexFilters = {
    search: string;
    sort: 'newest' | 'oldest' | 'name' | 'contacts';
};
