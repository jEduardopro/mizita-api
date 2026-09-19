import type {
    BrandColor,
    ButtonShape,
    GalleryImage,
    LinkPlatform,
    PageTheme,
    TimeInterval,
    WeekdayNumber,
} from '@/lib/booking-brand';

export type PublicBrand = {
    accent_color: BrandColor;
    button_shape: ButtonShape;
    theme: PageTheme;
    banner_url: string | null;
    gallery: GalleryImage[];
};

export type PublicScheduleEntry = TimeInterval & {
    weekday: WeekdayNumber;
};

export type PublicService = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    duration_minutes: number;
    price: string;
    image_url: string | null;
    staff_ids: string[];
};

export type PublicTeamMember = {
    id: string;
    name: string;
};

export type PublicLocation = {
    street: string;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country_code: string;
    latitude: string | null;
    longitude: string | null;
};

export type PublicLink = {
    platform: LinkPlatform;
    url: string;
};

export type PublicContact = {
    phone: string | null;
    links: PublicLink[];
};

export type PublicOpenState =
    | { open: true; closes_at: string; opens_on_weekday: null; opens_at: null }
    | {
          open: false;
          closes_at: null;
          opens_on_weekday: WeekdayNumber | null;
          opens_at: string | null;
      };

export type PublicBookingPolicy = {
    policy_message: string;
};

export type PublicBusinessPage = {
    id: string;
    name: string;
    slug: string;
    about: string | null;
    timezone: string;
    currency_code: string;
    logo_url: string | null;
    brand: PublicBrand;
    schedule: PublicScheduleEntry[];
    open_state: PublicOpenState;
    last_bookable_date: string;
    services: PublicService[];
    team: PublicTeamMember[];
    location: PublicLocation | null;
    contact: PublicContact;
    booking_policy?: PublicBookingPolicy;
};

export type PublicAvailableDay = {
    date: string;
    starts: string[];
};

export type PublicAvailabilityQuery = {
    service_id: string;
    staff_id: string;
    from: string;
    to: string;
};

export type PublicBookingStatus = 'booked' | 'cancelled';

export type PublicBooking = {
    reference_code: string;
    customer_name: string;
    service_name: string;
    staff_member_name: string;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    status: PublicBookingStatus;
    cancelled_at: string | null;
    cancellation_window_minutes: number | null;
    changeable: boolean;
};

export type PublicBookingConfirmation = {
    booking: PublicBooking;
    manage_token: string;
};

export type PublicGuestPhonePayload = {
    country_code: string;
    national_number: string;
};

export type PublicGuestPayload = {
    name: string;
    email?: string | null;
    phone?: PublicGuestPhonePayload | null;
};

export type CreatePublicBookingPayload = {
    service_id: string;
    staff_member_id: string;
    starts_at: string;
    guest: PublicGuestPayload;
    notes?: string | null;
};

export type ReschedulePublicBookingPayload = {
    starts_at: string;
};

export type PublicBookingCredentials = {
    reference: string;
    manageToken: string;
};
