import { WEEKDAYS, type WeekdayNumber } from '@/lib/booking-brand';
import type { ScheduleRule } from '../types';

export type WorkingInterval = {
    starts_at: string;
    ends_at: string;
};

export type WorkingDay = WorkingInterval & {
    enabled: boolean;
};

export type WorkingWeek = Record<WeekdayNumber, WorkingDay>;

export const DEFAULT_WORKING_INTERVAL: WorkingInterval = { starts_at: '09:00', ends_at: '17:00' };

const DAY_OFF: WorkingDay = { enabled: false, starts_at: '', ends_at: '' };

export function weekOf(build: (weekday: WeekdayNumber) => WorkingDay): WorkingWeek {
    return {
        1: build(1),
        2: build(2),
        3: build(3),
        4: build(4),
        5: build(5),
        6: build(6),
        7: build(7),
    };
}

export function rulesOn(schedule: readonly ScheduleRule[], weekday: WeekdayNumber): ScheduleRule[] {
    return schedule
        .filter((rule) => rule.weekday === weekday)
        .sort((first, second) => first.starts_at.localeCompare(second.starts_at));
}

function spanOf(rules: readonly ScheduleRule[]): WorkingInterval | null {
    if (rules.length === 0) {
        return null;
    }

    const latestEnd = rules.reduce(
        (latest, rule) => (rule.ends_at > latest ? rule.ends_at : latest),
        rules[0].ends_at,
    );

    return { starts_at: rules[0].starts_at, ends_at: latestEnd };
}

export function workingWeekFrom(schedule: readonly ScheduleRule[]): WorkingWeek {
    return weekOf((weekday) => {
        const span = spanOf(rulesOn(schedule, weekday));

        return span === null ? { ...DAY_OFF } : { enabled: true, ...span };
    });
}

export function seedIntervalFor(
    weekday: WeekdayNumber,
    businessSchedule: readonly ScheduleRule[] | null,
): WorkingInterval {
    const businessSpan = businessSchedule === null ? null : spanOf(rulesOn(businessSchedule, weekday));

    return businessSpan ?? { ...DEFAULT_WORKING_INTERVAL };
}

export function scheduleFrom(week: WorkingWeek): ScheduleRule[] {
    return WEEKDAYS.filter((weekday) => week[weekday].enabled).map((weekday) => ({
        weekday,
        starts_at: week[weekday].starts_at,
        ends_at: week[weekday].ends_at,
    }));
}

function sameDay(first: WorkingDay, second: WorkingDay): boolean {
    if (first.enabled !== second.enabled) {
        return false;
    }

    return ! first.enabled || (first.starts_at === second.starts_at && first.ends_at === second.ends_at);
}

export function sameWorkingWeek(first: WorkingWeek, second: WorkingWeek): boolean {
    return WEEKDAYS.every((weekday) => sameDay(first[weekday], second[weekday]));
}

export function withIntervalOnActiveDays(week: WorkingWeek, interval: WorkingInterval): WorkingWeek {
    return weekOf((weekday) =>
        week[weekday].enabled ? { enabled: true, ...interval } : { ...week[weekday] },
    );
}
