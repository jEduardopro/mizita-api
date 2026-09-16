import type { WeekdayNumber } from '@/lib/booking-brand';

export const WEEKDAY_LABEL_KEYS = {
    1: 'weekdays.monday',
    2: 'weekdays.tuesday',
    3: 'weekdays.wednesday',
    4: 'weekdays.thursday',
    5: 'weekdays.friday',
    6: 'weekdays.saturday',
    7: 'weekdays.sunday',
} as const satisfies Record<WeekdayNumber, string>;
