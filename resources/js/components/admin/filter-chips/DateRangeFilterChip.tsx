import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { DateRangeCalendar, type DateRangeCalendarMessages } from '@/components/form/DateRangeCalendar';
import { DateRangePresetList } from '@/components/form/DateRangePresetList';
import { dateFromIso } from '@/components/form/date-format';
import {
    DEFAULT_DATE_RANGE_PRESETS,
    resolvePresets,
    type DateRangePreset,
    type DateRangePresetLabelKey,
    type DateRangeValue,
} from '@/components/form/date-range';
import { useDateRangeDraft } from '@/components/form/use-date-range-draft';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { FilterChip } from './FilterChip';
import { FilterChipApply } from './FilterChipApply';
import { dateRangeLabelFormatter } from './date-range-label';

export type DateRangeFilterChipMessages = DateRangeCalendarMessages & {
    apply: string;
    clear: string;
    presetsLabel: string;
    presets: Record<DateRangePresetLabelKey, string>;
};

export type DateRangeFilterChipProps = {
    label: string;
    value: DateRangeValue | null;
    onApply: (range: DateRangeValue) => void;
    onClear: () => void;
    today: string;
    max?: string;
    presets?: readonly DateRangePreset[];
    messages: DateRangeFilterChipMessages;
    className?: string;
};

const DESKTOP_VISIBLE_MONTHS = 2;

const PHONE_VISIBLE_MONTHS = 1;

function dateOrUndefined(value: string | undefined): Date | undefined {
    return value === undefined ? undefined : (dateFromIso(value) ?? undefined);
}

export function DateRangeFilterChip({
    label,
    value,
    onApply,
    onClear,
    today,
    max,
    presets = DEFAULT_DATE_RANGE_PRESETS,
    messages,
    className,
}: DateRangeFilterChipProps) {
    const { i18n } = useTranslation();
    const visibleMonths = useIsDesktop() ? DESKTOP_VISIBLE_MONTHS : PHONE_VISIBLE_MONTHS;

    const picker = useDateRangeDraft({ value, onApply, fallbackMonth: today, visibleMonths });
    const resolvedPresets = useMemo(() => resolvePresets(presets, today, max), [presets, today, max]);
    const formatRange = useMemo(() => dateRangeLabelFormatter(i18n.language), [i18n.language]);

    function selectPreset(range: DateRangeValue) {
        picker.selectDays({ from: dateOrUndefined(range.from), to: dateOrUndefined(range.to) });
    }

    return (
        <FilterChip
            label={label}
            valueLabel={value === null ? null : formatRange(value)}
            open={picker.open}
            onOpenChange={picker.onOpenChange}
            onClear={onClear}
            messages={messages}
            className={className}
            contentClassName="w-auto max-w-[calc(100vw-1.5rem)] overflow-y-auto overscroll-contain"
        >
            <div className="flex flex-col md:flex-row">
                {resolvedPresets.length > 0 ? (
                    <DateRangePresetList
                        label={messages.presetsLabel}
                        presets={resolvedPresets}
                        presetLabels={messages.presets}
                        draftRange={picker.draftRange}
                        onSelect={selectPreset}
                        className="shrink-0"
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
                    messages={messages}
                />
            </div>

            <FilterChipApply disabled={! picker.canApply} onClick={picker.apply}>
                {messages.apply}
            </FilterChipApply>
        </FilterChip>
    );
}
