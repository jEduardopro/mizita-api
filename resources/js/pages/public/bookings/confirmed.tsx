import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { addressLineFrom, mapUrlFor } from '@/domains/public-catalog/components/booking-address';
import { AddToCalendarMenu } from '@/domains/public-catalog/components/booking/AddToCalendarMenu';
import { BookingConfirmationCard } from '@/domains/public-catalog/components/booking/BookingConfirmationCard';
import { BookingOutcomeLayout } from '@/domains/public-catalog/components/booking/BookingOutcomeLayout';
import { BookingRecordFallback } from '@/domains/public-catalog/components/booking/BookingRecordFallback';
import { bookingManageUrl } from '@/domains/public-catalog/components/booking/booking-steps';
import type { CalendarEvent } from '@/domains/public-catalog/components/booking/calendar-links';
import { useBookingRecord } from '@/domains/public-catalog/components/booking/use-booking-record';
import { brandColorClasses } from '@/lib/booking-brand';

type Props = {
    slug: string;
    reference: string;
};

export default function BookingConfirmedScreen({ slug, reference }: Props) {
    const { t } = useTranslation('public');
    const record = useBookingRecord(slug, reference);

    if (record.status !== 'ready') {
        return <BookingRecordFallback record={record} />;
    }

    const { page, booking, credentials } = record;

    const calendarEvent: CalendarEvent = {
        uid: booking.reference_code,
        title: `${booking.service_name} · ${page.name}`,
        description: t('booking.flow.confirmed.reference', { code: booking.reference_code }),
        location: page.location === null ? '' : addressLineFrom(page.location),
        startsAt: booking.starts_at,
        endsAt: booking.ends_at,
    };

    return (
        <BookingOutcomeLayout page={page} title={t('booking.flow.confirmed.title')}>
            <header className="grid gap-2">
                <h1 className="font-heading text-[clamp(1.5rem,5vw,2rem)] leading-tight font-semibold tracking-[-0.02em] text-balance">
                    {t('booking.flow.confirmed.title')}
                </h1>

                <p className="text-sm leading-relaxed text-muted-foreground text-pretty">
                    {t('booking.flow.confirmed.description')}
                </p>
            </header>

            <BookingConfirmationCard booking={booking} timezone={page.timezone}>
                {page.location === null ? null : (
                    <div className="grid gap-1.5 border-t border-dashed border-border pt-5">
                        <p className="text-[0.6875rem] font-medium tracking-[0.1em] text-muted-foreground uppercase">
                            {t('booking.nav.location')}
                        </p>

                        <a
                            href={mapUrlFor(page.location)}
                            target="_blank"
                            rel="noreferrer"
                            className="justify-self-start rounded-md text-sm leading-relaxed underline decoration-muted-foreground/50 underline-offset-4 outline-none hover:decoration-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            {addressLineFrom(page.location)}

                            <span className="sr-only">{` (${t('booking.location.mapLink')})`}</span>
                        </a>
                    </div>
                )}

                <div className="grid gap-4 border-t border-dashed border-border pt-5 sm:flex sm:flex-wrap sm:items-center sm:justify-between">
                    <AddToCalendarMenu
                        event={calendarEvent}
                        accent={brandColorClasses[page.brand.accent_color]}
                        buttonShape={page.brand.button_shape}
                    />

                    <Link
                        href={bookingManageUrl(
                            page.slug,
                            booking.reference_code,
                            credentials.manageToken,
                        )}
                        className="justify-self-start rounded-md text-sm font-medium underline underline-offset-4 outline-none hover:no-underline focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('booking.flow.confirmed.manage')}
                    </Link>
                </div>
            </BookingConfirmationCard>
        </BookingOutcomeLayout>
    );
}
