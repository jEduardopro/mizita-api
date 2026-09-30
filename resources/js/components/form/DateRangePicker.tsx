import { cn } from 'cn';
import { enUS } from 'date-fns/locale/en-US';
import { es } from 'date-fns/locale/es';
import { CalendarRange, X } from 'lucide-react';
import { useId, useMemo, useRef } from 'react';
import type { DateRange, Locale } from 'react-day-picker';
import { useTranslation } from 'react-i18next';
import { dateFromIso, formatIsoDate, fullDateFormatter } from '@/components/form/date-format';
import {
    DEFAULT_DATE_RANGE_PRESETS,
    isSameRange,
    resolvePresets,
    type DateRangePreset,
    type DateRangeValue,
    type ResolvedDateRangePreset,
} from '@/components/form/date-range';
import { CONTROL_DENSITY_CLASSES, useFormDensity } from '@/components/form/form-density';
import { useDateRangeDraft } from '@/components/form/use-date-range-draft';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Label } from '@/components/ui/label';
import { Popover, PopoverAnchor, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useIsDesktop } from '@/hooks/use-is-desktop';

export type DateRangeLabelDisplay = 'visible' | 'hidden';

type Props = {
    label: string;
    placeholder: string;
    value: DateRangeValue | null;
    onApply: (range: DateRangeValue) => void;
    onClear?: () => void;
    today?: string;
    max?: string;
    presets?: readonly DateRangePreset[];
    labelDisplay?: DateRangeLabelDisplay;
    className?: string;
    fieldClassName?: string;
};

const CALENDAR_LOCALES: Record<string, Locale> = {
    en: enUS,
    es,
};

const DESKTOP_VISIBLE_MONTHS = 2;

const PHONE_VISIBLE_MONTHS = 1;

const RANGE_SEPARATOR = ' – ';

const LABEL_DISPLAY_CLASSES: Record<DateRangeLabelDisplay, string> = {
    visible: '',
    hidden: 'sr-only',
};

const FIELD_CLASS =
    'flex w-full items-center overflow-hidden rounded-lg border border-input bg-transparent transition-colors has-[:focus-visible]:border-ring has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring/50 dark:bg-input/30';

const TRIGGER_CLASS = 'flex h-full min-w-0 flex-1 items-center gap-2 px-2.5 text-left outline-none';

const CLEAR_CLASS =
    'flex aspect-square h-full shrink-0 items-center justify-center text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:bg-muted focus-visible:text-foreground';

const PRESET_LIST_CLASS =
    'flex gap-2 overflow-x-auto border-b border-border p-3 [contain:inline-size] [scrollbar-width:none] md:w-40 md:shrink-0 md:flex-col md:gap-0.5 md:overflow-visible md:border-r md:border-b-0 md:[contain:none] [&::-webkit-scrollbar]:hidden';

const PRESET_CLASS =
    'flex min-h-11 w-full items-center rounded-full border border-border px-4 text-left text-sm whitespace-nowrap text-foreground/80 transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 aria-pressed:border-transparent aria-pressed:bg-muted aria-pressed:font-medium aria-pressed:text-foreground md:min-h-9 md:rounded-md md:border-transparent md:px-3';

const ACTION_CLASS = 'h-11 flex-1 px-4 md:h-9 md:flex-none';

function calendarLocale(language: string): Locale {
    return CALENDAR_LOCALES[language.split('-')[0]] ?? enUS;
}

function dateOrUndefined(value: string | undefined): Date | undefined {
    return value === undefined ? undefined : (dateFromIso(value) ?? undefined);
}

function shownRange(value: DateRangeValue | null, locale: string): string {
    if (value === null) {
        return '';
    }

    const from = formatIsoDate(value.from, locale);

    if (value.to === value.from) {
        return from;
    }

    return `${from}${RANGE_SEPARATOR}${formatIsoDate(value.to, locale)}`;
}

type PresetListProps = {
    presets: readonly ResolvedDateRangePreset[];
    draftRange: DateRangeValue | null;
    onSelect: (range: DateRangeValue) => void;
};

function DateRangePresetList({ presets, draftRange, onSelect }: PresetListProps) {
    const { t } = useTranslation('common');

    return (
        <ul aria-label={t('dateRange.presetsLabel')} className={PRESET_LIST_CLASS}>
            {presets.map((preset) => (
                <li key={preset.id} className="shrink-0">
                    <button
                        type="button"
                        aria-pressed={isSameRange(preset.range, draftRange)}
                        onClick={() => onSelect(preset.range)}
                        className={PRESET_CLASS}
                    >
                        {t(`dateRange.presets.${preset.labelKey}`)}
                    </button>
                </li>
            ))}
        </ul>
    );
}

type CalendarProps = {
    selected: DateRange;
    onSelect: (days: DateRange) => void;
    month: Date;
    onMonthChange: (month: Date) => void;
    visibleMonths: number;
    today?: Date;
    latest?: Date;
};

