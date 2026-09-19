import { timeOfDayIn } from '@/lib/timezone';

const TIME_OF_DAY_PATTERN = /^(\d{2}):(\d{2})$/;

const UTC_TIMEZONE = 'UTC';

const MINUTE_IN_MS = 60_000;

const ISO_DATE_LENGTH = 'YYYY-MM-DD'.length;

const HOURS_IN_HALF_DAY = 12;

const LAST_HOUR_OF_DAY = 23;

const LAST_MINUTE_OF_HOUR = 59;

const ANTE_MERIDIEM = 'AM';

const POST_MERIDIEM = 'PM';

export function todayAsIsoDate(): string {
    const today = new Date();

    return new Date(today.getTime() - today.getTimezoneOffset() * MINUTE_IN_MS)
        .toISOString()
        .slice(0, ISO_DATE_LENGTH);
}

export function formatTimeOfDay(value: string): string {
    const parts = TIME_OF_DAY_PATTERN.exec(value);

    if (parts === null) {
        return value;
    }

    const [, hourText, minuteText] = parts;
    const hours = Number(hourText);
    const minutes = Number(minuteText);

    if (hours > LAST_HOUR_OF_DAY || minutes > LAST_MINUTE_OF_HOUR) {
        return value;
    }

    const hoursOnClock = hours % HOURS_IN_HALF_DAY === 0 ? HOURS_IN_HALF_DAY : hours % HOURS_IN_HALF_DAY;
    const meridiem = hours < HOURS_IN_HALF_DAY ? ANTE_MERIDIEM : POST_MERIDIEM;

    return `${hoursOnClock}:${minuteText} ${meridiem}`;
}

export function formatInstantTimeOfDay(instant: string, timeZone: string): string {
    const moment = new Date(instant);
    const timeOfDay = timeOfDayIn(timeZone, moment) ?? timeOfDayIn(UTC_TIMEZONE, moment);

    return timeOfDay === null ? '' : formatTimeOfDay(timeOfDay);
}
