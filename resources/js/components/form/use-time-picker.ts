import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type ChangeEvent,
    type FocusEvent,
    type KeyboardEvent,
    type RefObject,
} from 'react';
import {
    buildTimeOptions,
    filterTimeOptions,
    formatTime,
    minutesFromTime,
    timeOptionIndexFrom,
    type TimeOption,
} from '@/components/form/time-format';

type Params = {
    id: string;
    value: string;
    onChange: (value: string) => void;
    stepMinutes: number;
    startsFrom?: string;
};

type OutsideInteraction = {
    target: EventTarget | null;
    preventDefault: () => void;
};

export type TimePickerController = {
    rootRef: RefObject<HTMLDivElement | null>;
    inputRef: RefObject<HTMLInputElement | null>;
    listId: string;
    query: string;
    open: boolean;
    options: readonly TimeOption[];
    activeIndex: number;
    activeOptionId: string | undefined;
    optionId: (index: number) => string;
    openList: () => void;
    selectOption: (option: TimeOption) => void;
    onQueryChange: (event: ChangeEvent<HTMLInputElement>) => void;
    onKeyDown: (event: KeyboardEvent<HTMLInputElement>) => void;
    onFocus: () => void;
    onBlur: (event: FocusEvent<HTMLElement>) => void;
    onOpenChange: (next: boolean) => void;
    onInteractOutside: (event: OutsideInteraction) => void;
};

const DAY_START_MINUTES = 0;

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function firstOptionMinutes(startsFrom: string | undefined, stepMinutes: number): number {
    if (startsFrom === undefined) {
        return DAY_START_MINUTES;
    }

    const start = minutesFromTime(startsFrom);

    if (start === null) {
        return DAY_START_MINUTES;
    }

    return start + stepMinutes;
}

export function useTimePicker({
    id,
    value,
    onChange,
    stepMinutes,
    startsFrom,
}: Params): TimePickerController {
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const options = useMemo(
        () => buildTimeOptions(firstOptionMinutes(startsFrom, stepMinutes), stepMinutes),
        [startsFrom, stepMinutes],
    );

    const selectedLabel = formatTime(value);

    const [query, setQuery] = useState(selectedLabel);
    const [open, setOpen] = useState(false);
    const [requestedIndex, setRequestedIndex] = useState(0);
    const [shownLabel, setShownLabel] = useState(selectedLabel);

    if (selectedLabel !== shownLabel && ! open) {
        setShownLabel(selectedLabel);
        setQuery(selectedLabel);
    }

    const untouched = query === selectedLabel;
    const visibleOptions = untouched ? options : filterTimeOptions(options, query);

    const lastIndex = visibleOptions.length - 1;
    const activeIndex = lastIndex < 0 ? -1 : Math.min(requestedIndex, lastIndex);
    const activeOption = activeIndex < 0 ? null : visibleOptions[activeIndex];

    const optionId = (index: number) => `${id}-option-${index}`;
    const activeOptionId = open && activeIndex >= 0 ? optionId(activeIndex) : undefined;

    useEffect(() => {
        if (activeOptionId === undefined) {
            return;
        }

        document.getElementById(activeOptionId)?.scrollIntoView({ block: 'nearest' });
    }, [activeOptionId]);

    function indexForValue(): number {
        const minutes = minutesFromTime(value);

        if (minutes === null) {
            return 0;
        }

        return Math.max(timeOptionIndexFrom(options, minutes), 0);
    }

    function openList() {
        setOpen(true);

        if (! untouched) {
            return;
        }

        setRequestedIndex(indexForValue());
        inputRef.current?.select();
    }

    function selectOption(option: TimeOption) {
        setQuery(option.label);
        setShownLabel(option.label);
        setRequestedIndex(0);
        setOpen(false);
        onChange(option.value);
    }

    function onQueryChange(event: ChangeEvent<HTMLInputElement>) {
        setQuery(event.target.value);
        setRequestedIndex(0);
        setOpen(true);
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (! open) {
                openList();

                return;
            }

            setRequestedIndex(clamp(activeIndex + (event.key === 'ArrowDown' ? 1 : -1), 0, lastIndex));

            return;
        }

        if (! open) {
            return;
        }

        if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            setRequestedIndex(event.key === 'Home' ? 0 : Math.max(lastIndex, 0));

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();

            if (activeOption) {
                selectOption(activeOption);
            }

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
        }
    }

    function onFocus() {
        inputRef.current?.select();
    }

    function onBlur(event: FocusEvent<HTMLElement>) {
        if (rootRef.current?.contains(event.relatedTarget)) {
            return;
        }

        setOpen(false);
        setQuery(selectedLabel);
        setShownLabel(selectedLabel);
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
        inputRef,
        listId: `${id}-listbox`,
        query,
        open,
        options: visibleOptions,
        activeIndex,
        activeOptionId,
        optionId,
        openList,
        selectOption,
        onQueryChange,
        onKeyDown,
        onFocus,
        onBlur,
        onOpenChange,
        onInteractOutside,
    };
}
