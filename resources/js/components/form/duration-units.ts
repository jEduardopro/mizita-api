import type { NumberValue } from '@/components/form/NumberField';

export const DURATION_UNITS = ['minutes', 'hours', 'days', 'months'] as const;

export type DurationUnit = (typeof DURATION_UNITS)[number];

export type Duration = {
    amount: NumberValue;
    unit: DurationUnit;
};

const MINUTES_PER_HOUR = 60;

const HOURS_PER_DAY = 24;

const DAYS_PER_MONTH = 30;

const MINUTES_PER_UNIT: Record<DurationUnit, number> = {
    minutes: 1,
    hours: MINUTES_PER_HOUR,
    days: MINUTES_PER_HOUR * HOURS_PER_DAY,
    months: MINUTES_PER_HOUR * HOURS_PER_DAY * DAYS_PER_MONTH,
};

export function minutesFromDuration(duration: Duration): number {
    const amount = duration.amount === '' ? 0 : duration.amount;

    return amount * MINUTES_PER_UNIT[duration.unit];
}

function largestUnitDividing(minutes: number, units: readonly DurationUnit[]): DurationUnit | null {
    const ordered = [...units].sort((one, other) => MINUTES_PER_UNIT[other] - MINUTES_PER_UNIT[one]);

    return ordered.find((unit) => minutes % MINUTES_PER_UNIT[unit] === 0) ?? null;
}

export function durationFromMinutes(
    minutes: number,
    units: readonly DurationUnit[],
    fallbackUnit: DurationUnit,
): Duration {
    if (minutes === 0) {
        return { amount: 0, unit: fallbackUnit };
    }

    const unit = largestUnitDividing(minutes, units) ?? fallbackUnit;

    return { amount: Math.round(minutes / MINUTES_PER_UNIT[unit]), unit };
}
