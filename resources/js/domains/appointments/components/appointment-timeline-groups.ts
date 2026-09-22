import { isoDateIn } from '@/lib/timezone';
import type { Appointment } from '../types';

const UTC_TIMEZONE = 'UTC';

const ISO_YEAR_LENGTH = 'YYYY'.length;

const NOT_FOUND = -1;

export type AppointmentDayGroup = {
    date: string;
    year: number;
    isToday: boolean;
    appointments: Appointment[];
};

function isoDateForInstant(instant: Date, timezone: string): string {
    return isoDateIn(timezone, instant) ?? isoDateIn(UTC_TIMEZONE, instant) ?? '';
}

function yearOf(date: string): number {
    return Number(date.slice(0, ISO_YEAR_LENGTH));
}

function emptyTodayGroup(today: string): AppointmentDayGroup {
    return { date: today, year: yearOf(today), isToday: true, appointments: [] };
}

function groupContiguousDays(appointments: Appointment[], timezone: string): AppointmentDayGroup[] {
    const groups: AppointmentDayGroup[] = [];

    for (const appointment of appointments) {
        const date = isoDateForInstant(new Date(appointment.starts_at), timezone);
        const openGroup = groups.at(-1);

        if (openGroup !== undefined && openGroup.date === date) {
            openGroup.appointments.unshift(appointment);
            continue;
        }

        groups.push({ date, year: yearOf(date), isToday: false, appointments: [appointment] });
    }

    return groups;
}

function withTodayDivider(
    groups: AppointmentDayGroup[],
    today: string,
    isComplete: boolean,
): AppointmentDayGroup[] {
    const todayIndex = groups.findIndex((group) => group.date === today);

    if (todayIndex !== NOT_FOUND) {
        return groups.map((group, index) =>
            index === todayIndex ? { ...group, isToday: true } : group,
        );
    }

    const firstPastIndex = groups.findIndex((group) => group.date < today);

    if (firstPastIndex !== NOT_FOUND) {
        return [
            ...groups.slice(0, firstPastIndex),
            emptyTodayGroup(today),
            ...groups.slice(firstPastIndex),
        ];
    }

    return isComplete ? [...groups, emptyTodayGroup(today)] : groups;
}

export function todayInTimezone(timezone: string): string {
    return isoDateForInstant(new Date(), timezone);
}

export function buildAppointmentTimeline(
    appointments: Appointment[],
    timezone: string,
    today: string,
    isComplete: boolean,
): AppointmentDayGroup[] {
    if (appointments.length === 0) {
        return [];
    }

    return withTodayDivider(groupContiguousDays(appointments, timezone), today, isComplete);
}
