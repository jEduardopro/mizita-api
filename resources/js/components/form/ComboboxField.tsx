import { cn } from 'cn';
import { Check, ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { useCombobox, type ComboboxOption } from '@/components/form/use-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type { ComboboxOption };

export type ComboboxOptionsStatus = 'pending' | 'error' | 'ready';

export function comboboxOptionsStatus(isPending: boolean, isError: boolean): ComboboxOptionsStatus {
    if (isPending) {
        return 'pending';
    }

    if (isError) {
        return 'error';
    }

    return 'ready';
}

type Messages = {
    empty: string;
    optionsError: string;
    retry: string;
    results: string;
};

const SKELETON_ROWS = [0, 1, 2];

type PanelProps = {
    listId: string;
    label: string;
    options: readonly ComboboxOption[];
    selectedValue: string | null;
    activeIndex: number;
    optionId: (index: number) => string;
    onSelect: (option: ComboboxOption) => void;
    status: ComboboxOptionsStatus;
    emptyMessage: string;
};

function ComboboxPanel({
    listId,
    label,
    options,
    selectedValue,
    activeIndex,
    optionId,
    onSelect,
    status,
    emptyMessage,
}: PanelProps) {
    return (
        <div
            aria-busy={status === 'pending'}
            className="overflow-hidden rounded-lg border border-border bg-popover shadow-lg"
        >
            {status === 'pending' ? (
                <div className="grid gap-2 p-2.5">
                    {SKELETON_ROWS.map((row) => (
                        <span
                            key={row}
                            aria-hidden="true"
                            className="h-6 rounded-sm bg-muted motion-safe:animate-pulse"
                        />
                    ))}
                </div>
            ) : null}

            {status === 'ready' && options.length === 0 ? (
                <p role="status" className="px-3 py-2.5 text-sm text-muted-foreground">
                    {emptyMessage}
                </p>
            ) : null}

            <ul
                id={listId}
                role="listbox"
                aria-label={label}
                onMouseDown={(event) => event.preventDefault()}
                className="max-h-[min(18rem,45svh)] overflow-y-auto overscroll-contain"
            >
                {options.map((option, index) => (
                    <li
                        key={option.value}
                        id={optionId(index)}
                        role="option"
                        aria-selected={option.value === selectedValue}
                        onClick={() => onSelect(option)}
                        className={cn(
                            'flex min-h-11 cursor-default items-center gap-2 px-3 py-2.5 text-base',
                            index === activeIndex ? 'bg-muted text-foreground' : undefined,
                        )}
                    >
                        <span className="min-w-0 flex-1">{option.label}</span>

                        {option.value === selectedValue ? (
                            <Check aria-hidden="true" className="size-4 shrink-0 text-primary" />
                        ) : null}
                    </li>
                ))}
            </ul>
        </div>
    );
}

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type' | 'role' | 'list'> & {
    id: string;
    label: string;
    options: readonly ComboboxOption[];
    value: string | null;
    onChange: (value: string | null) => void;
    optionsStatus: ComboboxOptionsStatus;
    onRetryOptions?: () => void;
    messages: Messages;
    error?: string;
    hint?: string;
};

export function ComboboxField({
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
    className,
    ...props
}: Props) {
    const combobox = useCombobox({ id, options, value, onChange });
    const message = fieldMessage({ id, error, hint });

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>

            <div ref={combobox.rootRef} onBlur={combobox.onBlur} className="grid gap-1.5">
                <div className="relative">
                    <Input
                        {...props}
                        id={id}
                        role="combobox"
                        aria-expanded={combobox.open}
                        aria-controls={combobox.listId}
                        aria-autocomplete="list"
                        aria-activedescendant={combobox.activeOptionId}
                        aria-invalid={!! error}
                        aria-describedby={message?.id}
                        autoComplete="off"
                        autoCorrect="off"
                        spellCheck={false}
                        value={combobox.query}
                        onChange={combobox.onQueryChange}
                        onKeyDown={combobox.onKeyDown}
                        onClick={combobox.openList}
                        className={cn('h-11 pr-9 text-base md:text-base', className)}
                    />

                    <ChevronDown
                        aria-hidden="true"
                        className={cn(
                            'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground',
                            'motion-safe:transition-transform motion-safe:duration-200',
                            combobox.open ? 'rotate-180' : undefined,
                        )}
                    />
                </div>

                {combobox.open ? (
                    <ComboboxPanel
                        listId={combobox.listId}
                        label={label}
                        options={combobox.filteredOptions}
                        selectedValue={value}
                        activeIndex={combobox.activeIndex}
                        optionId={combobox.optionId}
                        onSelect={combobox.selectOption}
                        status={optionsStatus}
                        emptyMessage={messages.empty}
                    />
                ) : null}

                {optionsStatus === 'error' ? (
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-xs text-muted-foreground">{messages.optionsError}</p>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onRetryOptions}
                            className="h-11 px-3"
                        >
                            {messages.retry}
                        </Button>
                    </div>
                ) : null}
            </div>

            <span role="status" className="sr-only">
                {combobox.open ? messages.results : ''}
            </span>

            <FieldMessage message={message} />
        </div>
    );
}
