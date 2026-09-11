import { Link } from '@inertiajs/react';
import { cn } from 'cn';

type Props = {
    name: string;
    /** Where the wordmark leads. Defaults to the public landing page. */
    href?: string;
    className?: string;
};

/**
 * The product signature: the name set tight, followed by a small square that
 * stands for a booked block on an agenda column.
 */
export function Wordmark({ name, href = '/', className }: Props) {
    return (
        <Link
            href={href}
            className={cn(
                'group inline-flex items-baseline gap-1.5 rounded-md text-base font-medium tracking-[-0.04em] text-foreground outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                className,
            )}
        >
            {name.toLowerCase()}
            <span
                aria-hidden="true"
                className="size-1.5 rounded-[2px] bg-foreground/30 transition-colors group-hover:bg-foreground"
            />
        </Link>
    );
}
