import { cn } from 'cn';
import { Clock, MapPin, Store } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { brandColorClasses, type TimeInterval } from '@/lib/booking-brand';
import type { PublicBusinessPage } from '../types';
import { addressLinesFrom } from './booking-address';
import { socialLinksFrom, websiteLinkFrom } from './booking-links';
import { intervalKeyFor, intervalLabelFor } from './booking-schedule';
import { bookingStepUrl } from './booking/booking-steps';
import { BookingContactPills } from './BookingContactPills';
import { BookingCta } from './BookingCta';
import { BookingOpenBadge } from './BookingOpenBadge';
import { BookingSocialLinks } from './BookingSocialLinks';

type Props = {
    page: PublicBusinessPage;
    todayIntervals: TimeInterval[];
};

export function BookingSidebar({ page, todayIntervals }: Props) {
    const { t } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');

    const accent = brandColorClasses[page.brand.accent_color];
    const website = websiteLinkFrom(page.contact.links);
    const socialLinks = socialLinksFrom(page.contact.links);
    const addressLines = page.location === null ? [] : addressLinesFrom(page.location);
    const hasContactLinks = page.contact.phone !== null || page.contact.links.length > 0;

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
                <p className="flex flex-wrap items-center justify-center gap-x-2 text-sm">
                    <Clock aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />

                    <span className="text-muted-foreground">{t('booking.hours.today')}</span>

                    {todayIntervals.length === 0 ? (
                        <span>{tCommon('hours.closed')}</span>
                    ) : (
                        todayIntervals.map((interval) => (
                            <span
                                key={intervalKeyFor(interval)}
                                className="whitespace-nowrap tabular-nums"
                            >
                                {intervalLabelFor(interval)}
                            </span>
                        ))
                    )}
                </p>
            )}

            {addressLines.length === 0 && ! hasContactLinks ? null : (
                <div className="grid w-full justify-items-center gap-5 border-t border-border pt-5">
                    {addressLines.length === 0 ? null : (
                        <p className="flex items-start justify-center gap-2 text-sm leading-relaxed">
                            <MapPin
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />

                            <span className="grid">
                                {addressLines.map((line) => (
                                    <span key={line}>{line}</span>
                                ))}
                            </span>
                        </p>
                    )}

                    {! hasContactLinks ? null : (
                        <div className="grid w-full justify-items-center gap-3">
                            <p className="text-sm font-medium text-balance">
                                {t('booking.contact.title')}
                            </p>

                            <BookingContactPills phone={page.contact.phone} website={website} />

                            {socialLinks.length === 0 ? null : (
                                <BookingSocialLinks links={socialLinks} />
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
