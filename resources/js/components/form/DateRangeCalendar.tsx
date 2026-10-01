import { useMemo } from 'react';
import type { DateRange } from 'react-day-picker';
import { useTranslation } from 'react-i18next';
import { calendarLocale } from '@/components/form/calendar-locale';
import { fullDateFormatter } from '@/components/form/date-format';
import { Calendar } from '@/components/ui/calendar';

export type DateRangeCalendarMessages = {
    previousMonth: string;
    nextMonth: string;
    today: string;
};

type Props = {
    selected: DateRange;
    onSelect: (days: DateRange) => void;
    month: Date;
    onMonthChange: (month: Date) => void;
    visibleMonths: number;
    today?: Date;
    latest?: Date;
    messages: DateRangeCalendarMessages;
};

export function DateRangeCalendar({
    selected,
    onSelect,
    month,
    onMonthChange,
    visibleMonths,
    today,
    latest,
    messages,
}: Props) {
    const { i18n } = useTranslation();
    const dayFormatter = useMemo(() => fullDateFormatter(i18n.language), [i18n.language]);

    return (
        <Calendar
            mode="range"
            required
            resetOnSelect
            selected={selected}
            onSelect={onSelect}
            month={month}
            onMonthChange={onMonthChange}
            numberOfMonths={visibleMonths}
            today={today}
            locale={calendarLocale(i18n.language)}
            endMonth={latest}
            disabled={latest === undefined ? undefined : { after: latest }}
            labels={{
                labelPrevious: () => messages.previousMonth,
                labelNext: () => messages.nextMonth,
                labelDayButton: (date, modifiers) =>
                    modifiers.today ? `${messages.today}, ${dayFormatter.format(date)}` : dayFormatter.format(date),
            }}
            className="mx-auto p-3 [--cell-size:min(--spacing(11),calc((100vw-3rem)/7))] md:[--cell-size:--spacing(9)]"
        />
    );
}
