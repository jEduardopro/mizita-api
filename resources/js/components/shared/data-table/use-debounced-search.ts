import { useCallback, useEffect, useState } from 'react';
import { useDebouncedValue } from '@/hooks/use-debounced-value';

type DebouncedSearch = {
    input: string;
    setInput: (value: string) => void;
    clear: () => void;
};

export function useDebouncedSearch(
    search: string,
    setSearch: (value: string) => void,
    delayMs: number,
): DebouncedSearch {
    const [input, setInput] = useState(search);
    const [syncedSearch, setSyncedSearch] = useState(search);
    const debouncedInput = useDebouncedValue(input, delayMs);

    if (search !== syncedSearch) {
        setSyncedSearch(search);
        setInput(search);
    }

    useEffect(() => {
        if (debouncedInput === input && debouncedInput !== search) {
            setSearch(debouncedInput);
        }
    }, [debouncedInput, input, search, setSearch]);

    const clear = useCallback(() => setInput(''), []);

    return { input, setInput, clear };
}
