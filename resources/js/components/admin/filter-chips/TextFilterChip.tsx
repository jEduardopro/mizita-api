import type { ChangeEvent, FormEvent } from 'react';
import { Input } from '@/components/ui/input';
import { FilterChip } from './FilterChip';
import { FilterChipApply } from './FilterChipApply';
import { useFilterDraft } from './use-filter-draft';

export type TextFilterChipMessages = {
    apply: string;
    clear: string;
};

export type TextFilterChipProps = {
    label: string;
    value: string | null;
    onApply: (text: string) => void;
    onClear: () => void;
    placeholder: string;
    messages: TextFilterChipMessages;
    maxLength?: number;
    normalize?: (text: string) => string;
    className?: string;
};

function keepText(text: string): string {
    return text;
}

export function TextFilterChip({
    label,
    value,
    onApply,
    onClear,
    placeholder,
    messages,
    maxLength,
    normalize = keepText,
    className,
}: TextFilterChipProps) {
    const filter = useFilterDraft({ value: value ?? '', onApply: (text) => onApply(text.trim()) });
    const trimmedDraft = filter.draft.trim();
    const canApply = trimmedDraft !== '' && trimmedDraft !== value;

    function handleChange(event: ChangeEvent<HTMLInputElement>) {
        filter.setDraft(normalize(event.target.value));
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (canApply) {
            filter.apply();
        }
    }

    return (
        <FilterChip
            label={label}
            valueLabel={value}
            open={filter.open}
            onOpenChange={filter.onOpenChange}
            onClear={onClear}
            messages={messages}
            className={className}
        >
            <form onSubmit={handleSubmit} className="flex flex-col">
                <div className="p-3">
                    <Input
                        type="text"
                        aria-label={label}
                        placeholder={placeholder}
                        value={filter.draft}
                        onChange={handleChange}
                        maxLength={maxLength}
                        autoComplete="off"
                        autoCorrect="off"
                        spellCheck={false}
                        enterKeyHint="done"
                        className="h-11 text-base md:h-9 md:text-sm"
                    />
                </div>

                <FilterChipApply type="submit" disabled={! canApply}>
                    {messages.apply}
                </FilterChipApply>
            </form>
        </FilterChip>
    );
}
