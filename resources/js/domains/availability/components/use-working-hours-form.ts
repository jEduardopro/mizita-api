import { useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import type { WeekdayNumber } from '@/lib/booking-brand';
import { raiseSuccessToast } from '@/lib/toast';
import type { MySchedule, ReplaceSchedulePayload, ScheduleRule } from '../types';
import {
    sameWorkingWeek,
    scheduleFrom,
    seedIntervalFor,
    withIntervalOnActiveDays,
    workingWeekFrom,
    type WorkingInterval,
    type WorkingWeek,
} from './working-week';

export type WorkingHoursFormController = {
    week: WorkingWeek;
    toggleDay: (weekday: WeekdayNumber, enabled: boolean) => void;
    changeDay: (weekday: WeekdayNumber, interval: WorkingInterval) => void;
    copyToActiveDays: (weekday: WeekdayNumber) => void;
    isDirty: boolean;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
    discard: () => void;
};

type Params = {
    schedule: MySchedule;
    businessSchedule: readonly ScheduleRule[] | null;
    onSave: (payload: ReplaceSchedulePayload) => Promise<unknown>;
};

export function useWorkingHoursForm({
    schedule,
    businessSchedule,
    onSave,
}: Params): WorkingHoursFormController {
    const { t } = useTranslation('admin');
    const { capture, reset } = useServerErrors();

    const baseline = useMemo(() => workingWeekFrom(schedule.schedule), [schedule]);
    const [draft, setDraft] = useState<WorkingWeek | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const week = draft ?? baseline;

    function edit(change: (current: WorkingWeek) => WorkingWeek) {
        setDraft((current) => change(current ?? baseline));
    }

    function toggleDay(weekday: WeekdayNumber, enabled: boolean) {
        edit((current) => {
            const day = current[weekday];
            const interval =
                day.starts_at === '' ? seedIntervalFor(weekday, businessSchedule) : day;

            return {
                ...current,
                [weekday]: { enabled, starts_at: interval.starts_at, ends_at: interval.ends_at },
            };
        });
    }

    function changeDay(weekday: WeekdayNumber, interval: WorkingInterval) {
        edit((current) => ({ ...current, [weekday]: { enabled: true, ...interval } }));
    }

    function copyToActiveDays(weekday: WeekdayNumber) {
        edit((current) => withIntervalOnActiveDays(current, current[weekday]));
    }

    function discard() {
        reset();
        setDraft(null);
    }

    async function save() {
        reset();
        setIsSubmitting(true);

        try {
            await onSave({ schedule: scheduleFrom(week) });
            setDraft(null);
            raiseSuccessToast(t('workingHours.saved'));
        } catch (error) {
            capture(error, t('workingHours.unexpected'));
        } finally {
            setIsSubmitting(false);
        }
    }

    return {
        week,
        toggleDay,
        changeDay,
        copyToActiveDays,
        isDirty: draft !== null && ! sameWorkingWeek(draft, baseline),
        isSubmitting,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void save();
        },
        discard,
    };
}
