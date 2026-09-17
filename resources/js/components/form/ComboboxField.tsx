import { cn } from 'cn';
import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { ComboboxPanel, type ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import { useCombobox, type ComboboxOption } from '@/components/form/use-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverAnchor } from '@/components/ui/popover';

export type { ComboboxOption };
export { comboboxOptionsStatus, type ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';

type Messages = {
    empty: string;
    optionsError: string;
    retry: string;
    results: string;
};

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
                <Popover open={combobox.open} onOpenChange={combobox.onOpenChange}>
                    <PopoverAnchor asChild>
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
                    </PopoverAnchor>

                    <ComboboxPanel
                        listId={combobox.listId}
                        label={label}
                        options={combobox.filteredOptions}
                        selectedValue={value}
                        activeIndex={combobox.activeIndex}
                        optionId={combobox.optionId}
                        onSelect={combobox.selectOption}
                        onInteractOutside={combobox.onInteractOutside}
                        status={optionsStatus}
                        emptyMessage={messages.empty}
                    />
                </Popover>

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
