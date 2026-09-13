import type { PhoneCountryCode } from '@/lib/phone';

/**
 * A business as `BusinessResource` serialises it.
 *
 * `business_id` is deliberately absent everywhere in this API: the caller
 * already operates inside one business. The slug is present because it is the
 * public booking address, and it is the server's to derive — never sent.
 */
export type Business = {
    /** The uuid. */
    id: string;
    name: string;
    slug: string;
    /** IANA identifier, e.g. `America/Mexico_City`. */
    timezone: string;
    industry_id: string;
    /** ISO 8601, `DATE_ATOM`. */
    created_at: string;
};

/** Why a name cannot be used. `null` when it can. */
export type NameUnavailableReason = 'taken' | 'not_sluggable';

/** The answer to "could this name be a booking address?". */
export type BusinessNameAvailability = {
    available: boolean;
    /** The slug the name would produce, or null when it would produce none. */
    slug: string | null;
    reason: NameUnavailableReason | null;
};

/**
 * The phone as the API takes it: the country and the number people actually say
 * out loud, kept apart. Composing them into one E.164 string here would make the
 * client decide a formatting rule the backend owns.
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
