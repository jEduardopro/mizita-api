import { cn } from 'cn';
import { enUS } from 'date-fns/locale/en-US';
import { es } from 'date-fns/locale/es';
import { CalendarDays, X } from 'lucide-react';
import { useMemo } from 'react';
import type { Locale } from 'react-day-picker';
import { useTranslation } from 'react-i18next';
import {
    datePlaceholder,
    dateFromIso,
    formatIsoDate,
    fullDateFormatter,
    type DatePartLabels,
} from '@/components/form/date-format';
import {
    CONTROL_DENSITY_CLASSES,
    useFormDensity,
    type FormDensity,
} from '@/components/form/form-density';
import { useDateField } from '@/components/form/use-date-field';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';

export type DatePickerMessages = {
    calendar: string;
    previousMonth: string;
    nextMonth: string;
    month: string;
    year: string;
    today: string;
    clear: string;
};

type Naming = { labelledBy: string; label?: never } | { label: string; labelledBy?: never };

type Props = Naming & {
    id: string;
    value: string;
    onChange: (value: string) => void;
    format: DatePartLabels;
    messages: DatePickerMessages;
    clearable?: boolean;
    min?: string;
    max?: string;
    invalid?: boolean;
    describedBy?: string;
    className?: string;
};

const CALENDAR_LOCALES: Record<string, Locale> = {
    en: enUS,
    es,
};

const FIELD_CLASS =
    'flex w-full items-center overflow-hidden rounded-lg border border-input bg-transparent px-2.5 text-left transition-colors outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:bg-input/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40';

const CLEAR_DENSITY_CLASSES: Record<FormDensity, string> = {
    comfortable: 'size-11',
    compact: 'size-9',
};

function calendarLocale(language: string): Locale {
    return CALENDAR_LOCALES[language.split('-')[0]] ?? enUS;
}

export function DatePicker({
    id,
    value,
    onChange,
    format,
    messages,
    clearable = false,
    min,
    max,
    invalid = false,
    describedBy,
    label,
    labelledBy,
    className,
}: Props) {
    const { i18n } = useTranslation();
    const locale = i18n.language;
    const density = useFormDensity();

    const earliest = min === undefined ? null : dateFromIso(min);
    const latest = max === undefined ? null : dateFromIso(max);

    const field = useDateField({
        id,
        value,
        onChange,
        fallbackMonth: latest ?? new Date(),
    });

    const dayFormatter = useMemo(() => fullDateFormatter(locale), [locale]);

    const shownDate = formatIsoDate(value, locale);
    const showClear = clearable && shownDate !== '';

    const bounds = [
        latest === null ? null : { after: latest },
        earliest === null ? null : { before: earliest },
    ].filter((bound) => bound !== null);

    return (
        <div ref={field.rootRef} onBlur={field.onBlur}>
            <Popover open={field.open} onOpenChange={field.onOpenChange}>
                <PopoverAnchor asChild>
                    <div className="relative">
                        <button
                            ref={field.fieldRef}
                            id={id}
                            type="button"
                            aria-haspopup="dialog"
                            aria-expanded={field.open}
                            aria-controls={field.calendarId}
                            aria-invalid={invalid}
                            aria-label={label}
                            aria-labelledby={labelledBy}
                            aria-describedby={describedBy}
                            onClick={field.toggleCalendar}
                            onKeyDown={field.onKeyDown}
                            className={cn(
                                FIELD_CLASS,
                                CONTROL_DENSITY_CLASSES[density],
                                showClear ? 'pr-20' : 'pr-10',
                                className,
                            )}
                        >
                            <span
                                className={cn(
                                    'truncate',
                                    shownDate === '' ? 'text-muted-foreground' : undefined,
                                )}
                            >
                                {shownDate === '' ? datePlaceholder(locale, format) : shownDate}
                            </span>
                        </button>

                        <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center gap-1">
                            {showClear ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={messages.clear}
                                    onClick={field.clear}
                                    className={cn(
                                        'pointer-events-auto rounded-md text-muted-foreground hover:bg-transparent hover:text-foreground',
                                        CLEAR_DENSITY_CLASSES[density],
                                    )}
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            ) : null}

                            <CalendarDays aria-hidden="true" className="size-4 text-muted-foreground" />
                        </div>
                    </div>
                </PopoverAnchor>

                <PopoverContent
                    ref={field.calendarRef}
                    id={field.calendarId}
                    aria-label={messages.calendar}
                    align="start"
                    sideOffset={6}
                    collisionPadding={12}
                    onOpenAutoFocus={(event) => {
                        event.preventDefault();
                        field.onCalendarMount();
                    }}
                    onEscapeKeyDown={field.returnFocus}
                    onInteractOutside={field.onInteractOutside}
                    className="w-auto max-w-[calc(100vw-1.5rem)] gap-0 p-0 shadow-lg"
                >
                    <Calendar
                        mode="single"
                        selected={field.selected}
                        onSelect={field.selectDate}
                        month={field.month}
                        onMonthChange={field.showMonth}
                        captionLayout="dropdown"
                        locale={calendarLocale(locale)}
                        startMonth={earliest ?? undefined}
                        endMonth={latest ?? undefined}
                        disabled={bounds}
                        labels={{
                            labelPrevious: () => messages.previousMonth,
                            labelNext: () => messages.nextMonth,
                            labelMonthDropdown: () => messages.month,
                            labelYearDropdown: () => messages.year,
                            labelDayButton: (date, modifiers) =>
                                modifiers.today
                                    ? `${messages.today}, ${dayFormatter.format(date)}`
                                    : dayFormatter.format(date),
                        }}
                        className="p-3 [--cell-size:min(--spacing(11),calc((100vw-3rem)/7))]"
                    />
                </PopoverContent>
            </Popover>
        </div>
    );
}
