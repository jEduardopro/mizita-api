import { cn } from 'cn';
import type { ReactNode } from 'react';

type Props = {
    id: string;
    title: string;
    className?: string;
    children: ReactNode;
};

export function BookingSection({ id, title, className, children }: Props) {
    return (
        <section
            id={id}
            tabIndex={-1}
            aria-labelledby={`${id}-heading`}
            className={cn('scroll-mt-16 outline-none', className)}
        >
            <h2
                id={`${id}-heading`}
                className="font-heading text-[clamp(1.25rem,3.2vw,1.625rem)] leading-tight font-medium tracking-[-0.03em] text-balance"
            >
                {title}
            </h2>

            <div className="mt-4">{children}</div>
        </section>
    );
}
