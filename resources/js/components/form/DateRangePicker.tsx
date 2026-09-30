import { cn } from 'cn';
import { enUS } from 'date-fns/locale/en-US';
import { es } from 'date-fns/locale/es';
import { CalendarRange } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { DateRange, Locale } from 'react-day-picker';
import { useTranslation } from 'react-i18next';
import {
    dateFromIso,
    formatIsoDate,
    fullDateFormatter,
    isoFromDate,
} from '@/components/form/date-format';
import { CONTROL_DENSITY_CLASSES, useFormDensity } from '@/components/form/form-density';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

export type DateRangeDraft = {
    from: string | null;
    to: string | null;
};

export type DateRangePickerMessages = {
    calendar: string;
    placeholder: string;
    previousMonth: string;
    nextMonth: string;
    today: string;
};

type Props = {
    id: string;
    value: DateRangeDraft;
    onChange: (value: DateRangeDraft) => void;
    messages: DateRangePickerMessages;
    max?: string;
    describedBy?: string;
    className?: string;
};

const CALENDAR_LOCALES: Record<string, Locale> = {
    en: enUS,
    es,
};

const RANGE_SEPARATOR = ' – ';

const FIELD_CLASS =
    'flex w-full items-center gap-2 overflow-hidden rounded-lg border border-input bg-transparent px-2.5 text-left transition-colors outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30';

function calendarLocale(language: string): Locale {
    return CALENDAR_LOCALES[language.split('-')[0]] ?? enUS;
}

function dateOrUndefined(value: string | null): Date | undefined {
    return value === null ? undefined : (dateFromIso(value) ?? undefined);
}

function isoOrNull(date: Date | undefined): string | null {
    return date === undefined ? null : isoFromDate(date);
}

function shownRange(value: DateRangeDraft, locale: string): string {
    if (value.from === null) {
        return '';
    }

    const from = formatIsoDate(value.from, locale);

    if (value.to === null || value.to === value.from) {
        return from;
    }

    return `${from}${RANGE_SEPARATOR}${formatIsoDate(value.to, locale)}`;
}

export function DateRangePicker({
    id,
    value,
    onChange,
    messages,
    max,
    describedBy,
    className,
}: Props) {
    const { i18n } = useTranslation();
    const locale = i18n.language;
    const density = useFormDensity();
    const [open, setOpen] = useState(false);

    const latest = max === undefined ? undefined : (dateFromIso(max) ?? undefined);
    const selected: DateRange = { from: dateOrUndefined(value.from), to: dateOrUndefined(value.to) };
    const dayFormatter = useMemo(() => fullDateFormatter(locale), [locale]);
    const shown = shownRange(value, locale);

    function handleSelect(next: DateRange) {
        onChange({ from: isoOrNull(next.from), to: isoOrNull(next.to) });

        if (next.from !== undefined && next.to !== undefined) {
            setOpen(false);
        }
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    id={id}
                    type="button"
                    aria-describedby={describedBy}
                    className={cn(FIELD_CLASS, CONTROL_DENSITY_CLASSES[density], className)}
                >
                    <CalendarRange aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />

                    <span className={cn('truncate tabular-nums', shown === '' && 'text-muted-foreground')}>
                        {shown === '' ? messages.placeholder : shown}
                    </span>
                </button>
            </PopoverTrigger>

            <PopoverContent
                aria-label={messages.calendar}
                align="start"
                sideOffset={6}
                collisionPadding={12}
                className="w-auto max-w-[calc(100vw-1.5rem)] gap-0 p-0 shadow-lg"
            >
                <Calendar
                    mode="range"
                    required
                    resetOnSelect
                    selected={selected}
                    onSelect={handleSelect}
                    defaultMonth={selected.to ?? selected.from ?? latest}
                    locale={calendarLocale(locale)}
                    endMonth={latest}
                    disabled={latest === undefined ? undefined : { after: latest }}
                    labels={{
                        labelPrevious: () => messages.previousMonth,
                        labelNext: () => messages.nextMonth,
                        labelDayButton: (date, modifiers) =>
                            modifiers.today
                                ? `${messages.today}, ${dayFormatter.format(date)}`
                                : dayFormatter.format(date),
                    }}
                    className="p-3 [--cell-size:min(--spacing(11),calc((100vw-3rem)/7))]"
                />
            </PopoverContent>
        </Popover>
    );
}
