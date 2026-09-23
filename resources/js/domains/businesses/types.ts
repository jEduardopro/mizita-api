import type {
    BrandColor,
    ButtonShape,
    GalleryImage,
    LinkPlatform,
    PageTheme,
    TimeInterval,
    WeekdayNumber,
} from '@/lib/booking-brand';
import type { PhoneCountryCode } from '@/lib/phone';

export type {
    BrandColor,
    ButtonShape,
    GalleryImage,
    LinkPlatform,
    PageTheme,
    TimeInterval,
    WeekdayNumber,
    WeeklyHours,
} from '@/lib/booking-brand';

export type Business = {
    id: string;
    name: string;
    slug: string;
    timezone: string;
    industry_id: string;
    logo_url: string | null;
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

export type ScheduleRule = TimeInterval & {
    weekday: WeekdayNumber;
};

export type BusinessPhone = BusinessPhonePayload & {
    e164: string;
};

export type BusinessAddress = {
    street: string;
    city: string | null;
    state_id: string | null;
    postal_code: string | null;
    country_code: string;
    latitude: string | null;
    longitude: string | null;
};

export type BusinessLink = {
    platform: LinkPlatform;
    url: string;
    position: number;
};

export type BookingPageSettings = {
    accent_color: BrandColor;
    button_shape: ButtonShape;
    theme: PageTheme;
    banner_url: string | null;
    gallery: GalleryImage[];
};

export type BookingPage = BookingPageSettings & {
    id: string;
};

export type ReorderGalleryPayload = {
    images: string[];
};

export type BookingPolicySettings = {
    lead_time_minutes: number;
    booking_window_minutes: number | null;
    slot_granularity_minutes: number;
    cancellation_window_minutes: number | null;
    policy_message: string | null;
    display_on_booking_page: boolean;
};

export type ContactFieldName = 'phone' | 'email' | 'address';

export type ContactFieldLevel = 'hidden' | 'optional' | 'required';

export type ContactFieldsSettings = Record<ContactFieldName, ContactFieldLevel>;

export type BusinessSettings = {
    id: string;
    name: string;
    slug: string;
    industry_id: string;
    timezone: string;
    about: string | null;
    contact_email: string | null;
    currency_code: string;
    logo_url: string | null;
    phone: BusinessPhone | null;
    address: BusinessAddress | null;
    schedule: ScheduleRule[];
    links: BusinessLink[];
    booking_page: BookingPageSettings;
    booking_policy: BookingPolicySettings;
    contact_fields: ContactFieldsSettings;
};

export type BrandSectionPayload = {
    name: string;
    slug: string;
    industry_id: string;
    about: string | null;
};

export type AppearanceSectionPayload = {
    accent_color: BrandColor;
    button_shape: ButtonShape;
    theme: PageTheme;
};

export type ContactSectionPayload = {
    contact_email: string | null;
    phone: BusinessPhonePayload | null;
};

type SubmittedAddress = {
    street: string;
    city: string;
    state_id: string | null;
    postal_code: string;
    country_code: string;
    latitude: string | null;
    longitude: string | null;
};

export type LocationSectionPayload = SubmittedAddress & {
    currency_code: string;
    timezone: string;
};

export type BookingPolicySectionPayload = {
    lead_time_minutes: number;
    booking_window_minutes: number | null;
    slot_granularity_minutes: number;
    cancellation_window_minutes: number | null;
    policy_message: string | null;
    display_on_booking_page: boolean;
};

export type LinkPayload = {
    platform: LinkPlatform;
    url: string;
};

export type UpdateBusinessSettingsPayload = {
    brand?: BrandSectionPayload;
    appearance?: AppearanceSectionPayload;
    contact?: ContactSectionPayload;
    location?: LocationSectionPayload;
    booking_policy?: BookingPolicySectionPayload;
    contact_fields?: ContactFieldsSettings;
    schedule?: ScheduleRule[];
    links?: LinkPayload[];
};
