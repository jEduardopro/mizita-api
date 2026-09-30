import { cn } from 'cn';
import { Check } from 'lucide-react';
import type { ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import { Button } from '@/components/ui/button';
import { keepScrollableWhileModalOpen } from '@/lib/scrollable';
import type { MultiComboboxOption } from './use-multi-combobox';

const SKELETON_ROWS = [0, 1, 2];

type Messages = {
    empty: string;
    optionsError?: string;
    retry?: string;
};

type Props = {
    listId: string;
    label: string;
    options: readonly MultiComboboxOption[];
    activeIndex: number;
    optionId: (index: number) => string;
    isSelected: (option: MultiComboboxOption) => boolean;
    onToggle: (option: MultiComboboxOption) => void;
    status: ComboboxOptionsStatus;
    onRetry?: () => void;
    messages: Messages;
};

export function MultiComboboxOptions({
    listId,
    label,
    options,
    activeIndex,
    optionId,
    isSelected,
    onToggle,
    status,
    onRetry,
    messages,
}: Props) {
    return (
        <>
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

            {status === 'error' && messages.optionsError !== undefined ? (
                <div className="grid gap-2 p-3">
                    <p className="text-sm text-muted-foreground">{messages.optionsError}</p>

                    {onRetry !== undefined && messages.retry !== undefined ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onRetry}
                            className="h-11 justify-self-start px-4 md:h-9"
                        >
                            {messages.retry}
                        </Button>
                    ) : null}
                </div>
            ) : null}

            {status === 'ready' && options.length === 0 ? (
                <p role="status" className="px-3 py-3 text-sm text-muted-foreground">
                    {messages.empty}
                </p>
            ) : null}

            <ul
                ref={keepScrollableWhileModalOpen}
                id={listId}
                role="listbox"
                aria-label={label}
                aria-multiselectable="true"
                aria-busy={status === 'pending'}
                onMouseDown={(event) => event.preventDefault()}
                className="max-h-[min(18rem,calc(var(--radix-popover-content-available-height,100svh)-2.75rem))] overflow-y-auto overscroll-contain p-1 empty:hidden"
            >
                {options.map((option, index) => {
                    const selected = isSelected(option);

                    return (
                        <li
                            key={option.value}
                            id={optionId(index)}
                            role="option"
                            aria-selected={selected}
                            onClick={() => onToggle(option)}
                            className={cn(
                                'flex min-h-11 cursor-default items-center gap-3 rounded-md px-2.5 py-2 text-base hover:bg-muted',
                                index === activeIndex ? 'bg-muted' : undefined,
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'flex size-4.5 shrink-0 items-center justify-center rounded-[0.3rem] border motion-safe:transition-colors',
                                    selected
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input bg-background',
                                )}
                            >
                                {selected ? <Check className="size-3.5" strokeWidth={3} /> : null}
                            </span>

                            <span className="min-w-0 flex-1 truncate">{option.label}</span>
                        </li>
                    );
                })}
            </ul>
        </>
    );
}
