import { useTranslation } from 'react-i18next';
import type { BrandColorClasses, WeekdayNumber } from '@/lib/booking-brand';
import type { PublicBusinessPage } from '../types';
import type { BookingDayHours } from './booking-schedule';
import { BOOKING_SECTION_IDS } from './booking-sections';
import { BookingAbout } from './BookingAbout';
import { BookingGallery } from './BookingGallery';
import { BookingHours } from './BookingHours';
import { BookingLinks } from './BookingLinks';
import { BookingLocation } from './BookingLocation';
import { BookingSection } from './BookingSection';
import { BookingServices } from './BookingServices';
import { BookingTeam } from './BookingTeam';

const LINKS_SECTION_ID = 'links';

type Props = {
    page: PublicBusinessPage;
    days: BookingDayHours[];
    today: WeekdayNumber | null;
    accent: BrandColorClasses;
    themeScope: string | undefined;
    openServiceSlug: string | null;
};

export function BookingSections({
    page,
    days,
    today,
    accent,
    themeScope,
    openServiceSlug,
}: Props) {
    const { t } = useTranslation('public');

    const about = (page.about ?? '').trim();

    return (
        <div className="grid gap-12">
            {page.services.length === 0 ? null : (
                <BookingSection
                    id={BOOKING_SECTION_IDS.services}
                    title={t('booking.nav.services')}
                >
                    <BookingServices
                        services={page.services}
                        currencyCode={page.currency_code}
                        accentColor={page.brand.accent_color}
                        buttonShape={page.brand.button_shape}
                        openServiceSlug={openServiceSlug}
                    />
                </BookingSection>
            )}

            {page.team.length === 0 ? null : (
                <BookingSection id={BOOKING_SECTION_IDS.team} title={t('booking.nav.team')}>
                    <BookingTeam team={page.team} accent={accent} />
                </BookingSection>
            )}

            {about === '' ? null : (
                <BookingSection id={BOOKING_SECTION_IDS.about} title={t('booking.nav.about')}>
                    <BookingAbout about={about} />
                </BookingSection>
            )}

            {page.brand.gallery.length === 0 ? null : (
                <BookingSection id={BOOKING_SECTION_IDS.gallery} title={t('booking.nav.gallery')}>
                    <BookingGallery
                        images={page.brand.gallery}
                        businessName={page.name}
                        themeScope={themeScope}
                    />
                </BookingSection>
            )}

            {page.schedule.length === 0 ? null : (
                <BookingSection id={BOOKING_SECTION_IDS.hours} title={t('booking.hours.title')}>
                    <BookingHours
                        days={days}
                        today={today}
                        timezone={page.timezone}
                        accent={accent}
                    />
                </BookingSection>
            )}

            {page.location === null ? null : (
                <BookingSection
                    id={BOOKING_SECTION_IDS.location}
                    title={t('booking.nav.location')}
                >
                    <BookingLocation location={page.location} />
                </BookingSection>
            )}

            {page.contact.links.length === 0 ? null : (
                <BookingSection id={LINKS_SECTION_ID} title={t('booking.links.title')}>
                    <BookingLinks links={page.contact.links} />
                </BookingSection>
            )}
        </div>
    );
}
