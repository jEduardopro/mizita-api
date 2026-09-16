export const COUNTRY_CODES = ['MX', 'US'] as const;

export type CountryCode = (typeof COUNTRY_CODES)[number];

export const CURRENCY_CODES = ['MXN', 'USD'] as const;

export type CurrencyCode = (typeof CURRENCY_CODES)[number];

export const DEFAULT_COUNTRY_CODE: CountryCode = 'MX';

export const DEFAULT_CURRENCY_CODE: CurrencyCode = 'MXN';

export const COUNTRY_LABEL_KEYS = {
    MX: 'businessSettings.location.countries.MX',
    US: 'businessSettings.location.countries.US',
} as const satisfies Record<CountryCode, string>;

export const CURRENCY_LABEL_KEYS = {
    MXN: 'businessSettings.location.currencies.MXN',
    USD: 'businessSettings.location.currencies.USD',
} as const satisfies Record<CurrencyCode, string>;
