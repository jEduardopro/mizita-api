import type { WeekdayNumber } from '@/lib/booking-brand';

export type ScheduleRule = {
    weekday: WeekdayNumber;
    starts_at: string;
    ends_at: string;
};

export type MySchedule = {
    inherited: boolean;
    schedule: ScheduleRule[];
};

export type ReplaceSchedulePayload = {
    schedule: ScheduleRule[];
};
