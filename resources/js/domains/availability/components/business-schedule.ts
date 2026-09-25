import type { MySchedule, ScheduleRule } from '../types';

export function businessScheduleFor(
    schedule: MySchedule | undefined,
    configuredBusinessSchedule: readonly ScheduleRule[] | undefined,
): readonly ScheduleRule[] | null {
    if (schedule?.inherited) {
        return schedule.schedule;
    }

    return configuredBusinessSchedule ?? null;
}
