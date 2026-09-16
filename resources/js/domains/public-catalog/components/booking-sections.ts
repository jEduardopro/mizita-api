import type { TFunction } from 'i18next';
import type { Section } from '@/components/public/shell/SectionNav';
import type { PublicBusinessPage } from '../types';

export const BOOKING_SECTION_IDS = {
    services: 'services',
    team: 'team',
    about: 'about',
    gallery: 'gallery',
    hours: 'hours',
    location: 'location',
} as const;

const NAV_ITEMS = [
    { id: BOOKING_SECTION_IDS.services, labelKey: 'booking.nav.services' },
    { id: BOOKING_SECTION_IDS.team, labelKey: 'booking.nav.team' },
    { id: BOOKING_SECTION_IDS.about, labelKey: 'booking.nav.about' },
    { id: BOOKING_SECTION_IDS.gallery, labelKey: 'booking.nav.gallery' },
    { id: BOOKING_SECTION_IDS.location, labelKey: 'booking.nav.location' },
] as const;

type NavId = (typeof NAV_ITEMS)[number]['id'];

function filledSections(page: PublicBusinessPage): Record<NavId, boolean> {
    return {
        [BOOKING_SECTION_IDS.services]: page.services.length > 0,
        [BOOKING_SECTION_IDS.team]: page.team.length > 0,
        [BOOKING_SECTION_IDS.about]: (page.about ?? '').trim() !== '',
        [BOOKING_SECTION_IDS.gallery]: page.brand.gallery.length > 0,
        [BOOKING_SECTION_IDS.location]: page.location !== null,
    };
}

export function bookingNavSections(
    page: PublicBusinessPage,
    t: TFunction<'public'>,
): Section[] {
    const filled = filledSections(page);

    return NAV_ITEMS.filter((item) => filled[item.id]).map((item) => ({
        id: item.id,
        label: t(item.labelKey),
    }));
}
