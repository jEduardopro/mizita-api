import type { ComboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import { Button } from '@/components/ui/button';
import { keepScrollableWhileModalOpen } from '@/lib/scrollable';
import type { OptionsFilterOption } from './OptionsFilterChip';

const SKELETON_ROWS = [0, 1, 2];

export type OptionsFilterListMessages = {
    empty: string;
    error: string;
    retry: string;
};

type Props = {
    label: string;
    options: readonly OptionsFilterOption[];
    selected: readonly string[];
    onToggle: (value: string) => void;
    status: ComboboxOptionsStatus;
    onRetry?: () => void;
    messages: OptionsFilterListMessages;
};

export function OptionsFilterList({ label, options, selected, onToggle, status, onRetry, messages }: Props) {
    if (status === 'pending') {
        return (
            <div aria-busy="true" className="grid shrink-0 gap-2 p-3">
                {SKELETON_ROWS.map((row) => (
                    <span key={row} aria-hidden="true" className="h-7 rounded-md bg-muted motion-safe:animate-pulse" />
                ))}
            </div>
        );
    }

    if (status === 'error') {
        return (
            <div className="grid shrink-0 gap-3 p-3">
                <p role="alert" className="text-sm text-muted-foreground">
                    {messages.error}
                </p>

                {onRetry === undefined ? null : (
                    <Button type="button" variant="outline" onClick={onRetry} className="h-11 justify-self-start px-4 md:h-9">
                        {messages.retry}
                    </Button>
                )}
            </div>
        );
    }

    if (options.length === 0) {
        return (
            <p role="status" className="shrink-0 px-3 py-4 text-sm text-muted-foreground">
                {messages.empty}
            </p>
        );
    }

    return (
        <ul
            ref={keepScrollableWhileModalOpen}
            aria-label={label}
            className="max-h-72 min-h-0 overflow-y-auto overscroll-contain p-1.5"
        >
            {options.map((option) => (
                <li key={option.value}>
                    <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 text-base hover:bg-muted has-[:focus-visible]:bg-muted md:min-h-9 md:text-sm">
                        <input
                            type="checkbox"
                            checked={selected.includes(option.value)}
                            onChange={() => onToggle(option.value)}
                            className="size-4.5 shrink-0 accent-primary md:size-4"
                        />
                        <span className="min-w-0 flex-1 truncate">{option.label}</span>
                    </label>
                </li>
            ))}
        </ul>
    );
}
