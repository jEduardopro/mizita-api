import { Search } from 'lucide-react';
import { useState } from 'react';
import type { ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import { Input } from '@/components/ui/input';
import { foldForSearch } from '@/lib/text';
import { FilterChip } from './FilterChip';
import { FilterChipApply } from './FilterChipApply';
import { OptionsFilterList, type OptionsFilterListMessages } from './OptionsFilterList';
import { useFilterDraft } from './use-filter-draft';

export type OptionsFilterOption = {
    value: string;
    label: string;
};

export type OptionsFilterChipMessages = OptionsFilterListMessages & {
    apply: string;
    clear: string;
    searchPlaceholder: string;
};

export type OptionsFilterChipProps = {
    label: string;
    valueLabel: string | null;
    options: readonly OptionsFilterOption[];
    value: readonly string[];
    onApply: (values: string[]) => void;
    onClear: () => void;
    messages: OptionsFilterChipMessages;
    searchable?: boolean;
    onSearchChange?: (term: string) => void;
    status?: ComboboxOptionsStatus;
    onRetry?: () => void;
    className?: string;
};

function isSameSelection(left: readonly string[], right: readonly string[]): boolean {
    return left.length === right.length && left.every((value) => right.includes(value));
}

function matchingOptions(options: readonly OptionsFilterOption[], term: string): readonly OptionsFilterOption[] {
    const needle = foldForSearch(term.trim());

    if (needle === '') {
        return options;
    }

    return options.filter((option) => foldForSearch(option.label).includes(needle));
}

type SearchProps = {
    placeholder: string;
    term: string;
    onChange: (term: string) => void;
};

function OptionsFilterSearch({ placeholder, term, onChange }: SearchProps) {
    return (
        <div className="relative shrink-0 border-b border-border">
            <Search
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />

            <Input
                type="search"
                aria-label={placeholder}
                placeholder={placeholder}
                value={term}
                onChange={(event) => onChange(event.target.value)}
                autoComplete="off"
                autoCorrect="off"
                spellCheck={false}
                className="h-11 rounded-none border-0 pl-9 text-base focus-visible:ring-0 md:text-sm dark:bg-transparent"
            />
        </div>
    );
}

export function OptionsFilterChip({
    label,
    valueLabel,
    options,
    value,
    onApply,
    onClear,
    messages,
    searchable = false,
    onSearchChange,
    status = 'ready',
    onRetry,
    className,
}: OptionsFilterChipProps) {
    const filter = useFilterDraft<readonly string[]>({ value, onApply: (values) => onApply([...values]) });
    const [term, setTerm] = useState('');

    const visibleOptions = onSearchChange === undefined ? matchingOptions(options, term) : options;
    const canApply = filter.draft.length > 0 && ! isSameSelection(filter.draft, value);

    function changeTerm(next: string) {
        setTerm(next);
        onSearchChange?.(next);
    }

    function handleOpenChange(next: boolean) {
        filter.onOpenChange(next);

        if (term !== '') {
            changeTerm('');
        }
    }

    function toggle(optionValue: string) {
        filter.setDraft(
            filter.draft.includes(optionValue)
                ? filter.draft.filter((selected) => selected !== optionValue)
                : [...filter.draft, optionValue],
        );
    }

    return (
        <FilterChip
            label={label}
            valueLabel={valueLabel}
            open={filter.open}
            onOpenChange={handleOpenChange}
            onClear={onClear}
            messages={messages}
            className={className}
        >
            {searchable ? (
                <OptionsFilterSearch placeholder={messages.searchPlaceholder} term={term} onChange={changeTerm} />
            ) : null}

            <OptionsFilterList
                label={label}
                options={visibleOptions}
                selected={filter.draft}
                onToggle={toggle}
                status={status}
                onRetry={onRetry}
                messages={messages}
            />

            <FilterChipApply disabled={! canApply} onClick={filter.apply}>
                {messages.apply}
            </FilterChipApply>
        </FilterChip>
    );
}
