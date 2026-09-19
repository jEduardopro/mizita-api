export type CalendarEvent = {
    uid: string;
    title: string;
    description: string;
    location: string;
    startsAt: string;
    endsAt: string;
};

const GOOGLE_CALENDAR_ENDPOINT = 'https://calendar.google.com/calendar/render';

const ICS_LINE_BREAK = '\r\n';

const ICS_CONTINUATION = ' ';

const ICS_MAX_LINE_LENGTH = 75;

const ICS_FILE_EXTENSION = '.ics';

const UNSAFE_FILE_NAME_CHARACTERS = /[^a-zA-Z0-9-]+/g;

const INSTANT_SEPARATORS = /[-:]/g;

const INSTANT_MILLISECONDS = /\.\d{3}(?=Z$)/;

function compactInstant(instant: string): string {
    const moment = new Date(instant);

    if (Number.isNaN(moment.getTime())) {
        return '';
    }

    return moment.toISOString().replace(INSTANT_SEPARATORS, '').replace(INSTANT_MILLISECONDS, '');
}

function escapeIcsText(value: string): string {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/;/g, '\\;')
        .replace(/,/g, '\\,')
        .replace(/\r?\n/g, '\\n');
}

function foldIcsLine(line: string): string {
    const segments: string[] = [];
    let remaining = line;

    while (remaining.length > ICS_MAX_LINE_LENGTH) {
        segments.push(remaining.slice(0, ICS_MAX_LINE_LENGTH));
        remaining = remaining.slice(ICS_MAX_LINE_LENGTH);
    }

    segments.push(remaining);

    return segments.join(`${ICS_LINE_BREAK}${ICS_CONTINUATION}`);
}

export function googleCalendarUrl(event: CalendarEvent): string {
    const parameters = new URLSearchParams({
        action: 'TEMPLATE',
        text: event.title,
        dates: `${compactInstant(event.startsAt)}/${compactInstant(event.endsAt)}`,
        details: event.description,
        location: event.location,
    });

    return `${GOOGLE_CALENDAR_ENDPOINT}?${parameters.toString()}`;
}

export function icsCalendarFor(event: CalendarEvent, stampedAt: string): string {
    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Mizita//Booking//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        `UID:${escapeIcsText(event.uid)}`,
        `DTSTAMP:${compactInstant(stampedAt)}`,
        `DTSTART:${compactInstant(event.startsAt)}`,
        `DTEND:${compactInstant(event.endsAt)}`,
        `SUMMARY:${escapeIcsText(event.title)}`,
        `DESCRIPTION:${escapeIcsText(event.description)}`,
        `LOCATION:${escapeIcsText(event.location)}`,
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    return `${lines.map(foldIcsLine).join(ICS_LINE_BREAK)}${ICS_LINE_BREAK}`;
}

export function icsFileNameFor(uid: string): string {
    return `${uid.replace(UNSAFE_FILE_NAME_CHARACTERS, '-')}${ICS_FILE_EXTENSION}`;
}