function DateRangeCalendar({ selected, onSelect, month, onMonthChange, visibleMonths, today, latest }: CalendarProps) {
    const { t, i18n } = useTranslation('common');
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
                labelPrevious: () => t('dateRange.previousMonth'),
                labelNext: () => t('dateRange.nextMonth'),
                labelDayButton: (date, modifiers) =>
                    modifiers.today
                        ? `${t('dateRange.today')}, ${dayFormatter.format(date)}`
                        : dayFormatter.format(date),
            }}
            className="mx-auto p-3 [--cell-size:min(--spacing(11),calc((100vw-3rem)/7))] md:[--cell-size:--spacing(9)]"
        />
    );
}

type ActionsProps = {
    canApply: boolean;
    onCancel: () => void;
    onApply: () => void;
};

function DateRangeActions({ canApply, onCancel, onApply }: ActionsProps) {
    const { t } = useTranslation('common');

    return (
        <div className="flex gap-2 border-t border-border p-3 md:justify-end">
            <Button type="button" variant="outline" onClick={onCancel} className={ACTION_CLASS}>
                {t('actions.cancel')}
            </Button>

            <Button type="button" variant="brand" disabled={! canApply} onClick={onApply} className={ACTION_CLASS}>
                {t('dateRange.apply')}
            </Button>
        </div>
    );
}

export function DateRangePicker({
    label,
    placeholder,
    value,
    onApply,
    onClear,
    today,
    max,
    presets = DEFAULT_DATE_RANGE_PRESETS,
    labelDisplay = 'visible',
    className,
    fieldClassName,
}: Props) {
    const { t, i18n } = useTranslation('common');
    const density = useFormDensity();
    const visibleMonths = useIsDesktop() ? DESKTOP_VISIBLE_MONTHS : PHONE_VISIBLE_MONTHS;
    const triggerRef = useRef<HTMLButtonElement>(null);
    const triggerId = useId();
    const labelId = useId();
    const shownId = useId();

    const picker = useDateRangeDraft({ value, onApply, fallbackMonth: today ?? max, visibleMonths });
    const shown = shownRange(value, i18n.language);
    const resolvedPresets = useMemo(() => resolvePresets(presets, today, max), [presets, today, max]);
    const canClear = onClear !== undefined && value !== null;

    function handleClear() {
        onClear?.();
        triggerRef.current?.focus();
    }

    return (
        <div className={cn('grid min-w-0 gap-1.5', className)}>
            <Label id={labelId} htmlFor={triggerId} className={LABEL_DISPLAY_CLASSES[labelDisplay]}>
                {label}
            </Label>

            <Popover open={picker.open} onOpenChange={picker.onOpenChange}>
                <PopoverAnchor asChild>
                    <div className={cn(FIELD_CLASS, CONTROL_DENSITY_CLASSES[density], fieldClassName)}>
                        <PopoverTrigger asChild>
                            <button
                                ref={triggerRef}
                                id={triggerId}
                                type="button"
                                aria-labelledby={`${labelId} ${shownId}`}
                                className={TRIGGER_CLASS}
                            >
                                <CalendarRange aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />

                                <span
                                    id={shownId}
                                    className={cn('truncate tabular-nums', shown === '' && 'text-muted-foreground')}
                                >
                                    {shown === '' ? placeholder : shown}
                                </span>
                            </button>
                        </PopoverTrigger>

                        {canClear ? (
                            <button
                                type="button"
                                onClick={handleClear}
                                aria-label={t('dateRange.clear')}
                                className={CLEAR_CLASS}
                            >
                                <X aria-hidden="true" className="size-4" />
                            </button>
                        ) : null}
                    </div>
                </PopoverAnchor>

                <PopoverContent
                    aria-label={t('dateRange.calendar')}
                    align="start"
                    sideOffset={6}
                    collisionPadding={12}
                    className="w-auto max-w-[calc(100vw-1.5rem)] gap-0 p-0 shadow-lg"
                >
                    <div className="flex flex-col md:flex-row">
                        {resolvedPresets.length > 0 ? (
                            <DateRangePresetList
                                presets={resolvedPresets}
                                draftRange={picker.draftRange}
                                onSelect={picker.applyPreset}
                            />
                        ) : null}

                        <DateRangeCalendar
                            selected={picker.selected}
                            onSelect={picker.selectDays}
                            month={picker.month}
                            onMonthChange={picker.showMonth}
                            visibleMonths={visibleMonths}
                            today={dateOrUndefined(today)}
                            latest={dateOrUndefined(max)}
                        />
                    </div>

                    <DateRangeActions canApply={picker.canApply} onCancel={picker.cancel} onApply={picker.apply} />
                </PopoverContent>
            </Popover>
        </div>
    );
}
