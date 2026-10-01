import { useState } from 'react';

type Params<T> = {
    value: T;
    onApply: (draft: T) => void;
};

type FilterDraft<T> = {
    open: boolean;
    draft: T;
    setDraft: (draft: T) => void;
    onOpenChange: (next: boolean) => void;
    apply: () => void;
};

export function useFilterDraft<T>({ value, onApply }: Params<T>): FilterDraft<T> {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<T>(value);

    function onOpenChange(next: boolean) {
        if (next) {
            setDraft(value);
        }

        setOpen(next);
    }

    function apply() {
        onApply(draft);
        setOpen(false);
    }

    return { open, draft, setDraft, onOpenChange, apply };
}
