import { cn } from 'cn';
import { Plus, X } from 'lucide-react';
import { useRef, type ReactNode } from 'react';
import { Popover, PopoverAnchor, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

export type FilterChipMessages = {
    clear: string;
};

export type FilterChipProps = {
    label: string;
    valueLabel?: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onClear: () => void;
    messages: FilterChipMessages;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
};

type FilterChipState = 'inactive' | 'active' | 'open';

const LABEL_SEPARATOR = ':';

const CHIP_CLASS =
    'inline-flex h-11 max-w-full min-w-0 items-center rounded-full border text-sm text-foreground motion-safe:transition-colors md:h-8';

const CHIP_STATE_CLASSES: Record<FilterChipState, string> = {
    inactive: 'border-dashed border-muted-foreground/40 hover:bg-muted',
    active: 'border-transparent bg-muted hover:bg-[color-mix(in_oklch,var(--muted),var(--foreground)_5%)]',
    open: 'border-transparent bg-muted',
};

const TRIGGER_CLASS =
    'flex h-full min-w-0 items-center gap-1.5 rounded-full px-4 whitespace-nowrap outline-none focus-visible:ring-3 focus-visible:ring-ring/50 md:px-3';

const CLEAR_CLASS =
    'group/clear -ms-1 flex h-full w-11 shrink-0 items-center justify-center rounded-full text-muted-foreground outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 md:w-8';

const DEFAULT_CONTENT_CLASS =
    'w-[min(20rem,calc(100vw-1.5rem))] max-h-(--radix-popover-content-available-height) gap-0 rounded-xl p-0 shadow-lg';

function chipState(open: boolean, isActive: boolean): FilterChipState {
    if (open) {
        return 'open';
    }

    return isActive ? 'active' : 'inactive';
}

type ChipTextProps = {
    label: string;
    valueLabel: string | null;
};

function FilterChipText({ label, valueLabel }: ChipTextProps) {
    if (valueLabel === null) {
        return (
            <>
                <Plus aria-hidden="true" className="size-4 shrink-0 text-muted-foreground md:size-3.5" />
                <span className="truncate">{label}</span>
            </>
        );
    }

    return (
        <span className="truncate">
            <span className="text-muted-foreground">
                {label}
                {LABEL_SEPARATOR}
            </span>{' '}
            <span className="font-medium tabular-nums">{valueLabel}</span>
        </span>
    );
}

export function FilterChip({
    label,
    valueLabel = null,
    open,
    onOpenChange,
    onClear,
    messages,
    children,
    className,
    contentClassName,
}: FilterChipProps) {
    const triggerRef = useRef<HTMLButtonElement>(null);
    const isActive = valueLabel !== null;

    function handleClear() {
        onClear();
        triggerRef.current?.focus();
    }

    return (
        <Popover open={open} onOpenChange={onOpenChange}>
            <PopoverAnchor asChild>
                <div className={cn(CHIP_CLASS, CHIP_STATE_CLASSES[chipState(open, isActive)], className)}>
                    <PopoverTrigger asChild>
                        <button ref={triggerRef} type="button" className={cn(TRIGGER_CLASS, isActive && 'pe-1.5 md:pe-1')}>
                            <FilterChipText label={label} valueLabel={valueLabel} />
                        </button>
                    </PopoverTrigger>

                    {isActive ? (
                        <button type="button" onClick={handleClear} aria-label={messages.clear} className={CLEAR_CLASS}>
                            <span className="flex size-6 items-center justify-center rounded-full motion-safe:transition-colors group-hover/clear:bg-foreground/10 md:size-5">
                                <X aria-hidden="true" className="size-3.5" />
                            </span>
                        </button>
                    ) : null}
                </div>
            </PopoverAnchor>

            <PopoverContent
                aria-label={label}
                align="start"
                sideOffset={6}
                collisionPadding={12}
                className={cn(DEFAULT_CONTENT_CLASS, contentClassName)}
            >
                {children}
            </PopoverContent>
        </Popover>
    );
}
