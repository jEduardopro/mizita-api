import { cn } from 'cn';
import { MapPin, Store } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { brandColorClasses, type WeekdayNumber } from '@/lib/booking-brand';
import type { PublicBusinessPage } from '../types';
import { addressLinesFrom, mapUrlFor } from './booking-address';
import { socialLinksFrom, websiteLinkFrom } from './booking-links';
import type { BookingDayHours } from './booking-schedule';
import { bookingStepUrl, businessPath } from './booking/booking-steps';
import { BookingShareButton } from './booking/BookingShareButton';
import { BookingContactPills } from './BookingContactPills';
import { BookingCta } from './BookingCta';
import { BookingOpenBadge } from './BookingOpenBadge';
import { BookingSidebarHours } from './BookingSidebarHours';
import { BookingSocialLinks } from './BookingSocialLinks';

type Props = {
    page: PublicBusinessPage;
    days: BookingDayHours[];
    today: WeekdayNumber | null;
};

export function BookingSidebar({ page, days, today }: Props) {
    const { t } = useTranslation('public');

    const accent = brandColorClasses[page.brand.accent_color];
    const website = websiteLinkFrom(page.contact.links);
    const socialLinks = socialLinksFrom(page.contact.links);
    const addressLines = page.location === null ? [] : addressLinesFrom(page.location);
    const hasContactLinks = page.contact.phone !== null || page.contact.links.length > 0;

    const shareButton = (
        <BookingShareButton
            url={`${window.location.origin}${businessPath(page.slug)}`}
            title={t('booking.share.title', { name: page.name })}
            label={t('booking.share.label')}
            copied={t('booking.share.copied')}
            copyFailed={t('booking.share.copyFailed')}
            className="text-muted-foreground hover:bg-muted hover:text-foreground"
        />
    );

    return (
        <div className="grid justify-items-center gap-5 rounded-2xl border border-border bg-card p-5 text-center text-card-foreground shadow-sm sm:p-6">
            <div className="grid justify-items-center gap-3">
                <span
                    className={cn(
                        'grid size-16 place-content-center overflow-hidden rounded-full',
                        accent.surface,
                    )}
                >
                    {page.logo_url === null ? (
                        <Store aria-hidden="true" className="size-6 text-muted-foreground" />
                    ) : (
                        <img src={page.logo_url} alt="" className="size-full object-cover" />
                    )}
                </span>

                <h1 className="font-heading text-xl font-semibold tracking-[-0.02em] text-balance">
                    {page.name}
                </h1>
            </div>

            <div className="grid w-full justify-items-center gap-4">
                <BookingCta
                    href={bookingStepUrl(page.slug, 'service', {})}
                    accentColor={page.brand.accent_color}
                    buttonShape={page.brand.button_shape}
                    className="w-full"
                />

                {page.open_state.open ? (
                    <BookingOpenBadge closesAt={page.open_state.closes_at} accent={accent} />
                ) : null}
            </div>

            {page.schedule.length === 0 ? null : (
                <BookingSidebarHours
                    openState={page.open_state}
                    days={days}
                    today={today}
                    timezone={page.timezone}
                    accent={accent}
                />
            )}

            {addressLines.length === 0 && ! hasContactLinks ? null : (
                <div className="grid w-full justify-items-center gap-5 border-t border-border pt-5">
                    {page.location === null || addressLines.length === 0 ? null : (
                        <a
                            href={mapUrlFor(page.location)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group flex items-start justify-center gap-2 rounded-lg text-sm leading-relaxed outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            <MapPin
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />

                            <span className="grid">
                                {addressLines.map((line) => (
                                    <span
                                        key={line}
                                        className="transition-colors group-hover:text-muted-foreground"
                                    >
                                        {line}
                                    </span>
                                ))}

                                <span className="sr-only">{` (${t('booking.location.mapLink')})`}</span>
                            </span>
                        </a>
                    )}

                    {! hasContactLinks ? null : (
                        <div className="grid w-full justify-items-center gap-3">
                            <p className="text-sm font-medium text-balance">
                                {t('booking.contact.title')}
                            </p>

                            <BookingContactPills phone={page.contact.phone} website={website} />

                            <div className="flex flex-wrap items-center justify-center gap-1">
                                {socialLinks.length === 0 ? null : (
                                    <BookingSocialLinks links={socialLinks} />
                                )}

                                {shareButton}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {hasContactLinks ? null : shareButton}
        </div>
    );
}
