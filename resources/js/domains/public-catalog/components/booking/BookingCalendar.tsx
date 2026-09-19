import { cn } from 'cn';
import { enUS } from 'date-fns/locale/en-US';
import { es } from 'date-fns/locale/es';
import { getDefaultClassNames, type Locale, type Matcher } from 'react-day-picker';
import { useTranslation } from 'react-i18next';
import { dateFromIso, isoFromDate } from '@/components/form/date-format';
import { buttonVariants } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import type { BrandColorClasses } from '@/lib/booking-brand';
import { firstDayOfMonth, monthOfIsoDate } from './booking-slots';

type Props = {
    month: string;
    selectedDate: string | null;
    availableDates: ReadonlySet<string>;
    earliestDate: string;
    latestDate: string;
    accent: BrandColorClasses;
    onMonthChange(month: string): void;
    onSelectDate(date: string): void;
};

const CALENDAR_LOCALES: Record<string, Locale> = {
    en: enUS,
    es,
};

const CELL_SIZE_CLASS =
    '[--cell-size:min(--spacing(11),calc((100vw-5.25rem)/7))] sm:[--cell-size:--spacing(11)]';

const NAV_BUTTON_CLASS = cn(
    buttonVariants({ variant: 'ghost' }),
    'size-11 p-0 select-none aria-disabled:opacity-50',
);

const defaultClassNames = getDefaultClassNames();

const CALENDAR_CLASS_NAMES = {
    today: cn('rounded-(--cell-radius) font-semibold', defaultClassNames.today),
    day_button: cn(
        'data-[selected-single=true]:bg-transparent data-[selected-single=true]:text-inherit data-[selected-single=true]:hover:bg-transparent data-[selected-single=true]:hover:text-inherit',
        defaultClassNames.day_button,
    ),
    month_caption: cn(
        'flex h-11 w-full items-center justify-center px-11',
        defaultClassNames.month_caption,
    ),
    button_previous: cn(NAV_BUTTON_CLASS, defaultClassNames.button_previous),
    button_next: cn(NAV_BUTTON_CLASS, defaultClassNames.button_next),
};

function calendarLocale(language: string): Locale {
    return CALENDAR_LOCALES[language.split('-')[0]] ?? enUS;
}

export function BookingCalendar({
    month,
    selectedDate,
    availableDates,
    earliestDate,
    latestDate,
    accent,
    onMonthChange,
    onSelectDate,
}: Props) {
    const { t, i18n } = useTranslation('public');

    const shownMonth = dateFromIso(firstDayOfMonth(month));
    const earliest = dateFromIso(earliestDate);
    const latest = dateFromIso(latestDate);
    const selected = selectedDate === null ? null : dateFromIso(selectedDate);

    const isUnavailable: Matcher = (date) => ! availableDates.has(isoFromDate(date));
    const unbookable: Matcher[] = [isUnavailable];

    if (earliest !== null) {
        unbookable.push({ before: earliest });
    }

    if (latest !== null) {
        unbookable.push({ after: latest });
    }

    function selectDay(date: Date | undefined): void {
        if (date === undefined) {
            return;
        }

        onSelectDate(isoFromDate(date));
    }

    function showMonth(date: Date): void {
        onMonthChange(monthOfIsoDate(isoFromDate(date)));
    }

    return (
        <Calendar
            mode="single"
            selected={selected ?? undefined}
            onSelect={selectDay}
            month={shownMonth ?? undefined}
            onMonthChange={showMonth}
            startMonth={earliest ?? undefined}
            endMonth={latest ?? undefined}
            disabled={unbookable}
            showOutsideDays={false}
            locale={calendarLocale(i18n.language)}
            labels={{
                labelPrevious: () => t('booking.flow.time.previousMonth'),
                labelNext: () => t('booking.flow.time.nextMonth'),
            }}
            classNames={CALENDAR_CLASS_NAMES}
            modifiersClassNames={{
                selected: cn('rounded-(--cell-radius)', accent.accent, accent.accentForeground),
            }}
            className={cn('p-3', CELL_SIZE_CLASS)}
        />
    );
}
