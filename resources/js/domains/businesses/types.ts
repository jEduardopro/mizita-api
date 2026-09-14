import type { PhoneCountryCode } from '@/lib/phone';

export type Business = {
    id: string;
    name: string;
    slug: string;
    timezone: string;
    industry_id: string;
    created_at: string;
};

export type NameUnavailableReason = 'taken' | 'not_sluggable';

export type BusinessNameAvailability = {
    available: boolean;
    slug: string | null;
    reason: NameUnavailableReason | null;
};

export type BusinessPhonePayload = {
    country_code: PhoneCountryCode;
    national_number: string;
};

export type CreateBusinessPayload = {
    name: string;
    timezone: string;
    industry_id: string;
    phone?: BusinessPhonePayload;
};
