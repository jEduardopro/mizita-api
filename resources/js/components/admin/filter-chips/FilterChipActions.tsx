import { Button } from '@/components/ui/button';

export type FilterChipActionsMessages = {
    cancel: string;
    apply: string;
};

type Props = {
    canApply: boolean;
    onCancel: () => void;
    onApply: () => void;
    messages: FilterChipActionsMessages;
};

const ACTION_CLASS = 'h-11 flex-1 px-4 md:h-9 md:flex-none';

export function FilterChipActions({ canApply, onCancel, onApply, messages }: Props) {
    return (
        <div className="flex shrink-0 gap-2 border-t border-border p-3 md:justify-end">
            <Button type="button" variant="outline" onClick={onCancel} className={ACTION_CLASS}>
                {messages.cancel}
            </Button>

            <Button type="button" variant="brand" disabled={! canApply} onClick={onApply} className={ACTION_CLASS}>
                {messages.apply}
            </Button>
        </div>
    );
}
