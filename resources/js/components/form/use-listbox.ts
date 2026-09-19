import { useEffect, useRef, useState, type FocusEvent, type KeyboardEvent, type RefObject } from 'react';

type Params = {
    id: string;
    values: readonly string[];
    value: string;
    onChange: (value: string) => void;
};

type OutsideInteraction = {
    target: EventTarget | null;
    preventDefault: () => void;
};

type Listbox = {
    rootRef: RefObject<HTMLDivElement | null>;
    listId: string;
    open: boolean;
    activeIndex: number;
    activeOptionId: string | undefined;
    optionId: (index: number) => string;
    toggle: () => void;
    select: (value: string) => void;
    onKeyDown: (event: KeyboardEvent<HTMLElement>) => void;
    onBlur: (event: FocusEvent<HTMLElement>) => void;
    onOpenChange: (next: boolean) => void;
    onInteractOutside: (event: OutsideInteraction) => void;
};

const OPENING_KEYS = ['Enter', ' ', 'ArrowDown', 'ArrowUp'];

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

export function useListbox({ id, values, value, onChange }: Params): Listbox {
    const rootRef = useRef<HTMLDivElement>(null);
    const selectedIndex = values.indexOf(value);

    const [open, setOpen] = useState(false);
    const [requestedIndex, setRequestedIndex] = useState(selectedIndex);

    const lastIndex = values.length - 1;
    const activeIndex = lastIndex < 0 ? -1 : clamp(requestedIndex, 0, lastIndex);

    const optionId = (index: number) => `${id}-option-${index}`;
    const activeOptionId = open && activeIndex >= 0 ? optionId(activeIndex) : undefined;

    useEffect(() => {
        if (activeOptionId === undefined) {
            return;
        }

        document.getElementById(activeOptionId)?.scrollIntoView({ block: 'nearest' });
    }, [activeOptionId]);

    function openList() {
        setRequestedIndex(Math.max(selectedIndex, 0));
        setOpen(true);
    }

    function toggle() {
        if (open) {
            setOpen(false);

            return;
        }

        openList();
    }

    function select(next: string) {
        setOpen(false);
        onChange(next);
    }

    function onKeyDown(event: KeyboardEvent<HTMLElement>) {
        if (event.key === 'Escape') {
            if (open) {
                event.preventDefault();
                setOpen(false);
            }

            return;
        }

        if (! OPENING_KEYS.includes(event.key) && event.key !== 'Home' && event.key !== 'End') {
            return;
        }

        event.preventDefault();

        if (! open) {
            openList();

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            setRequestedIndex(clamp(activeIndex + (event.key === 'ArrowDown' ? 1 : -1), 0, lastIndex));

            return;
        }

        if (event.key === 'Home' || event.key === 'End') {
            setRequestedIndex(event.key === 'Home' ? 0 : Math.max(lastIndex, 0));

            return;
        }

        if (activeIndex >= 0) {
            select(values[activeIndex]);
        }
    }

    function onBlur(event: FocusEvent<HTMLElement>) {
        if (rootRef.current?.contains(event.relatedTarget)) {
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
        listId: `${id}-listbox`,
        open,
        activeIndex,
        activeOptionId,
        optionId,
        toggle,
        select,
        onKeyDown,
        onBlur,
        onOpenChange,
        onInteractOutside,
    };
}
