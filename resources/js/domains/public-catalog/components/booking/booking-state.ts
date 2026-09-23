import type { ComboboxOption } from '@/components/form/use-combobox';

export const BOOKING_STATE_COUNTRY = 'MX';

export type BookingStateSelection =
    | { kind: 'catalog'; id: string; name: string }
    | { kind: 'other' };

export type BookingStateValue = {
    selection: BookingStateSelection | null;
    otherName: string;
};

export const EMPTY_BOOKING_STATE: BookingStateValue = {
    selection: null,
    otherName: '',
};

export function isOtherStateSelected({ selection }: BookingStateValue): boolean {
    return selection?.kind === 'other';
}

export function bookingStateName({ selection, otherName }: BookingStateValue): string {
    if (selection === null) {
        return '';
    }

    return selection.kind === 'other' ? otherName : selection.name;
}

export function stateChoiceOf({ selection }: BookingStateValue): string | null {
    return selection?.kind === 'catalog' ? selection.id : null;
}

function catalogSelectionFor(
    choice: string | null,
    options: readonly ComboboxOption[],
): BookingStateSelection | null {
    const option = options.find((candidate) => candidate.value === choice);

    return option === undefined ? null : { kind: 'catalog', id: option.value, name: option.label };
}

export function withStateChoice(
    state: BookingStateValue,
    choice: string | null,
    options: readonly ComboboxOption[],
): BookingStateValue {
    return { ...state, selection: catalogSelectionFor(choice, options) };
}

export function withOtherState(state: BookingStateValue): BookingStateValue {
    return { ...state, selection: { kind: 'other' } };
}

export function withCatalogState(state: BookingStateValue): BookingStateValue {
    return { ...state, selection: null };
}

export function withOtherStateName(state: BookingStateValue, otherName: string): BookingStateValue {
    return { ...state, otherName };
}
