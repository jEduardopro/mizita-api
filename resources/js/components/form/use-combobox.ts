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
    /** The input's id. Every other id in the widget is derived from it. */
    id: string;
    /** Already sorted by the caller — this hook decides what matches, not what order. */
    options: readonly ComboboxOption[];
    value: string | null;
    onChange: (value: string | null) => void;
};

type Combobox = {
    /** Wraps the input and the list, so a blur can tell inside from outside. */
    rootRef: RefObject<HTMLDivElement | null>;
    listId: string;
    /** The text in the box: what was typed, or the selected option's label. */
    query: string;
    open: boolean;
    filteredOptions: readonly ComboboxOption[];
    /** The virtually focused row, or -1 when there is nothing to move to. */
    activeIndex: number;
    activeOptionId: string | undefined;
    optionId: (index: number) => string;
    openList: () => void;
    selectOption: (option: ComboboxOption) => void;
    onQueryChange: (event: ChangeEvent<HTMLInputElement>) => void;
    onKeyDown: (event: KeyboardEvent<HTMLInputElement>) => void;
    onBlur: (event: FocusEvent<HTMLElement>) => void;
};

/**
 * Strips accents and case so a Spanish list can be searched from a plain
 * keyboard: `barberia` has to find `Barbería`.
 */
function fold(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

/**
 * DOM focus never moves off the input: the active row is carried by
 * `aria-activedescendant`, so the caret stays where the person is writing while
 * the arrow keys walk a list they are not standing in.
 */
export function useCombobox({ id, options, value, onChange }: Params): Combobox {
    const rootRef = useRef<HTMLDivElement>(null);

    const selectedOption = options.find((option) => option.value === value) ?? null;
    const selectedLabel = selectedOption?.label ?? '';

    const [query, setQuery] = useState(selectedLabel);
    const [open, setOpen] = useState(false);
    const [requestedIndex, setRequestedIndex] = useState(0);
    const [shownLabel, setShownLabel] = useState(selectedLabel);

    // Options can arrive after the selection, so a value chosen before the list
    // loaded has no label until it does. Re-read only while closed, so this never
    // fights what is being typed.
    if (selectedLabel !== shownLabel && ! open) {
        setShownLabel(selectedLabel);
        setQuery(selectedLabel);
    }

    // Typed text filters, except while it is exactly the selection's own label:
    // reopening the list after choosing offers every option again.
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

    // Walking the list with the keyboard has to move the list, not just the
    // highlight.
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

            // Clamped rather than wrapping: holding an arrow key walks in one
            // direction.
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
            // While the list is showing, Enter belongs to the list. Without this
            // it would submit the form underneath.
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
        // Focus moving to the retry button inside the panel is not a blur.
        if (rootRef.current?.contains(event.relatedTarget)) {
            return;
        }

        // Free text is not a value: the box goes back to naming the selection,
        // or to empty when there is none.
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
