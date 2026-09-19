import { Link } from '@inertiajs/react';
import { cn } from 'cn';
import { Store } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { BrandColorClasses } from '@/lib/booking-brand';
import type { PublicBusinessPage } from '../../types';
import { addressLinesFrom } from '../booking-address';
import { businessPageUrl } from './booking-steps';

type Props = {
    page: PublicBusinessPage;
    accent: BrandColorClasses;
};

export function BookingBusinessCard({ page, accent }: Props) {
    const { t } = useTranslation('public');

    const addressLines = page.location === null ? [] : addressLinesFrom(page.location);

    return (
        <section className="hidden gap-4 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm lg:grid">
            <h2 className="text-[0.6875rem] font-medium tracking-[0.1em] text-muted-foreground uppercase">
                {t('booking.flow.business.title')}
            </h2>

            <div className="flex min-w-0 items-center gap-3">
                <span
                    className={cn(
                        'grid size-11 shrink-0 place-content-center overflow-hidden rounded-full',
                        accent.surface,
                    )}
                >
                    {page.logo_url === null ? (
                        <Store aria-hidden="true" className="size-5 text-muted-foreground" />
                    ) : (
                        <img src={page.logo_url} alt="" className="size-full object-cover" />
                    )}
                </span>

                <span className="min-w-0 font-heading text-sm font-semibold tracking-[-0.01em]">
                    {page.name}
                </span>
            </div>

            {addressLines.length === 0 ? null : (
                <p className="grid text-sm leading-relaxed text-muted-foreground">
                    {addressLines.map((line) => (
                        <span key={line}>{line}</span>
                    ))}
                </p>
            )}

            <Link
                href={businessPageUrl(page.slug)}
                className="justify-self-start rounded-md text-sm font-medium underline underline-offset-4 outline-none hover:no-underline focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                {t('booking.flow.business.viewPage')}
            </Link>
        </section>
    );
}
