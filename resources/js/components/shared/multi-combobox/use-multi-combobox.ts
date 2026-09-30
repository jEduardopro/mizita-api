import { useEffect, useState, type ChangeEvent, type KeyboardEvent } from 'react';
import { foldForSearch } from '@/lib/text';

export type MultiComboboxOption = {
    value: string;
    label: string;
};

type Params = {
    id: string;
    options: readonly MultiComboboxOption[];
    value: readonly string[];
    onChange: (value: string[]) => void;
};

type MultiCombobox = {
    listId: string;
    open: boolean;
    query: string;
    filteredOptions: readonly MultiComboboxOption[];
    activeIndex: number;
    activeOptionId: string | undefined;
    optionId: (index: number) => string;
    isSelected: (option: MultiComboboxOption) => boolean;
    toggleOption: (option: MultiComboboxOption) => void;
    onOpenChange: (next: boolean) => void;
    onQueryChange: (event: ChangeEvent<HTMLInputElement>) => void;
    onSearchKeyDown: (event: KeyboardEvent<HTMLInputElement>) => void;
};

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function matching(options: readonly MultiComboboxOption[], query: string): readonly MultiComboboxOption[] {
    const needle = foldForSearch(query.trim());

    if (needle === '') {
        return options;
    }

    return options.filter((option) => foldForSearch(option.label).includes(needle));
}

export function useMultiCombobox({ id, options, value, onChange }: Params): MultiCombobox {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [requestedIndex, setRequestedIndex] = useState(0);

    const filteredOptions = matching(options, query);

    const lastIndex = filteredOptions.length - 1;
    const activeIndex = lastIndex < 0 ? -1 : clamp(requestedIndex, 0, lastIndex);
    const activeOption = activeIndex < 0 ? null : filteredOptions[activeIndex];

    const optionId = (index: number) => `${id}-option-${index}`;
    const activeOptionId = open && activeIndex >= 0 ? optionId(activeIndex) : undefined;

    useEffect(() => {
        if (activeOptionId === undefined) {
            return;
        }

        document.getElementById(activeOptionId)?.scrollIntoView({ block: 'nearest' });
    }, [activeOptionId]);

    function isSelected(option: MultiComboboxOption): boolean {
        return value.includes(option.value);
    }

    function toggleOption(option: MultiComboboxOption) {
        onChange(
            isSelected(option)
                ? value.filter((selected) => selected !== option.value)
                : [...value, option.value],
        );
    }

    function onOpenChange(next: boolean) {
        setOpen(next);
        setQuery('');
        setRequestedIndex(0);
    }

    function onQueryChange(event: ChangeEvent<HTMLInputElement>) {
        setQuery(event.target.value);
        setRequestedIndex(0);
    }

    function onSearchKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setRequestedIndex(clamp(activeIndex + (event.key === 'ArrowDown' ? 1 : -1), 0, lastIndex));

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
                toggleOption(activeOption);
            }
        }
    }

    return {
        listId: `${id}-listbox`,
        open,
        query,
        filteredOptions,
        activeIndex,
        activeOptionId,
        optionId,
        isSelected,
        toggleOption,
        onOpenChange,
        onQueryChange,
        onSearchKeyDown,
    };
}
