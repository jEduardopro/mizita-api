import { useRef, useState, type FocusEvent, type KeyboardEvent, type RefObject } from 'react';
import { dateFromIso, isoFromDate } from '@/components/form/date-format';

type Params = {
    id: string;
    value: string;
    onChange: (value: string) => void;
    fallbackMonth: Date;
};

type OutsideInteraction = {
    target: EventTarget | null;
    preventDefault: () => void;
};

type DateField = {
    rootRef: RefObject<HTMLDivElement | null>;
    fieldRef: RefObject<HTMLButtonElement | null>;
    calendarRef: RefObject<HTMLDivElement | null>;
    calendarId: string;
    open: boolean;
    selected: Date | undefined;
    month: Date;
    toggleCalendar: () => void;
    onCalendarMount: () => void;
    showMonth: (month: Date) => void;
    selectDate: (date: Date | undefined) => void;
    clear: () => void;
    onKeyDown: (event: KeyboardEvent<HTMLButtonElement>) => void;
    onBlur: (event: FocusEvent<HTMLElement>) => void;
    onOpenChange: (next: boolean) => void;
    onInteractOutside: (event: OutsideInteraction) => void;
    returnFocus: () => void;
};

const FOCUSABLE_DAY = 'button[tabindex="0"]';

export function useDateField({ id, value, onChange, fallbackMonth }: Params): DateField {
    const rootRef = useRef<HTMLDivElement>(null);
    const fieldRef = useRef<HTMLButtonElement>(null);
    const calendarRef = useRef<HTMLDivElement>(null);
    const dayFocusPending = useRef(false);

    const selected = dateFromIso(value);

    const [shown, setShown] = useState(value);
    const [open, setOpen] = useState(false);
    const [month, setMonth] = useState(selected ?? fallbackMonth);

    if (value !== shown) {
        setShown(value);

        if (selected !== null) {
            setMonth(selected);
        }
    }

    function focusDay() {
        calendarRef.current?.querySelector<HTMLElement>(FOCUSABLE_DAY)?.focus();
    }

    function onCalendarMount() {
        if (! dayFocusPending.current) {
            return;
        }

        dayFocusPending.current = false;
        focusDay();
    }

    function returnFocus() {
        fieldRef.current?.focus();
    }

    function toggleCalendar() {
        setOpen(! open);
    }

    function showMonth(next: Date) {
        setMonth(next);
    }

    function selectDate(date: Date | undefined) {
        if (date === undefined) {
            return;
        }

        setMonth(date);
        setOpen(false);
        onChange(isoFromDate(date));
        returnFocus();
    }

    function clear() {
        onChange('');
        returnFocus();
    }

    function onKeyDown(event: KeyboardEvent<HTMLButtonElement>) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();

            if (open) {
                focusDay();

                return;
            }

            dayFocusPending.current = true;
            setOpen(true);

            return;
        }

        if (event.key === 'Escape' && open) {
            event.preventDefault();
            setOpen(false);
        }
    }

    function onBlur(event: FocusEvent<HTMLElement>) {
        const movedTo = event.relatedTarget;

        if (rootRef.current?.contains(movedTo) || calendarRef.current?.contains(movedTo)) {
            return;
        }

        setOpen(false);
    }

    function onOpenChange(next: boolean) {
        setOpen(next);
    }

    function onInteractOutside(event: OutsideInteraction) {
        if (event.target instanceof Node && rootRef.current?.contains(event.target)) {
            event.preventDefault();
        }
    }

    return {
        rootRef,
        fieldRef,
        calendarRef,
        calendarId: `${id}-calendar`,
        open,
        selected: selected ?? undefined,
        month,
        toggleCalendar,
        onCalendarMount,
        showMonth,
        selectDate,
        clear,
        onKeyDown,
        onBlur,
        onOpenChange,
        onInteractOutside,
        returnFocus,
    };
}
