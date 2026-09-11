import { Link } from '@inertiajs/react';
import { cn } from 'cn';

/**
 * The steps the wordmark is set at. It is a prop rather than a type class passed
 * in by each caller, so the four places that render the mark all pick from the
 * same ramp instead of inventing sizes.
 */
export type WordmarkSize = 'sm' | 'default' | 'lg' | 'xl';

const sizes: Record<WordmarkSize, string> = {
    sm: 'text-sm',
    default: 'text-base',
    lg: 'text-xl',
    xl: 'text-2xl',
};

type Props = {
    name: string;
    /** Where the wordmark leads. Defaults to the public landing page. */
    href?: string;
    size?: WordmarkSize;
    className?: string;
};

/**
 * The product signature: the name set tight, followed by a small square that
 * stands for a booked block on an agenda column.
 *
 * The square is the one place the brand blue appears in the chrome. It is the
 * mark, so it carries the colour; everything else in the header stays quiet.
 *
 * It is sized in `em`, so it tracks the type at every step instead of needing a
 * value per size: `0.375em` is the same 6px it has always been at 16px.
 */
export function Wordmark({ name, href = '/', size = 'default', className }: Props) {
    return (
        <Link
            href={href}
            className={cn(
                'group inline-flex items-baseline gap-[0.375em] rounded-md font-medium tracking-[-0.04em] text-foreground outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                sizes[size],
                className,
            )}
        >
            {name.toLowerCase()}
            <span
                aria-hidden="true"
                className="size-[0.375em] rounded-[0.125em] bg-primary/70 transition-colors group-hover:bg-primary"
            />
        </Link>
    );
}
