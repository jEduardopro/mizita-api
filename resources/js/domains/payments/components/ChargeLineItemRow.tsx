import { cn } from 'cn';
import type { ReactNode } from 'react';
import { serviceColorClasses, type ServiceColor } from '@/lib/service-color';

type Props = {
    color: ServiceColor | null;
    name: ReactNode;
    amount: ReactNode;
    action?: ReactNode;
    message?: ReactNode;
};

export function ChargeLineItemRow({ color, name, amount, action, message }: Props) {
    return (
        <div className="grid gap-1">
            <div className="flex items-center gap-3">
                <span
                    aria-hidden="true"
                    className={cn(
                        'size-2.5 shrink-0 rounded-full',
                        color === null ? 'bg-muted-foreground/40' : serviceColorClasses[color].bar,
                    )}
                />

                <div className="min-w-0 flex-1">{name}</div>

                {amount}

                {action}
            </div>

            {message}
        </div>
    );
}
