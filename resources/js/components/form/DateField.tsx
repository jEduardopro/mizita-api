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
import { fieldMessage, FieldMessage, type HintTone } from '@/components/form/FieldMessage';
import { useDateField } from '@/components/form/use-date-field';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Label } from '@/components/ui/label';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';

type Messages = {
    calendar: string;
    previousMonth: string;
    nextMonth: string;
    month: string;
    year: string;
    today: string;
    clear: string;
};

type Props = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    format: DatePartLabels;
    messages: Messages;
    clearable?: boolean;
    min?: string;
    max?: string;
    error?: string;
    hint?: string;
    hintTone?: HintTone;
};

const CALENDAR_LOCALES: Record<string, Locale> = {
    en: enUS,
    es,
};

const FIELD_CLASS =
    'flex h-11 w-full items-center overflow-hidden rounded-lg border border-input bg-transparent px-2.5 text-left text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:bg-input/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40';

function calendarLocale(language: string): Locale {
    return CALENDAR_LOCALES[language.split('-')[0]] ?? enUS;
}

export function DateField({
    id,
    label,
    value,
    onChange,
    format,
    messages,
    clearable = false,
    min,
    max,
    error,
    hint,
    hintTone,
}: Props) {
    const { i18n } = useTranslation();
    const locale = i18n.language;

    const earliest = min === undefined ? null : dateFromIso(min);
    const latest = max === undefined ? null : dateFromIso(max);

    const field = useDateField({
        id,
        value,
        onChange,
        fallbackMonth: latest ?? new Date(),
    });

    const message = fieldMessage({ id, error, hint, hintTone });
    const dayFormatter = useMemo(() => fullDateFormatter(locale), [locale]);

    const labelId = `${id}-label`;
    const shownDate = formatIsoDate(value, locale);
    const showClear = clearable && shownDate !== '';

    const bounds = [
        latest === null ? null : { after: latest },
        earliest === null ? null : { before: earliest },
    ].filter((bound) => bound !== null);

    return (
        <div className="grid gap-2">
            <Label id={labelId} htmlFor={id}>
                {label}
            </Label>

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
                                aria-invalid={!! error}
                                aria-labelledby={`${labelId} ${id}`}
                                aria-describedby={message?.id}
                                onClick={field.toggleCalendar}
                                onKeyDown={field.onKeyDown}
                                className={cn(FIELD_CLASS, showClear ? 'pr-20' : 'pr-10')}
                            >
                                <span
                                    className={cn(
                                        'truncate',
                                        shownDate === '' ? 'text-muted-foreground' : undefined,
                                    )}
                                >
                                    {shownDate === ''
                                        ? datePlaceholder(locale, format)
                                        : shownDate}
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
                                        className="pointer-events-auto size-11 rounded-md text-muted-foreground hover:bg-transparent hover:text-foreground"
                                    >
                                        <X aria-hidden="true" />
                                    </Button>
                                ) : null}

                                <CalendarDays
                                    aria-hidden="true"
                                    className="size-4 text-muted-foreground"
                                />
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

            <FieldMessage message={message} />
        </div>
    );
}
