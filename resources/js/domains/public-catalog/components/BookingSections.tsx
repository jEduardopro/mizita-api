import { useTranslation } from 'react-i18next';
import type { BrandColorClasses, WeekdayNumber } from '@/lib/booking-brand';
import type { PublicBusinessPage } from '../types';
import type { BookingDayHours } from './booking-schedule';
import { aboutTextFrom, BOOKING_SECTION_IDS } from './booking-sections';
import { BookingAbout } from './BookingAbout';
import { BookingGallery } from './BookingGallery';
import { BookingHours } from './BookingHours';
import { BookingLocation } from './BookingLocation';
import { BookingSection } from './BookingSection';
import { BookingServices } from './BookingServices';
import { BookingTeam } from './BookingTeam';

type Props = {
    page: PublicBusinessPage;
    days: BookingDayHours[];
    today: WeekdayNumber | null;
    accent: BrandColorClasses;
    themeScope: string | undefined;
};

export function BookingSections({ page, days, today, accent, themeScope }: Props) {
    const { t } = useTranslation('public');

    return (
        <div className="divide-y divide-border rounded-2xl border border-border bg-card text-card-foreground shadow-sm">
            {page.services.length === 0 ? null : (
                <BookingSection
                    id={BOOKING_SECTION_IDS.services}
                    title={t('booking.nav.services')}
                >
                    <BookingServices
                        slug={page.slug}
                        services={page.services}
                        currencyCode={page.currency_code}
                        accentColor={page.brand.accent_color}
                        buttonShape={page.brand.button_shape}
                    />
                </BookingSection>
            )}

            {page.team.length === 0 ? null : (
                <BookingSection id={BOOKING_SECTION_IDS.team} title={t('booking.nav.team')}>
                    <BookingTeam team={page.team} accent={accent} />
                </BookingSection>
            )}

            <BookingSection id={BOOKING_SECTION_IDS.about} title={t('booking.nav.about')}>
                <BookingAbout
                    about={aboutTextFrom(page)}
                    phone={page.contact.phone}
                    links={page.contact.links}
                    cancellationWindowMinutes={page.cancellation_window_minutes}
                    themeScope={themeScope}
                />
            </BookingSection>

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
                    <BookingHours days={days} today={today} accent={accent} />
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
        </div>
    );
}
