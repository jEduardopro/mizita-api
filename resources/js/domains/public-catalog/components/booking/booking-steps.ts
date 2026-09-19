export const BOOKING_STEPS = ['service', 'staff', 'time', 'details'] as const;

export type BookingStep = (typeof BOOKING_STEPS)[number];

export type BookingSelection = {
    service: string | null;
    staff: string | null;
    at: string | null;
    tz: string | null;
};

export type BookingSelectionKey = keyof BookingSelection;

export const BOOKING_SELECTION_KEYS: readonly BookingSelectionKey[] = [
    'service',
    'staff',
    'at',
    'tz',
];

export const EMPTY_BOOKING_SELECTION: BookingSelection = {
    service: null,
    staff: null,
    at: null,
    tz: null,
};

const FLOW_SEGMENT = 'book';

export const MANAGE_TOKEN_PARAM = 'token';

const STEP_SEGMENTS: Record<BookingStep, string> = {
    service: '',
    staff: 'staff',
    time: 'time',
    details: 'details',
};

const STEP_OWNED_KEYS: Record<BookingStep, readonly BookingSelectionKey[]> = {
    service: ['service'],
    staff: ['staff'],
    time: ['at', 'tz'],
    details: [],
};

const STEP_PREREQUISITES: Record<BookingStep, readonly BookingSelectionKey[]> = {
    service: [],
    staff: ['service'],
    time: ['service', 'staff'],
    details: ['service', 'staff', 'at'],
};

function businessPath(slug: string): string {
    return `/${encodeURIComponent(slug)}`;
}

function flowBasePath(slug: string): string {
    return `${businessPath(slug)}/${FLOW_SEGMENT}`;
}

function flowPath(slug: string, step: BookingStep): string {
    const segment = STEP_SEGMENTS[step];

    return segment === '' ? flowBasePath(slug) : `${flowBasePath(slug)}/${segment}`;
}

function queryFrom(entries: [string, string | null][]): string {
    const parameters = new URLSearchParams();

    for (const [key, value] of entries) {
        if (value !== null && value !== '') {
            parameters.set(key, value);
        }
    }

    return parameters.toString();
}

function withQuery(path: string, query: string): string {
    return query === '' ? path : `${path}?${query}`;
}

export function previousStep(step: BookingStep): BookingStep | null {
    const position = BOOKING_STEPS.indexOf(step);

    return position <= 0 ? null : BOOKING_STEPS[position - 1];
}

export function stepPrerequisites(step: BookingStep): readonly BookingSelectionKey[] {
    return STEP_PREREQUISITES[step];
}

export function selectionBefore(step: BookingStep, selection: BookingSelection): BookingSelection {
    const retained: BookingSelection = { ...EMPTY_BOOKING_SELECTION };

    for (const earlier of BOOKING_STEPS.slice(0, BOOKING_STEPS.indexOf(step))) {
        for (const key of STEP_OWNED_KEYS[earlier]) {
            retained[key] = selection[key];
        }
    }

    return retained;
}

export function bookingStepUrl(
    slug: string,
    step: BookingStep,
    selection: Partial<BookingSelection>,
): string {
    const entries = BOOKING_SELECTION_KEYS.map(
        (key): [string, string | null] => [key, selection[key] ?? null],
    );

    return withQuery(flowPath(slug, step), queryFrom(entries));
}

export function bookingBackUrl(slug: string, step: BookingStep, selection: BookingSelection): string {
    const target = previousStep(step);

    if (target === null) {
        return businessPath(slug);
    }

    return bookingStepUrl(slug, target, selectionBefore(step, selection));
}

export function bookingConfirmedUrl(slug: string, reference: string, manageToken: string): string {
    const path = `${flowBasePath(slug)}/confirmed/${encodeURIComponent(reference)}`;

    return withQuery(path, queryFrom([[MANAGE_TOKEN_PARAM, manageToken]]));
}

export function bookingManageUrl(slug: string, reference: string, manageToken: string): string {
    const path = `${flowBasePath(slug)}/manage/${encodeURIComponent(reference)}`;

    return withQuery(path, queryFrom([[MANAGE_TOKEN_PARAM, manageToken]]));
}

export function businessPageUrl(slug: string): string {
    return businessPath(slug);
}
