import { Head, Link } from '@inertiajs/react';
import { cn } from 'cn';
import { ArrowLeft, Store } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { brandColorClasses, THEME_SCOPES } from '@/lib/booking-brand';
import { BOOKING_STEPS } from './booking-steps';
import { BookingBusinessCard } from './BookingBusinessCard';
import { BookingStepHeading } from './BookingStepHeading';
import { BookingSummary } from './BookingSummary';
import type { ReadyBookingFlow } from './use-booking-flow';

type Props = {
    flow: ReadyBookingFlow;
    title: string;
    description?: string;
    children: ReactNode;
};

const FULL_PROGRESS = 100;

export function BookingFlowLayout({ flow, title, description, children }: Props) {
    const { t } = useTranslation('public');

    const { page } = flow;
    const accent = brandColorClasses[page.brand.accent_color];
    const themeScope = THEME_SCOPES[page.brand.theme];

    const position = BOOKING_STEPS.indexOf(flow.step) + 1;
    const total = BOOKING_STEPS.length;
    const progress = t('booking.flow.progress', { current: position, total });
    const hasSelections = flow.service !== null;

    return (
        <div className={cn('flex min-h-svh flex-col bg-background text-foreground', themeScope)}>
            <Head title={title} />

            <header className="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur-md supports-[backdrop-filter]:bg-background/70">
                <div className="mx-auto flex h-14 w-full max-w-5xl items-center gap-2 px-5 sm:px-8">
                    <Link
                        href={flow.backUrl}
                        aria-label={t('booking.flow.back')}
                        className="-ml-2.5 grid size-11 shrink-0 place-content-center rounded-full outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <ArrowLeft aria-hidden="true" className="size-5" />
                    </Link>

                    <span
                        className={cn(
                            'grid size-8 shrink-0 place-content-center overflow-hidden rounded-full',
                            accent.surface,
                        )}
                    >
                        {page.logo_url === null ? (
                            <Store aria-hidden="true" className="size-4 text-muted-foreground" />
                        ) : (
                            <img src={page.logo_url} alt="" className="size-full object-cover" />
                        )}
                    </span>

                    <span className="truncate font-heading text-sm font-medium tracking-[-0.01em]">
                        {page.name}
                    </span>
                </div>

                <div
                    role="progressbar"
                    aria-label={progress}
                    aria-valuemin={1}
                    aria-valuemax={total}
                    aria-valuenow={position}
                    className="h-0.5 w-full bg-border"
                >
                    <div
                        className={cn(
                            'h-full motion-safe:transition-[width] motion-safe:duration-500',
                            accent.accent,
                        )}
                        style={{ width: `${(position / total) * FULL_PROGRESS}%` }}
                    />
                </div>
            </header>

            <main className="flex-1">
                <div className="mx-auto grid w-full max-w-5xl gap-8 px-5 py-8 sm:px-8 sm:py-10 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-10 lg:py-12">
                    <aside
                        className={cn(
                            'min-w-0 gap-4 lg:sticky lg:top-20 lg:col-start-2 lg:row-start-1 lg:grid lg:self-start',
                            hasSelections ? 'grid' : 'hidden',
                        )}
                    >
                        <BookingBusinessCard page={page} accent={accent} />

                        <BookingSummary
                            service={flow.service}
                            staffMember={flow.staffMember}
                            startsAt={flow.startsAt}
                            timezone={flow.timezone}
                            currencyCode={page.currency_code}
                        />
                    </aside>

                    <div className="grid min-w-0 gap-6 lg:col-start-1 lg:row-start-1">
                        <BookingStepHeading
                            eyebrow={progress}
                            title={title}
                            description={description}
                        />

                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}
