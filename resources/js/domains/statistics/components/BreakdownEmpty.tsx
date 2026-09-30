import type { LucideIcon } from 'lucide-react';

type Props = {
    icon: LucideIcon;
    message: string;
};

export function BreakdownEmpty({ icon: Icon, message }: Props) {
    return (
        <div className="grid justify-items-center gap-2 rounded-lg border border-dashed border-border px-4 py-8 text-center">
            <Icon aria-hidden="true" className="size-5 text-muted-foreground" />

            <p className="max-w-56 text-sm text-pretty text-muted-foreground">{message}</p>
        </div>
    );
}
