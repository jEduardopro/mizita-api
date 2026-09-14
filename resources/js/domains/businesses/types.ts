import type { PhoneCountryCode } from '@/lib/phone';

export type Business = {
    /** The uuid. */
    id: string;
    name: string;
    /** The public booking address. Derived by the server, never sent. */
    slug: string;
    /** IANA identifier, e.g. `America/Mexico_City`. */
    timezone: string;
    industry_id: string;
    /** ISO 8601, `DATE_ATOM`. */
    created_at: string;
};

export type NameUnavailableReason = 'taken' | 'not_sluggable';

export type BusinessNameAvailability = {
    available: boolean;
    /** The slug the name would produce, or null when it would produce none. */
    slug: string | null;
    reason: NameUnavailableReason | null;
};

/**
 * Country and national number stay apart: composing them into one E.164 string
 * here would make the client decide a formatting rule the backend owns.
 */
export type BusinessPhonePayload = {
    country_code: PhoneCountryCode;
    national_number: string;
};

export type CreateBusinessPayload = {
    name: string;
    /** IANA identifier. The only source of local time for the whole agenda. */
    timezone: string;
    industry_id: string;
    /** Omitted entirely when no number was given. */
    phone?: BusinessPhonePayload;
};
