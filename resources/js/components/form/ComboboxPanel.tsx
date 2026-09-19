import { cn } from 'cn';
import { Check } from 'lucide-react';
import type { ComponentProps } from 'react';
import type { ComboboxOption } from '@/components/form/use-combobox';
import { PopoverContent } from '@/components/ui/popover';
import { keepScrollableWhileModalOpen } from '@/lib/scrollable';

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

const SKELETON_ROWS = [0, 1, 2];

type PopoverContentProps = ComponentProps<typeof PopoverContent>;

type Props = {
    listId: string;
    label: string;
    options: readonly ComboboxOption[];
    selectedValue: string | null;
    activeIndex: number;
    optionId: (index: number) => string;
    onSelect: (option: ComboboxOption) => void;
    onInteractOutside: PopoverContentProps['onInteractOutside'];
    status: ComboboxOptionsStatus;
    emptyMessage: string;
};

export function ComboboxPanel({
    listId,
    label,
    options,
    selectedValue,
    activeIndex,
    optionId,
    onSelect,
    onInteractOutside,
    status,
    emptyMessage,
}: Props) {
    return (
        <PopoverContent
            role="presentation"
            align="start"
            sideOffset={6}
            collisionPadding={12}
            onOpenAutoFocus={(event) => event.preventDefault()}
            onCloseAutoFocus={(event) => event.preventDefault()}
            onInteractOutside={onInteractOutside}
            onMouseDown={(event) => event.preventDefault()}
            className="w-(--radix-popover-trigger-width) gap-0 overflow-hidden p-0 shadow-lg"
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
                ref={keepScrollableWhileModalOpen}
                id={listId}
                role="listbox"
                aria-label={label}
                aria-busy={status === 'pending'}
                className="max-h-[min(18rem,var(--radix-popover-content-available-height,100svh))] overflow-y-auto overscroll-contain"
            >
                {options.map((option, index) => (
                    <li
                        key={option.value}
                        id={optionId(index)}
                        role="option"
                        aria-selected={option.value === selectedValue}
                        onClick={() => onSelect(option)}
                        className={cn(
                            'flex min-h-11 cursor-default items-center gap-2 px-3 py-2.5 text-base hover:bg-muted',
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
        </PopoverContent>
    );
}
