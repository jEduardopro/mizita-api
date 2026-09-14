import {
    useEffect,
    useRef,
    useState,
    type ChangeEvent,
    type FocusEvent,
    type KeyboardEvent,
    type RefObject,
} from 'react';

export type ComboboxOption = {
    value: string;
    label: string;
};

type Params = {
    id: string;
    options: readonly ComboboxOption[];
    value: string | null;
    onChange: (value: string | null) => void;
};

type Combobox = {
    rootRef: RefObject<HTMLDivElement | null>;
    listId: string;
    query: string;
    open: boolean;
    filteredOptions: readonly ComboboxOption[];
    activeIndex: number;
    activeOptionId: string | undefined;
    optionId: (index: number) => string;
    openList: () => void;
    selectOption: (option: ComboboxOption) => void;
    onQueryChange: (event: ChangeEvent<HTMLInputElement>) => void;
    onKeyDown: (event: KeyboardEvent<HTMLInputElement>) => void;
    onBlur: (event: FocusEvent<HTMLElement>) => void;
};

function fold(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

export function useCombobox({ id, options, value, onChange }: Params): Combobox {
    const rootRef = useRef<HTMLDivElement>(null);

    const selectedOption = options.find((option) => option.value === value) ?? null;
    const selectedLabel = selectedOption?.label ?? '';

    const [query, setQuery] = useState(selectedLabel);
    const [open, setOpen] = useState(false);
    const [requestedIndex, setRequestedIndex] = useState(0);
    const [shownLabel, setShownLabel] = useState(selectedLabel);

    if (selectedLabel !== shownLabel && ! open) {
        setShownLabel(selectedLabel);
        setQuery(selectedLabel);
    }

    const search = query.trim();
    const filteredOptions =
        search !== '' && query !== selectedLabel
            ? options.filter((option) => fold(option.label).includes(fold(search)))
            : options;

    const lastIndex = filteredOptions.length - 1;
    const activeIndex = lastIndex < 0 ? -1 : Math.min(requestedIndex, lastIndex);
    const activeOption = activeIndex < 0 ? null : filteredOptions[activeIndex];

    const optionId = (index: number) => `${id}-option-${index}`;
    const activeOptionId = open && activeIndex >= 0 ? optionId(activeIndex) : undefined;

    useEffect(() => {
        if (activeOptionId === undefined) {
            return;
        }

        document.getElementById(activeOptionId)?.scrollIntoView({ block: 'nearest' });
    }, [activeOptionId]);

    function openList() {
        setOpen(true);
    }

    function selectOption(option: ComboboxOption) {
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
                setOpen(true);
                setRequestedIndex(0);

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

    function onBlur(event: FocusEvent<HTMLElement>) {
        if (rootRef.current?.contains(event.relatedTarget)) {
            return;
        }

        setOpen(false);
        setQuery(selectedLabel);
        setShownLabel(selectedLabel);
    }

    return {
        rootRef,
        listId: `${id}-listbox`,
        query,
        open,
        filteredOptions,
        activeIndex,
        activeOptionId,
        optionId,
        openList,
        selectOption,
        onQueryChange,
        onKeyDown,
        onBlur,
    };
}
