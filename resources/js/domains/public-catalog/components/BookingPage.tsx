import { Head } from '@inertiajs/react';
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
                logoUrl={page.logo_url}
                phone={page.contact.phone}
                accent={accent}
                sections={bookingNavSections(page, t)}
            />

            <main className="flex-1">
                <BookingHero bannerUrl={page.brand.banner_url} accent={accent} />

                <div className="relative z-10 mx-auto -mt-4 grid w-full max-w-5xl gap-8 px-5 pb-16 sm:-mt-8 sm:px-8 sm:pb-24 lg:-mt-12 lg:grid-cols-[minmax(0,1fr)_19rem] lg:gap-10">
                    <div className="min-w-0 lg:sticky lg:top-20 lg:col-start-2 lg:row-start-1 lg:self-start">
                        <BookingSidebar
                            page={page}
                            todayIntervals={todayIntervals}
                            openState={openState}
                        />
                    </div>

                    <div className="min-w-0 lg:col-start-1 lg:row-start-1">
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
        </div>
    );
}
