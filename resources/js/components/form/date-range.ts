import { startOfMonth, subDays } from 'date-fns';
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
};

const LAST_WEEK_LENGTH_IN_DAYS = 7;

const LAST_MONTH_LENGTH_IN_DAYS = 30;

function singleDay(day: Date): DateSpan {
    return { from: day, to: day };
}

function trailingDays(length: number): DateRangePreset['range'] {
    return (today) => ({ from: subDays(today, length - 1), to: today });
}

export const DEFAULT_DATE_RANGE_PRESETS: readonly DateRangePreset[] = [
    { id: 'today', labelKey: 'today', range: singleDay },
    { id: 'yesterday', labelKey: 'yesterday', range: (today) => singleDay(subDays(today, 1)) },
    { id: 'last-7-days', labelKey: 'last7Days', range: trailingDays(LAST_WEEK_LENGTH_IN_DAYS) },
    { id: 'last-30-days', labelKey: 'last30Days', range: trailingDays(LAST_MONTH_LENGTH_IN_DAYS) },
    { id: 'this-month', labelKey: 'thisMonth', range: (today) => ({ from: startOfMonth(today), to: today }) },
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

function rangeWithin(span: DateSpan, max: string | undefined): DateRangeValue | null {
    const from = isoFromDate(span.from);
    const to = isoFromDate(span.to);

    if (max === undefined) {
        return { from, to };
    }

    if (from > max) {
        return null;
    }

    return { from, to: to > max ? max : to };
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

    return presets.flatMap((preset) => {
        const range = rangeWithin(preset.range(anchor), max);

        return range === null ? [] : [{ id: preset.id, labelKey: preset.labelKey, range }];
    });
}
