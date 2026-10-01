import { addDays, endOfMonth, startOfMonth, subDays, subMonths } from 'date-fns';
import { dateFromIso, isoFromDate } from '@/components/form/date-format';
import type common from '@/locales/en/common.json';

export type DateRangeValue = {
    from: string;
    to: string;
};

export type DateRangeDraft = {
    from: string | null;
    to: string | null;
};

export type DateSpan = {
    from: Date;
    to: Date;
};

export type DateRangePresetLabelKey = keyof (typeof common)['dateRange']['presets'];

export type DateRangePreset = {
    id: string;
    labelKey: DateRangePresetLabelKey;
    range: (today: Date) => DateSpan;
};

export type ResolvedDateRangePreset = {
    id: string;
    labelKey: DateRangePresetLabelKey;
    range: DateRangeValue;
    disabled: boolean;
};

const LAST_WEEK_LENGTH_IN_DAYS = 7;

const LAST_FORTNIGHT_LENGTH_IN_DAYS = 15;

const LAST_QUARTER_LENGTH_IN_MONTHS = 3;

function singleDay(day: Date): DateSpan {
    return { from: day, to: day };
}

function trailingDays(length: number): DateRangePreset['range'] {
    return (today) => ({ from: subDays(today, length - 1), to: today });
}

function trailingMonths(length: number): DateRangePreset['range'] {
    return (today) => ({ from: addDays(subMonths(today, length), 1), to: today });
}

function wholeMonth(today: Date): DateSpan {
    return { from: startOfMonth(today), to: endOfMonth(today) };
}

export const DEFAULT_DATE_RANGE_PRESETS: readonly DateRangePreset[] = [
    { id: 'today', labelKey: 'today', range: singleDay },
    { id: 'yesterday', labelKey: 'yesterday', range: (today) => singleDay(subDays(today, 1)) },
    { id: 'last-7-days', labelKey: 'last7Days', range: trailingDays(LAST_WEEK_LENGTH_IN_DAYS) },
    { id: 'last-15-days', labelKey: 'last15Days', range: trailingDays(LAST_FORTNIGHT_LENGTH_IN_DAYS) },
    { id: 'this-month', labelKey: 'thisMonth', range: wholeMonth },
    { id: 'last-3-months', labelKey: 'last3Months', range: trailingMonths(LAST_QUARTER_LENGTH_IN_MONTHS) },
];

export function draftOfRange(range: DateRangeValue | null): DateRangeDraft {
    return range ?? { from: null, to: null };
}

export function rangeOfDraft(draft: DateRangeDraft): DateRangeValue | null {
    if (draft.from === null) {
        return null;
    }

    return { from: draft.from, to: draft.to ?? draft.from };
}

export function isSameRange(left: DateRangeValue | null, right: DateRangeValue | null): boolean {
    return left?.from === right?.from && left?.to === right?.to;
}

function rangeOfSpan(span: DateSpan): DateRangeValue {
    return { from: isoFromDate(span.from), to: isoFromDate(span.to) };
}

function endsAfter(range: DateRangeValue, max: string | undefined): boolean {
    return max !== undefined && range.to > max;
}

export function resolvePresets(
    presets: readonly DateRangePreset[],
    today: string | undefined,
    max: string | undefined,
): ResolvedDateRangePreset[] {
    const anchor = today === undefined ? null : dateFromIso(today);

    if (anchor === null) {
        return [];
    }

    return presets.map((preset) => {
        const range = rangeOfSpan(preset.range(anchor));

        return { id: preset.id, labelKey: preset.labelKey, range, disabled: endsAfter(range, max) };
    });
}
