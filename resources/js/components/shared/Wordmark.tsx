import { Link } from '@inertiajs/react';
import { cn } from 'cn';

export type WordmarkSize = 'sm' | 'default' | 'lg' | 'xl';

const sizes: Record<WordmarkSize, string> = {
    sm: 'h-4',
    default: 'h-5',
    lg: 'h-6',
    xl: 'h-8',
};

// Named for the ink, not the theme: dark ink is what a light surface needs.
const darkInkSource = '/images/brand/mizita-logo-dark.png';
const lightInkSource = '/images/brand/mizita-logo-light.png';

// Declared on every `<img>` so the browser reserves the box from the aspect
// ratio and a sticky header never reflows once the file arrives.
const intrinsicWidth = 584;
const intrinsicHeight = 130;

type ImageProps = {
    source: string;
    /**
     * Empty on the second copy: both inks are always in the document and only
     * one is visible, so the mark is announced once rather than twice.
     */
    alt: string;
    className: string;
};

function WordmarkImage({ source, alt, className }: ImageProps) {
    return (
        <img
            src={source}
            alt={alt}
            width={intrinsicWidth}
            height={intrinsicHeight}
            loading="eager"
            decoding="sync"
            className={cn('w-auto', className)}
        />
    );
}

type MarkProps = {
    /** The artwork carries no text layer, so the name comes from the server. */
    name: string;
    size?: WordmarkSize;
    className?: string;
};

/**
 * The artwork on its own, for the screens a person is not allowed to leave yet —
 * onboarding, where a link would only bounce off the server and come back here.
 */
export function WordmarkMark({ name, size = 'default', className }: MarkProps) {
    return (
        <span className={cn('inline-flex items-center', className)}>
            <WordmarkImage
                source={darkInkSource}
                alt={name}
                className={cn(sizes[size], 'dark:hidden')}
            />
            <WordmarkImage
                source={lightInkSource}
                alt=""
                className={cn(sizes[size], 'hidden dark:block')}
            />
        </span>
    );
}

type Props = {
    /** The artwork carries no text layer, so the name comes from the server. */
    name: string;
    href?: string;
    size?: WordmarkSize;
    className?: string;
};

/**
 * Both inks are rendered and the `dark` variant picks one, so the swap survives
 * server-rendered HTML and a theme set before paint, with no flash of the wrong
 * ink.
 */
export function Wordmark({ name, href = '/', size = 'default', className }: Props) {
    return (
        <Link
            href={href}
            className={cn(
                'inline-flex items-center rounded-md outline-none transition-opacity hover:opacity-70 focus-visible:ring-3 focus-visible:ring-ring/50',
                className,
            )}
        >
            <WordmarkMark name={name} size={size} />
        </Link>
    );
}
