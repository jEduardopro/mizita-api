import { cn } from 'cn';
import type { ReactNode } from 'react';

/**
 * The surface a section sits on. The page alternates between them so one section
 * ends where the next one begins without a rule having to say so.
 *
 * `brand` is the strongest surface the page has and there is exactly one of it,
 * reserved for the band that holds the last call to action.
 */
export type SectionTone = 'default' | 'muted' | 'brand';

const surfaces: Record<SectionTone, string> = {
    default: 'bg-background',
    muted: 'bg-surface-muted',
    brand: 'bg-surface-brand',
};

type Props = {
    /**
     * The anchor the header menu scrolls to. A section with an id is a navigation
     * destination, so it also gets `scroll-mt` to clear the sticky header, a
     * focus target for the menu to hand keyboard focus to, and its heading as its
     * accessible name — the heading must carry `id="<id>-heading"`.
     */
    id?: string;
    tone?: SectionTone;
    className?: string;
    children: ReactNode;
};

/**
 * One band of the page: the surface, the max width, the vertical rhythm and the
 * anchor wiring, in one place.
 *
 * The alternation is a property of the page, not of any one section, so it is
 * declared where the sections are composed rather than hand-tinted inside each
 * component. That is what stops it drifting the next time a section is restyled.
 */
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
