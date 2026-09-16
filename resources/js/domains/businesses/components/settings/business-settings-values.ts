import {
    LINK_PLATFORMS,
    WEEKDAYS,
    type BrandColor,
    type BusinessSettings,
    type ButtonShape,
    type LinkPlatform,
    type PageTheme,
    type ScheduleRule,
    type UpdateBusinessSettingsPayload,
    type WeeklyHours,
} from '@/domains/businesses/types';
import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import { resolvedTimezone } from '@/lib/timezone';
import { DEFAULT_COUNTRY_CODE, DEFAULT_CURRENCY_CODE } from './location-options';

const DEFAULT_ACCENT_COLOR: BrandColor = 'ink';

const DEFAULT_BUTTON_SHAPE: ButtonShape = 'pill';

const DEFAULT_PAGE_THEME: PageTheme = 'light';

const DEFAULT_PHONE_COUNTRY: PhoneCountryCode = 'MX';

export const BUSINESS_SETTINGS_SECTION_IDS = {
    brand: 'brand',
    appearance: 'appearance',
    contact: 'contact',
    location: 'location',
    hours: 'hours',
    links: 'links',
} as const;

export const BUSINESS_SETTINGS_SECTIONS = [
    { id: BUSINESS_SETTINGS_SECTION_IDS.brand, labelKey: 'businessSettings.sections.brand' },
    {
        id: BUSINESS_SETTINGS_SECTION_IDS.appearance,
        labelKey: 'businessSettings.sections.appearance',
    },
    { id: BUSINESS_SETTINGS_SECTION_IDS.contact, labelKey: 'businessSettings.sections.contact' },
    { id: BUSINESS_SETTINGS_SECTION_IDS.location, labelKey: 'businessSettings.sections.location' },
    { id: BUSINESS_SETTINGS_SECTION_IDS.hours, labelKey: 'businessSettings.sections.hours' },
    { id: BUSINESS_SETTINGS_SECTION_IDS.links, labelKey: 'businessSettings.sections.links' },
] as const;

export type BusinessSettingsFormValues = {
    name: string;
    slug: string;
    industryId: string | null;
    about: string;
    accentColor: BrandColor;
    buttonShape: ButtonShape;
    theme: PageTheme;
    contactEmail: string;
    phoneCountry: PhoneCountryCode;
    phoneNumber: string;
    street: string;
    city: string;
    stateId: string | null;
    postalCode: string;
    countryCode: string;
    latitude: string | null;
    longitude: string | null;
    currencyCode: string;
    timezone: string;
    hours: WeeklyHours;
    links: Record<LinkPlatform, string>;
};

export type BusinessSettingsField = keyof BusinessSettingsFormValues;

export const serverFields: Record<BusinessSettingsField, string> = {
    name: 'brand.name',
    slug: 'brand.slug',
    industryId: 'brand.industry_id',
    about: 'brand.about',
    accentColor: 'appearance.accent_color',
    buttonShape: 'appearance.button_shape',
    theme: 'appearance.theme',
    contactEmail: 'contact.contact_email',
    phoneCountry: 'contact.phone',
    phoneNumber: 'contact.phone',
    street: 'location.street',
    city: 'location.city',
    stateId: 'location.state_id',
    postalCode: 'location.postal_code',
    countryCode: 'location.country_code',
    latitude: 'location.latitude',
    longitude: 'location.longitude',
    currencyCode: 'location.currency_code',
    timezone: 'location.timezone',
    hours: 'schedule',
    links: 'links',
};

function emptyWeeklyHours(): WeeklyHours {
    return { 1: [], 2: [], 3: [], 4: [], 5: [], 6: [], 7: [] };
}

function weeklyHoursFrom(schedule: ScheduleRule[]): WeeklyHours {
    const hours = emptyWeeklyHours();

    for (const rule of schedule) {
        hours[rule.weekday].push({ starts_at: rule.starts_at, ends_at: rule.ends_at });
    }

    return hours;
}

function emptyLinks(): Record<LinkPlatform, string> {
    return {
        website: '',
        instagram: '',
        facebook: '',
        tiktok: '',
        x: '',
        linkedin: '',
        youtube: '',
        whatsapp: '',
    };
}

