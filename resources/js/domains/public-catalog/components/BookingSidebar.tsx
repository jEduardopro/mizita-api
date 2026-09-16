import { cn } from 'cn';
import { MapPin, Phone, Store } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { brandColorClasses, type TimeInterval } from '@/lib/booking-brand';
import type { PublicBusinessPage } from '../types';
import { addressLinesFrom } from './booking-address';
import { intervalKeyFor, intervalLabelFor } from './booking-schedule';
import { BookingCta } from './BookingCta';

type Props = {
    page: PublicBusinessPage;
    todayIntervals: TimeInterval[];
};

export function BookingSidebar({ page, todayIntervals }: Props) {
    const { t, i18n } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');

    const accent = brandColorClasses[page.brand.accent_color];

    return (
        <aside className="grid gap-5 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm">
            <div className="flex items-center gap-3">
                <span
                    className={cn(
                        'grid size-12 shrink-0 place-content-center overflow-hidden rounded-full',
                        accent.surface,
                    )}
                >
                    {page.logo_url === null ? (
                        <Store aria-hidden="true" className="size-5 text-muted-foreground" />
                    ) : (
                        <img src={page.logo_url} alt="" className="size-full object-cover" />
                    )}
                </span>

                <p className="min-w-0 font-heading text-base font-medium text-balance">
                    {page.name}
                </p>
            </div>

            <BookingCta
                accentColor={page.brand.accent_color}
                buttonShape={page.brand.button_shape}
            />

            <div className="grid gap-3 border-t border-border pt-5 text-sm">
                {page.schedule.length === 0 ? null : (
                    <p className="flex items-start justify-between gap-3">
                        <span className="text-muted-foreground">{t('booking.hours.today')}</span>

                        {todayIntervals.length === 0 ? (
                            <span>{tCommon('hours.closed')}</span>
                        ) : (
                            <span className="grid justify-items-end tabular-nums">
                                {todayIntervals.map((interval) => (
                                    <span
                                        key={intervalKeyFor(interval)}
                                        className="whitespace-nowrap"
                                    >
                                        {intervalLabelFor(interval, i18n.language)}
                                    </span>
                                ))}
                            </span>
                        )}
                    </p>
                )}

                {page.location === null ? null : (
                    <p className="flex items-start gap-2">
                        <MapPin
                            aria-hidden="true"
                            className="mt-1 size-4 shrink-0 text-muted-foreground"
                        />

                        <span className="grid leading-relaxed">
                            {addressLinesFrom(page.location).map((line) => (
                                <span key={line}>{line}</span>
                            ))}
                        </span>
                    </p>
                )}

                {page.contact.phone === null ? null : (
                    <a
                        href={`tel:${page.contact.phone}`}
                        className="-mx-2 flex h-11 items-center gap-2 rounded-lg px-2 transition-colors outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <Phone aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />
                        {page.contact.phone}
                    </a>
                )}
            </div>
        </aside>
    );
}
