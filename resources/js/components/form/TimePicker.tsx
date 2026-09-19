import { cn } from 'cn';
import { Check, Clock } from 'lucide-react';
import { DEFAULT_TIME_STEP_MINUTES, minutesFromTime } from '@/components/form/time-format';
import { useTimePicker } from '@/components/form/use-time-picker';
import { Input } from '@/components/ui/input';
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover';
import { keepScrollableWhileModalOpen } from '@/lib/scrollable';

export type TimePickerMessages = {
    list: string;
    empty: string;
};

type Props = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    messages: TimePickerMessages;
    placeholder?: string;
    stepMinutes?: number;
    startsFrom?: string;
    durationLabel?: (minutes: number) => string;
    invalid?: boolean;
    describedBy?: string;
    className?: string;
};

type OptionProps = {
    id: string;
    label: string;
    duration: string | null;
    active: boolean;
    selected: boolean;
    onSelect: () => void;
};

function TimeOption({ id, label, duration, active, selected, onSelect }: OptionProps) {
    return (
        <li
            id={id}
            role="option"
            aria-selected={selected}
            onClick={onSelect}
            className={cn(
                'flex min-h-11 cursor-default items-center gap-2 px-3 py-2.5 text-base whitespace-nowrap hover:bg-muted',
                active ? 'bg-muted text-foreground' : undefined,
            )}
        >
            <span className="shrink-0 tabular-nums">{label}</span>

            {duration === null ? null : (
                <span className="min-w-0 flex-1 truncate text-right text-sm text-muted-foreground tabular-nums">
                    {duration}
                </span>
            )}

            <span aria-hidden="true" className="ml-auto flex size-4 shrink-0 items-center justify-center">
                {selected ? <Check className="size-4 text-primary" /> : null}
            </span>
        </li>
    );
}

export function TimePicker({
    id,
    label,
    value,
    onChange,
    messages,
    placeholder,
    stepMinutes = DEFAULT_TIME_STEP_MINUTES,
    startsFrom,
    durationLabel,
    invalid = false,
    describedBy,
    className,
}: Props) {
    const picker = useTimePicker({ id, value, onChange, stepMinutes, startsFrom });
    const startMinutes = startsFrom === undefined ? null : minutesFromTime(startsFrom);

    function durationFrom(optionMinutes: number): string | null {
        if (startMinutes === null || durationLabel === undefined) {
            return null;
        }

        return durationLabel(optionMinutes - startMinutes);
    }

    return (
        <div ref={picker.rootRef} onBlur={picker.onBlur}>
            <Popover open={picker.open} onOpenChange={picker.onOpenChange}>
                <PopoverAnchor asChild>
                    <div className="relative">
                        <Input
                            ref={picker.inputRef}
                            id={id}
                            role="combobox"
                            aria-expanded={picker.open}
                            aria-controls={picker.listId}
                            aria-autocomplete="list"
                            aria-activedescendant={picker.activeOptionId}
                            aria-label={label}
                            aria-invalid={invalid}
                            aria-describedby={describedBy}
                            autoComplete="off"
                            autoCorrect="off"
                            spellCheck={false}
                            placeholder={placeholder}
                            value={picker.query}
                            onChange={picker.onQueryChange}
                            onKeyDown={picker.onKeyDown}
                            onFocus={picker.onFocus}
                            onClick={picker.openList}
                            className={cn('min-h-11 pr-9 tabular-nums', className)}
                        />

                        <Clock
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                    </div>
                </PopoverAnchor>

                <PopoverContent
                    role="presentation"
                    align="start"
                    sideOffset={6}
                    collisionPadding={12}
                    onOpenAutoFocus={(event) => event.preventDefault()}
                    onCloseAutoFocus={(event) => event.preventDefault()}
                    onInteractOutside={picker.onInteractOutside}
                    onMouseDown={(event) => event.preventDefault()}
                    className="w-auto max-w-[calc(100vw-1.5rem)] min-w-(--radix-popover-trigger-width) gap-0 overflow-hidden p-0 shadow-lg"
                >
                    {picker.options.length === 0 ? (
                        <p role="status" className="px-3 py-2.5 text-sm text-muted-foreground">
                            {messages.empty}
                        </p>
                    ) : null}

                    <ul
                        ref={keepScrollableWhileModalOpen}
                        id={picker.listId}
                        role="listbox"
                        aria-label={messages.list}
                        className="max-h-[min(18rem,var(--radix-popover-content-available-height,100svh))] overflow-y-auto overscroll-contain"
                    >
                        {picker.options.map((option, index) => (
                            <TimeOption
                                key={option.value}
                                id={picker.optionId(index)}
                                label={option.label}
                                duration={durationFrom(option.minutes)}
                                active={index === picker.activeIndex}
                                selected={option.value === value}
                                onSelect={() => picker.selectOption(option)}
                            />
                        ))}
                    </ul>
                </PopoverContent>
            </Popover>
        </div>
    );
}