function linksFrom(settings: BusinessSettings): Record<LinkPlatform, string> {
    const links = emptyLinks();

    for (const link of settings.links) {
        links[link.platform] = link.url;
    }

    return links;
}

function phoneCountryFrom(settings: BusinessSettings): PhoneCountryCode {
    const supported = SUPPORTED_PHONE_COUNTRIES.find(
        (country) => country.code === settings.phone?.country_code,
    );

    return supported?.code ?? DEFAULT_PHONE_COUNTRY;
}

export function initialBusinessSettingsValues(
    settings: BusinessSettings | null,
): BusinessSettingsFormValues {
    if (settings === null) {
        return {
            name: '',
            slug: '',
            industryId: null,
            about: '',
            accentColor: DEFAULT_ACCENT_COLOR,
            buttonShape: DEFAULT_BUTTON_SHAPE,
            theme: DEFAULT_PAGE_THEME,
            contactEmail: '',
            phoneCountry: DEFAULT_PHONE_COUNTRY,
            phoneNumber: '',
            street: '',
            city: '',
            stateId: null,
            postalCode: '',
            countryCode: DEFAULT_COUNTRY_CODE,
            latitude: null,
            longitude: null,
            currencyCode: DEFAULT_CURRENCY_CODE,
            timezone: resolvedTimezone(),
            hours: emptyWeeklyHours(),
            links: emptyLinks(),
        };
    }

    return {
        name: settings.name,
        slug: settings.slug,
        industryId: settings.industry_id,
        about: settings.about ?? '',
        accentColor: settings.booking_page.accent_color,
        buttonShape: settings.booking_page.button_shape,
        theme: settings.booking_page.theme,
        contactEmail: settings.contact_email ?? '',
        phoneCountry: phoneCountryFrom(settings),
        phoneNumber: settings.phone?.national_number ?? '',
        street: settings.address?.street ?? '',
        city: settings.address?.city ?? '',
        stateId: settings.address?.state_id ?? null,
        postalCode: settings.address?.postal_code ?? '',
        countryCode: settings.address?.country_code ?? DEFAULT_COUNTRY_CODE,
        latitude: settings.address?.latitude ?? null,
        longitude: settings.address?.longitude ?? null,
        currencyCode: settings.currency_code,
        timezone: settings.timezone,
        hours: weeklyHoursFrom(settings.schedule),
        links: linksFrom(settings),
    };
}

function trimmedOrNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function scheduleFrom(hours: WeeklyHours): ScheduleRule[] {
    return WEEKDAYS.flatMap((weekday) =>
        hours[weekday].map((interval) => ({
            weekday,
            starts_at: interval.starts_at,
            ends_at: interval.ends_at,
        })),
    );
}

function submittedLinkPlatforms(links: Record<LinkPlatform, string>): LinkPlatform[] {
    return LINK_PLATFORMS.filter((platform) => links[platform].trim() !== '');
}

export function businessSettingsPayloadFrom(
    values: BusinessSettingsFormValues,
): UpdateBusinessSettingsPayload {
    const nationalNumber = values.phoneNumber.trim();

    return {
        brand: {
            name: values.name.trim(),
            slug: values.slug.trim(),
            industry_id: values.industryId ?? '',
            about: trimmedOrNull(values.about),
        },
        appearance: {
            accent_color: values.accentColor,
            button_shape: values.buttonShape,
            theme: values.theme,
        },
        contact: {
            contact_email: trimmedOrNull(values.contactEmail),
            phone:
                nationalNumber === ''
                    ? null
                    : { country_code: values.phoneCountry, national_number: nationalNumber },
        },
        location: {
            street: values.street.trim(),
            city: values.city.trim(),
            state_id: values.stateId,
            postal_code: values.postalCode.trim(),
            country_code: values.countryCode,
            latitude: values.latitude,
            longitude: values.longitude,
            currency_code: values.currencyCode,
            timezone: values.timezone,
        },
        schedule: scheduleFrom(values.hours),
        links: submittedLinkPlatforms(values.links).map((platform) => ({
            platform,
            url: values.links[platform].trim(),
        })),
    };
}
