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
};

export type PublicTeamMember = {
    id: string;
    name: string;
};

export type PublicLocation = {
    street: string;
    city: string;
    state: string | null;
    postal_code: string;
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
    services: PublicService[];
    team: PublicTeamMember[];
    location: PublicLocation | null;
    contact: PublicContact;
};
