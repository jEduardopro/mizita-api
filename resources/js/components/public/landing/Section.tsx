import { cn } from 'cn';
import type { ReactNode } from 'react';

export type SectionTone = 'default' | 'muted' | 'brand';

const surfaces: Record<SectionTone, string> = {
    default: 'bg-background',
    muted: 'bg-surface-muted',
    brand: 'bg-surface-brand',
};

type Props = {
    /**
     * The anchor the header menu scrolls to. A section with an id becomes a
     * focus target and takes its accessible name from its heading, so that
     * heading must carry `id="<id>-heading"`.
     */
    id?: string;
    tone?: SectionTone;
    className?: string;
    children: ReactNode;
};

export function Section({ id, tone = 'default', className, children }: Props) {
    const isAnchor = id !== undefined;

    return (
        <section
            id={id}
            tabIndex={isAnchor ? -1 : undefined}
            aria-labelledby={isAnchor ? `${id}-heading` : undefined}
            className={cn('outline-none', surfaces[tone], isAnchor && 'scroll-mt-14', className)}
        >
            <div className="mx-auto w-full max-w-5xl px-5 py-16 sm:px-8 sm:py-24">{children}</div>
        </section>
    );
}
