/**
 * Facts only: a country code and its dialling prefix. The country's *name* is
 * copy, so it lives in the locale catalogues and reaches a field pre-translated.
 */
export const SUPPORTED_PHONE_COUNTRIES = [
    { code: 'MX', dialCode: '+52' },
    { code: 'US', dialCode: '+1' },
] as const;

export type PhoneCountryCode = (typeof SUPPORTED_PHONE_COUNTRIES)[number]['code'];
