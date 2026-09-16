import { cn } from 'cn';
import { Search } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { foldForSearch } from '@/lib/text';

export type CheckboxListOption = {
    value: string;
    label: string;
};

export type CheckboxListStatus = 'pending' | 'error' | 'ready';

type Messages = {
    searchLabel: string;
    searchPlaceholder: string;
    selectAll: string;
    selected: string;
    empty: string;
    optionsError: string;
    retry: string;
};

const SKELETON_ROWS = [0, 1, 2];

type SelectionState = boolean | 'mixed';

function selectionStateOf(
    visible: readonly CheckboxListOption[],
    selected: readonly string[],
): SelectionState {
    const chosen = visible.filter((option) => selected.includes(option.value)).length;

    if (chosen === 0) {
        return false;
    }

    return chosen === visible.length ? true : 'mixed';
}

type Props = {
    id: string;
    label: string;
    options: readonly CheckboxListOption[];
    value: readonly string[];
    onChange: (value: string[]) => void;
    optionsStatus: CheckboxListStatus;
    onRetryOptions?: () => void;
    messages: Messages;
    error?: string;
    hint?: string;
    required?: boolean;
};

export function CheckboxListField({
    id,
    label,
    options,
    value,
    onChange,
    optionsStatus,
    onRetryOptions,
    messages,
    error,
    hint,
    required,
}: Props) {
    const [search, setSearch] = useState('');
    const selectAllRef = useRef<HTMLInputElement>(null);

    const labelId = useId();
    const searchId = `${id}-search`;
    const message = fieldMessage({ id, error, hint });

    const needle = foldForSearch(search.trim());
    const visible =
        needle === ''
            ? options
            : options.filter((option) => foldForSearch(option.label).includes(needle));

    const selectionState = selectionStateOf(visible, value);

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = selectionState === 'mixed';
        }
    }, [selectionState]);

    function toggle(optionValue: string) {
        onChange(
            value.includes(optionValue)
                ? value.filter((selected) => selected !== optionValue)
                : [...value, optionValue],
        );
    }

    function toggleVisible() {
        const visibleValues = visible.map((option) => option.value);

        onChange(
            selectionState === true
                ? value.filter((selected) => ! visibleValues.includes(selected))
                : [...new Set([...value, ...visibleValues])],
        );
    }

    return (
        <div className="grid gap-2">
            <span id={labelId} className="text-sm leading-none font-medium">
                {label}
                {required ? (
                    <span aria-hidden="true" className="ms-0.5 text-destructive">
                        *
                    </span>
                ) : null}
            </span>

            <div
                role="group"
                aria-labelledby={labelId}
                aria-describedby={message?.id}
                aria-required={required}
                aria-invalid={!! error}
                className={cn(
                    'overflow-hidden rounded-xl border border-input',
                    error ? 'border-destructive' : undefined,
                )}
            >
                <div className="relative border-b border-border">
                    <Search
                        aria-hidden="true"
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />

                    <Label htmlFor={searchId} className="sr-only">
                        {messages.searchLabel}
                    </Label>

                    <Input
                        id={searchId}
                        type="search"
                        autoComplete="off"
                        placeholder={messages.searchPlaceholder}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="h-11 rounded-none border-0 pl-9 text-base focus-visible:ring-0 md:text-base dark:bg-transparent"
                    />
                </div>

                {optionsStatus === 'pending' ? (
                    <div aria-busy="true" className="grid gap-2 p-3">
                        {SKELETON_ROWS.map((row) => (
                            <span
                                key={row}
                                aria-hidden="true"
                                className="h-6 rounded-sm bg-muted motion-safe:animate-pulse"
                            />
                        ))}
                    </div>
                ) : null}

                {optionsStatus === 'error' ? (
                    <div className="grid gap-2 p-3">
                        <p className="text-xs text-muted-foreground">{messages.optionsError}</p>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={onRetryOptions}
                            className="h-11 justify-self-start px-4 md:h-9"
                        >
                            {messages.retry}
                        </Button>
                    </div>
                ) : null}

                {optionsStatus === 'ready' && options.length > 0 ? (
                    <div className="flex min-h-11 items-center justify-between gap-2 border-b border-border px-3">
                        <label className="flex min-h-11 items-center gap-3 text-sm">
                            <input
                                ref={selectAllRef}
                                type="checkbox"
                                checked={selectionState === true}
                                onChange={toggleVisible}
                                className="size-4.5 accent-primary"
                            />
                            {messages.selectAll}
                        </label>

                        <span className="text-xs text-muted-foreground">{messages.selected}</span>
                    </div>
                ) : null}

                {optionsStatus === 'ready' && visible.length === 0 ? (
                    <p role="status" className="px-3 py-3 text-sm text-muted-foreground">
                        {messages.empty}
                    </p>
                ) : null}

                <ul className="max-h-[min(18rem,45svh)] overflow-y-auto overscroll-contain p-1">
                    {visible.map((option) => (
                        <li key={option.value}>
                            <label className="flex min-h-11 items-center gap-3 rounded-lg px-2.5 text-base hover:bg-muted has-[:focus-visible]:bg-muted">
                                <input
                                    type="checkbox"
                                    checked={value.includes(option.value)}
                                    onChange={() => toggle(option.value)}
                                    className="size-4.5 accent-primary"
                                />
                                <span className="min-w-0 flex-1 truncate">{option.label}</span>
                            </label>
                        </li>
                    ))}
                </ul>
            </div>

            <FieldMessage message={message} />
        </div>
    );
}
