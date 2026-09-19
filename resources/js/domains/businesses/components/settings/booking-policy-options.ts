import type { DurationUnit } from '@/components/form/duration-units';

export const LEAD_TIME_UNITS = ['minutes', 'hours'] as const satisfies readonly DurationUnit[];

export const BOOKING_WINDOW_UNITS = ['days', 'months'] as const satisfies readonly DurationUnit[];

export const SLOT_SIZE_UNITS = ['minutes', 'hours'] as const satisfies readonly DurationUnit[];

export const DEFAULT_LEAD_TIME_UNIT: DurationUnit = 'hours';

export const DEFAULT_BOOKING_WINDOW_UNIT: DurationUnit = 'days';

export const DEFAULT_SLOT_SIZE_UNIT: DurationUnit = 'minutes';

export const DEFAULT_SLOT_SIZE_MINUTES = 30;

export const DURATION_UNIT_LABEL_KEYS = {
    minutes: 'businessSettings.policy.units.minutes',
    hours: 'businessSettings.policy.units.hours',
    days: 'businessSettings.policy.units.days',
    months: 'businessSettings.policy.units.months',
} as const satisfies Record<DurationUnit, string>;

export const CANCELLATION_WINDOWS = [
    'none',
    'h1',
    'h2',
    'h4',
    'h12',
    'h24',
    'h48',
    'never',
] as const;

export type CancellationWindow = (typeof CANCELLATION_WINDOWS)[number];

export const DEFAULT_CANCELLATION_WINDOW: CancellationWindow = 'none';

const CANCELLATION_WINDOW_MINUTES = {
    none: 0,
    h1: 60,
    h2: 120,
    h4: 240,
    h12: 720,
    h24: 1440,
    h48: 2880,
    never: null,
} as const satisfies Record<CancellationWindow, number | null>;

export const CANCELLATION_WINDOW_LABEL_KEYS = {
    none: 'businessSettings.policy.cancellation.options.none',
    h1: 'businessSettings.policy.cancellation.options.h1',
    h2: 'businessSettings.policy.cancellation.options.h2',
    h4: 'businessSettings.policy.cancellation.options.h4',
    h12: 'businessSettings.policy.cancellation.options.h12',
    h24: 'businessSettings.policy.cancellation.options.h24',
    h48: 'businessSettings.policy.cancellation.options.h48',
    never: 'businessSettings.policy.cancellation.options.never',
} as const satisfies Record<CancellationWindow, string>;

export function cancellationWindowMinutesFrom(choice: CancellationWindow): number | null {
    return CANCELLATION_WINDOW_MINUTES[choice];
}

export function cancellationWindowFrom(minutes: number | null): CancellationWindow {
    return (
        CANCELLATION_WINDOWS.find((choice) => CANCELLATION_WINDOW_MINUTES[choice] === minutes) ??
        DEFAULT_CANCELLATION_WINDOW
    );
}
