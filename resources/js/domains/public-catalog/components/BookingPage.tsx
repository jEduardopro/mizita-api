import { Head, Link, usePage } from '@inertiajs/react';
import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { brandColorClasses, THEME_SCOPES } from '@/lib/booking-brand';
import { isoWeekdayIn, timeOfDayIn } from '@/lib/timezone';
import type { PublicBusinessPage } from '../types';
import { resolveOpenState, weeklyHoursFrom } from './booking-schedule';
import { bookingNavSections } from './booking-sections';
import { BookingHeader } from './BookingHeader';
import { BookingHero } from './BookingHero';
import { BookingSections } from './BookingSections';
import { BookingSidebar } from './BookingSidebar';

type Props = {
    page: PublicBusinessPage;
    openServiceSlug: string | null;
};

export function BookingPage({ page, openServiceSlug }: Props) {
    const { t } = useTranslation('public');
    const { name: productName } = usePage().props;

    const accent = brandColorClasses[page.brand.accent_color];
    const themeScope = THEME_SCOPES[page.brand.theme];

    const now = new Date();
    const days = weeklyHoursFrom(page.schedule);
    const today = isoWeekdayIn(page.timezone, now);
    const openState = resolveOpenState(days, today, timeOfDayIn(page.timezone, now));
    const todayIntervals = days.find((day) => day.weekday === today)?.intervals ?? [];

    return (
        <div className={cn('flex min-h-svh flex-col bg-background text-foreground', themeScope)}>
            <Head title={page.name} />

            <BookingHeader
                name={page.name}
                phone={page.contact.phone}
                sections={bookingNavSections(page, t)}
            />

            <main className="flex-1">
                <BookingHero
                    name={page.name}
                    logoUrl={page.logo_url}
                    bannerUrl={page.brand.banner_url}
                    accent={accent}
                    openState={openState}
                />

                <div className="mx-auto mt-8 grid w-full max-w-5xl gap-10 px-5 pb-16 sm:mt-10 sm:px-8 sm:pb-24 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-12">
                    <div className="lg:sticky lg:top-20 lg:col-start-2 lg:row-start-1 lg:self-start">
                        <BookingSidebar page={page} todayIntervals={todayIntervals} />
                    </div>

                    <div className="lg:col-start-1 lg:row-start-1">
                        <BookingSections
                            page={page}
                            days={days}
                            today={today}
                            accent={accent}
                            themeScope={themeScope}
                            openServiceSlug={openServiceSlug}
                        />
                    </div>
                </div>
            </main>

            <footer className="border-t border-border">
                <div className="mx-auto flex w-full max-w-5xl items-center justify-center px-5 py-4 sm:px-8">
                    <Link
                        href="/"
                        className="flex h-11 items-center rounded-sm px-2 text-xs text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('booking.footer.poweredBy', { name: productName })}
                    </Link>
                </div>
            </footer>
        </div>
    );
}
