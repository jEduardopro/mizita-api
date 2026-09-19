import { cn } from 'cn';
import { Check, ChevronDown } from 'lucide-react';
import { useListbox } from '@/components/form/use-listbox';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { keepScrollableWhileModalOpen } from '@/lib/scrollable';

export type DialCodeOption = {
    code: string;
    name: string;
    dialCode: string;
};

type Props = {
    id: string;
    label: string;
    options: readonly DialCodeOption[];
    value: string;
    onChange: (code: string) => void;
    invalid?: boolean;
};

export function DialCodePicker({ id, label, options, value, onChange, invalid = false }: Props) {
    const listbox = useListbox({
        id,
        values: options.map((option) => option.code),
        value,
        onChange,
    });

    const selected = options.find((option) => option.code === value);

    return (
        <div ref={listbox.rootRef} onBlur={listbox.onBlur} className="shrink-0">
            <Popover open={listbox.open} onOpenChange={listbox.onOpenChange}>
                <PopoverAnchor asChild>
                    <button
                        id={id}
                        type="button"
                        role="combobox"
                        aria-label={label}
                        aria-expanded={listbox.open}
                        aria-controls={listbox.listId}
                        aria-activedescendant={listbox.activeOptionId}
                        aria-invalid={invalid}
                        onClick={listbox.toggle}
                        onKeyDown={listbox.onKeyDown}
                        className={cn(
                            'flex h-11 w-20 items-center justify-between gap-1.5 rounded-lg border border-input bg-transparent py-1 pr-2.5 pl-3 text-base tabular-nums transition-colors outline-none',
                            'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
                            'aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20',
                            'dark:bg-input/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40',
                        )}
                    >
                        {selected?.dialCode ?? value}

                        <ChevronDown
                            aria-hidden="true"
                            className={cn(
                                'size-4 shrink-0 text-muted-foreground',
                                'motion-safe:transition-transform motion-safe:duration-200',
                                listbox.open ? 'rotate-180' : undefined,
                            )}
                        />
                    </button>
                </PopoverAnchor>

                <PopoverContent
                    role="presentation"
                    align="start"
                    sideOffset={6}
                    collisionPadding={12}
                    onOpenAutoFocus={(event) => event.preventDefault()}
                    onCloseAutoFocus={(event) => event.preventDefault()}
                    onInteractOutside={listbox.onInteractOutside}
                    onMouseDown={(event) => event.preventDefault()}
                    className="w-auto min-w-(--radix-popover-trigger-width) gap-0 overflow-hidden p-0 shadow-lg"
                >
                    <ul
                        ref={keepScrollableWhileModalOpen}
                        id={listbox.listId}
                        role="listbox"
                        aria-label={label}
                        className="max-h-[min(18rem,var(--radix-popover-content-available-height,100svh))] overflow-y-auto overscroll-contain"
                    >
                        {options.map((option, index) => (
                            <li
                                key={option.code}
                                id={listbox.optionId(index)}
                                role="option"
                                aria-selected={option.code === value}
                                onClick={() => listbox.select(option.code)}
                                className={cn(
                                    'flex min-h-11 cursor-default items-center gap-3 px-3 py-2.5 text-base hover:bg-muted',
                                    index === listbox.activeIndex ? 'bg-muted text-foreground' : undefined,
                                )}
                            >
                                <span className="min-w-0 flex-1">{option.name}</span>

                                <span className="shrink-0 tabular-nums text-muted-foreground">
                                    {option.dialCode}
                                </span>

                                {option.code === value ? (
                                    <Check aria-hidden="true" className="size-4 shrink-0 text-primary" />
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </PopoverContent>
            </Popover>
        </div>
    );
}
