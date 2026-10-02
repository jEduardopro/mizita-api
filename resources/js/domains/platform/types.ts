export const PLATFORM_BUSINESS_SORT_FIELDS = [
    'created_at',
    'name',
    'services_count',
    'customers_count',
] as const;

export type PlatformBusinessSortField = (typeof PLATFORM_BUSINESS_SORT_FIELDS)[number];

export type PlatformBusinessPlan = 'free' | 'complete';

export type PlatformBusinessOwner = {
    name: string;
    email: string;
};

export type PlatformBusiness = {
    id: string;
    name: string;
    slug: string;
    created_at: string;
    owner: PlatformBusinessOwner | null;
    services_count: number;
    customers_count: number;
    plan: PlatformBusinessPlan;
};

export type PlatformBusinessListParams = {
    search?: string;
    page: number;
    per_page: number;
    sort: PlatformBusinessSortField;
    direction: 'asc' | 'desc';
};
