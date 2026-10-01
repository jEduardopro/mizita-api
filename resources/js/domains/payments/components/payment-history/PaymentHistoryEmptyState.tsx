import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    icon: LucideIcon;
    title: string;
    body: string;
    action?: ReactNode;
};

export function PaymentHistoryEmptyState({ icon: Icon, title, body, action }: Props) {
    return (
        <div className="grid justify-items-center gap-3 rounded-xl border border-dashed border-border px-5 py-12 text-center">
            <Icon aria-hidden="true" className="size-6 text-muted-foreground" />

            <p className="font-medium">{title}</p>

            <p className="max-w-sm text-sm text-pretty text-muted-foreground">{body}</p>

            {action}
        </div>
    );
}
