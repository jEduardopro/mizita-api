import { Link } from '@inertiajs/react';
import { cn } from 'cn';

export type WordmarkSize = 'sm' | 'default' | 'lg' | 'xl';

const sizes: Record<WordmarkSize, string> = {
    sm: 'h-4',
    default: 'h-5',
    lg: 'h-6',
    xl: 'h-8',
};

const darkInkSource = '/images/brand/mizita-logo-dark.png';
const lightInkSource = '/images/brand/mizita-logo-light.png';

const intrinsicWidth = 584;
const intrinsicHeight = 130;

type ImageProps = {
    source: string;
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
    name: string;
    size?: WordmarkSize;
    className?: string;
};

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
    name: string;
    href?: string;
    size?: WordmarkSize;
    className?: string;
};

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
