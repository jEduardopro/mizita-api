import { useId, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { DateRangePicker, type DateRangeDraft } from '@/components/form/DateRangePicker';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { StatisticsRange } from '../types';

type Props = {
    value: StatisticsRange | null;
    latest: string;
    onApply: (range: StatisticsRange) => void;
};

const EMPTY_DRAFT: DateRangeDraft = { from: null, to: null };

function draftOf(range: StatisticsRange | null): DateRangeDraft {
    return range ?? EMPTY_DRAFT;
}

function rangeOf(draft: DateRangeDraft): StatisticsRange | null {
    if (draft.from === null) {
        return null;
    }

    return { from: draft.from, to: draft.to ?? draft.from };
}

function isSameRange(left: StatisticsRange | null, right: StatisticsRange | null): boolean {
    return left?.from === right?.from && left?.to === right?.to;
}

export function StatisticsRangeForm({ value, latest, onApply }: Props) {
    const { t } = useTranslation('admin');
    const pickerId = useId();

    const [draft, setDraft] = useState<DateRangeDraft>(() => draftOf(value));
    const [syncedValue, setSyncedValue] = useState(value);

    if (! isSameRange(value, syncedValue)) {
        setSyncedValue(value);
        setDraft(draftOf(value));
    }

    const chosenRange = rangeOf(draft);
    const canApply = chosenRange !== null && ! isSameRange(chosenRange, value);

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (chosenRange !== null) {
            onApply(chosenRange);
        }
    }

    return (
        <form onSubmit={handleSubmit} className="grid gap-2 sm:flex sm:items-end">
            <div className="grid min-w-0 gap-1.5 sm:w-80">
                <Label htmlFor={pickerId}>{t('statistics.range.label')}</Label>

                <DateRangePicker
                    id={pickerId}
                    value={draft}
                    onChange={setDraft}
                    max={latest}
                    messages={{
                        calendar: t('statistics.range.picker.calendar'),
                        placeholder: t('statistics.range.placeholder'),
                        previousMonth: t('statistics.range.picker.previousMonth'),
                        nextMonth: t('statistics.range.picker.nextMonth'),
                        today: t('statistics.range.picker.today'),
                    }}
                />
            </div>

            <Button type="submit" variant="brand" disabled={! canApply} className="h-11 px-5">
                {t('statistics.range.apply')}
            </Button>
        </form>
    );
}
