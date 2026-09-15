import { cn } from 'cn';
import type { ReactNode } from 'react';

const columnClasses = {
    2: 'sm:grid-cols-2',
    3: 'sm:grid-cols-3',
} as const;

const SUBGRID_CLASSES =
    'sm:grid-rows-[auto_auto_auto] sm:gap-y-2 sm:*:grid sm:*:row-span-3 sm:*:grid-rows-subgrid';

type Props = {
    columns: keyof typeof columnClasses;
    className?: string;
    children: ReactNode;
};

export function FieldRow({ columns, className, children }: Props) {
    return (
        <div className={cn('grid gap-5', columnClasses[columns], SUBGRID_CLASSES, className)}>
            {children}
        </div>
    );
}
