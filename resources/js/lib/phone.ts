export const SUPPORTED_PHONE_COUNTRIES = [
    { code: 'MX', dialCode: '+52' },
    { code: 'US', dialCode: '+1' },
] as const;

export type PhoneCountryCode = (typeof SUPPORTED_PHONE_COUNTRIES)[number]['code'];

export function formatPhoneNumber(phone: { country_code: string; national_number: string }): string {
    const country = SUPPORTED_PHONE_COUNTRIES.find(
        (supported) => supported.code === phone.country_code,
    );

    if (country === undefined) {
        return phone.national_number;
    }

    return `${country.dialCode} ${phone.national_number}`;
}
