export const BOOKING_STEPS = ['service', 'staff', 'time', 'details'] as const;

export type BookingStep = (typeof BOOKING_STEPS)[number];

export type BookingSelection = {
    with: string | null;
    service: string | null;
    staff: string | null;
    at: string | null;
};

export type BookingSelectionKey = keyof BookingSelection;

export const BOOKING_SELECTION_KEYS: readonly BookingSelectionKey[] = [
    'with',
    'service',
    'staff',
    'at',
];

export const EMPTY_BOOKING_SELECTION: BookingSelection = {
    with: null,
    service: null,
    staff: null,
    at: null,
};

type BookingPin = {
    key: BookingSelectionKey;
    supplies: BookingSelectionKey;
};

const BOOKING_PINS: readonly BookingPin[] = [{ key: 'with', supplies: 'staff' }];

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
    time: ['at'],
    details: [],
};

const STEP_PREREQUISITES: Record<BookingStep, readonly BookingSelectionKey[]> = {
    service: [],
    staff: ['service'],
    time: ['service', 'staff'],
    details: ['service', 'staff', 'at'],
};

export function businessPath(slug: string): string {
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

function activePins(selection: Partial<BookingSelection>): readonly BookingPin[] {
    return BOOKING_PINS.filter((pin) => (selection[pin.key] ?? null) !== null);
}

function keysSuppliedByPins(selection: Partial<BookingSelection>): BookingSelectionKey[] {
    return activePins(selection).map((pin) => pin.supplies);
}

export function isStepSkipped(step: BookingStep, selection: BookingSelection): boolean {
    const owned = STEP_OWNED_KEYS[step];
    const supplied = keysSuppliedByPins(selection);

    return owned.length > 0 && owned.every((key) => supplied.includes(key));
}

function selectionOwnedBy(
    steps: readonly BookingStep[],
    selection: BookingSelection,
): BookingSelection {
    const retained: BookingSelection = { ...EMPTY_BOOKING_SELECTION };

    for (const pin of BOOKING_PINS) {
        retained[pin.key] = selection[pin.key];
    }

    for (const step of steps) {
        for (const key of STEP_OWNED_KEYS[step]) {
            retained[key] = selection[key];
        }
    }

    return retained;
}

export function bookingStepsFor(selection: BookingSelection): BookingStep[] {
    return BOOKING_STEPS.filter((step) => ! isStepSkipped(step, selection));
}

export function previousStep(step: BookingStep, selection: BookingSelection): BookingStep | null {
    const earlier = bookingStepsFor(selection).filter(
        (candidate) => BOOKING_STEPS.indexOf(candidate) < BOOKING_STEPS.indexOf(step),
    );

    return earlier[earlier.length - 1] ?? null;
}

export function nextStep(step: BookingStep, selection: BookingSelection): BookingStep | null {
    return (
        bookingStepsFor(selection).find(
            (candidate) => BOOKING_STEPS.indexOf(candidate) > BOOKING_STEPS.indexOf(step),
        ) ?? null
    );
}

export function stepPrerequisites(step: BookingStep): readonly BookingSelectionKey[] {
    return STEP_PREREQUISITES[step];
}

export function selectionBefore(step: BookingStep, selection: BookingSelection): BookingSelection {
    return selectionOwnedBy(BOOKING_STEPS.slice(0, BOOKING_STEPS.indexOf(step)), selection);
}

export function selectionThrough(step: BookingStep, selection: BookingSelection): BookingSelection {
    return selectionOwnedBy(BOOKING_STEPS.slice(0, BOOKING_STEPS.indexOf(step) + 1), selection);
}

export function withPinsApplied(selection: BookingSelection): BookingSelection {
    const applied: BookingSelection = { ...selection };

    for (const pin of activePins(selection)) {
        applied[pin.supplies] = selection[pin.key];
    }

    return applied;
}

export function hasUnresolvedPin(requested: BookingSelection, resolved: BookingSelection): boolean {
    return activePins(requested).some((pin) => resolved[pin.key] === null);
}

export function bookingStepUrl(
    slug: string,
    step: BookingStep,
    selection: Partial<BookingSelection>,
): string {
    const supplied = keysSuppliedByPins(selection);

    const entries = BOOKING_SELECTION_KEYS.filter((key) => ! supplied.includes(key)).map(
        (key): [string, string | null] => [key, selection[key] ?? null],
    );

    return withQuery(flowPath(slug, step), queryFrom(entries));
}

export function bookingBackUrl(slug: string, step: BookingStep, selection: BookingSelection): string {
    const target = previousStep(step, selection);

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
