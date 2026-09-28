import { cn } from 'cn';
import type { ReactNode } from 'react';

type Props = {
    surfaceClassName: string;
    className?: string;
    children?: ReactNode;
};

export function BookingHeroFrame({ surfaceClassName, className, children }: Props) {
    return (
        <div className={cn('relative w-full overflow-hidden sm:rounded-2xl', surfaceClassName, className)}>
            {children}

            <span
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-background to-transparent"
            />
        </div>
    );
}
