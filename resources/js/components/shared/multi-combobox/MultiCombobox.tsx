import { cn } from 'cn';
import { ChevronDown, Search, X } from 'lucide-react';
import { useId, useRef, type ReactNode } from 'react';
import type { ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { MultiComboboxOptions } from './MultiComboboxOptions';
import { useMultiCombobox, type MultiComboboxOption } from './use-multi-combobox';

export type { MultiComboboxOption };

type Messages = {
    placeholder: string;
    search: string;
    empty: string;
    clear: string;
    selected: string;
    optionsError?: string;
    retry?: string;
};

type Props = {
    label: string;
    options: readonly MultiComboboxOption[];
    value: readonly string[];
    onChange: (value: string[]) => void;
    messages: Messages;
    status?: ComboboxOptionsStatus;
    onRetryOptions?: () => void;
    icon?: ReactNode;
    className?: string;
};

function summaryOf(
    options: readonly MultiComboboxOption[],
    value: readonly string[],
    messages: Messages,
): string {
    if (value.length === 0) {
        return messages.placeholder;
    }

    const onlySelected = value.length === 1 ? options.find((option) => option.value === value[0]) : undefined;

    return onlySelected?.label ?? messages.selected;
}

export function MultiCombobox({
    label,
    options,
    value,
    onChange,
    messages,
    status = 'ready',
    onRetryOptions,
    icon,
    className,
}: Props) {
    const id = useId();
    const labelId = `${id}-label`;
    const summaryId = `${id}-summary`;
    const triggerRef = useRef<HTMLButtonElement>(null);

    const combobox = useMultiCombobox({ id, options, value, onChange });
    const hasSelection = value.length > 0;

    function clear() {
        onChange([]);
        triggerRef.current?.focus();
    }

    return (
        <div className={cn('relative', className)}>
            <span id={labelId} className="sr-only">
                {label}
            </span>

            <Popover open={combobox.open} onOpenChange={combobox.onOpenChange}>
                <PopoverTrigger asChild>
                    <Button
                        ref={triggerRef}
                        type="button"
                        variant="outline"
                        aria-labelledby={`${labelId} ${summaryId}`}
                        className={cn(
                            'h-11 w-full justify-start gap-2 px-3 text-base font-normal md:h-9',
                            hasSelection ? 'pr-11 md:pr-9' : undefined,
                        )}
                    >
                        {icon}

                        <span
                            id={summaryId}
                            className={cn(
                                'min-w-0 flex-1 truncate text-left',
                                hasSelection ? undefined : 'text-muted-foreground',
                            )}
                        >
                            {summaryOf(options, value, messages)}
                        </span>

                        {hasSelection ? null : (
                            <ChevronDown
                                aria-hidden="true"
                                className={cn(
                                    'text-muted-foreground motion-safe:transition-transform motion-safe:duration-200',
                                    combobox.open ? 'rotate-180' : undefined,
                                )}
                            />
                        )}
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    aria-label={label}
                    align="start"
                    sideOffset={6}
                    collisionPadding={12}
                    className="w-[max(var(--radix-popover-trigger-width),16rem)] max-w-[calc(100vw-1.5rem)] gap-0 overflow-hidden p-0 shadow-lg"
                >
                    <div className="relative border-b border-border">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <Input
                            type="search"
                            role="combobox"
                            aria-label={messages.search}
                            aria-expanded={combobox.open}
                            aria-controls={combobox.listId}
                            aria-autocomplete="list"
                            aria-activedescendant={combobox.activeOptionId}
                            autoComplete="off"
                            autoCorrect="off"
                            spellCheck={false}
                            placeholder={messages.search}
                            value={combobox.query}
                            onChange={combobox.onQueryChange}
                            onKeyDown={combobox.onSearchKeyDown}
                            className="h-11 rounded-none border-0 pl-9 text-base focus-visible:ring-0 md:text-base dark:bg-transparent"
                        />
                    </div>

                    <MultiComboboxOptions
                        listId={combobox.listId}
                        label={label}
                        options={combobox.filteredOptions}
                        activeIndex={combobox.activeIndex}
                        optionId={combobox.optionId}
                        isSelected={combobox.isSelected}
                        onToggle={combobox.toggleOption}
                        status={status}
                        onRetry={onRetryOptions}
                        messages={messages}
                    />
                </PopoverContent>
            </Popover>

            {hasSelection ? (
                <Button
                    type="button"
                    variant="ghost"
                    aria-label={messages.clear}
                    onClick={clear}
                    className="absolute inset-y-0 right-0 h-auto w-11 rounded-l-none text-muted-foreground hover:bg-transparent hover:text-foreground md:w-9 dark:hover:bg-transparent"
                >
                    <X aria-hidden="true" />
                </Button>
            ) : null}
        </div>
    );
}
