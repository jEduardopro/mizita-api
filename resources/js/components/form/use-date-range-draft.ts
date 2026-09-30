import { addMonths, startOfMonth } from 'date-fns';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { dateFromIso, isoFromDate } from '@/components/form/date-format';
import {
    draftOfRange,
    isSameRange,
    rangeOfDraft,
    type DateRangeDraft,
    type DateRangeValue,
} from '@/components/form/date-range';

type Params = {
    value: DateRangeValue | null;
    onApply: (range: DateRangeValue) => void;
    fallbackMonth: string | undefined;
    visibleMonths: number;
};

type DateRangeDraftState = {
    open: boolean;
    draftRange: DateRangeValue | null;
    selected: DateRange;
    month: Date;
    canApply: boolean;
    onOpenChange: (next: boolean) => void;
    showMonth: (month: Date) => void;
    selectDays: (days: DateRange) => void;
    applyPreset: (range: DateRangeValue) => void;
    apply: () => void;
    cancel: () => void;
};

function dateOrUndefined(value: string | null): Date | undefined {
    return value === null ? undefined : (dateFromIso(value) ?? undefined);
}

function isoOrNull(date: Date | undefined): string | null {
    return date === undefined ? null : isoFromDate(date);
}

function firstVisibleMonth(anchor: string | undefined, visibleMonths: number): Date {
    const anchorDate = (anchor === undefined ? null : dateFromIso(anchor)) ?? new Date();

    return startOfMonth(addMonths(anchorDate, 1 - visibleMonths));
}

export function useDateRangeDraft({ value, onApply, fallbackMonth, visibleMonths }: Params): DateRangeDraftState {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<DateRangeDraft>(() => draftOfRange(value));
    const [month, setMonth] = useState(() => firstVisibleMonth(value?.to ?? fallbackMonth, visibleMonths));

    const chosenRange = rangeOfDraft(draft);

    function reveal(anchor: string | undefined) {
        setMonth(firstVisibleMonth(anchor ?? fallbackMonth, visibleMonths));
    }

    function onOpenChange(next: boolean) {
        if (next) {
            setDraft(draftOfRange(value));
            reveal(value?.to);
        }

        setOpen(next);
    }

    function selectDays(days: DateRange) {
        setDraft({ from: isoOrNull(days.from), to: isoOrNull(days.to) });
    }

    function applyPreset(range: DateRangeValue) {
        setDraft(range);

        if (! isSameRange(range, value)) {
            onApply(range);
        }

        setOpen(false);
    }

    function apply() {
        if (chosenRange === null) {
            return;
        }

        onApply(chosenRange);
        setOpen(false);
    }

    return {
        open,
        draftRange: chosenRange,
        selected: { from: dateOrUndefined(draft.from), to: dateOrUndefined(draft.to) },
        month,
        canApply: chosenRange !== null && ! isSameRange(chosenRange, value),
        onOpenChange,
        showMonth: setMonth,
        selectDays,
        applyPreset,
        apply,
        cancel: () => setOpen(false),
    };
}
