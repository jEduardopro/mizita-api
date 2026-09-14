export const SUPPORTED_PHONE_COUNTRIES = [
    { code: 'MX', dialCode: '+52' },
    { code: 'US', dialCode: '+1' },
] as const;

export type PhoneCountryCode = (typeof SUPPORTED_PHONE_COUNTRIES)[number]['code'];
