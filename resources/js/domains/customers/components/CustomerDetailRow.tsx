import type { ReactNode } from 'react';

type Props = {
    label: string;
    value: ReactNode;
};

export function CustomerDetailRow({ label, value }: Props) {
    return (
        <div className="grid gap-1 border-b border-border py-4 first:pt-0 last:border-b-0 last:pb-0 sm:grid-cols-[minmax(0,9rem)_minmax(0,1fr)] sm:gap-6">
            <dt className="text-sm text-muted-foreground">{label}</dt>

            <dd className="text-sm text-pretty text-foreground">{value}</dd>
        </div>
    );
}
