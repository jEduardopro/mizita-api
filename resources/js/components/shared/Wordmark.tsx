import { Link } from '@inertiajs/react';
import { cn } from 'cn';

/**
 * The steps the wordmark is set at. It is a prop rather than a height class
 * passed in by each caller, so the six places that render the mark all pick from
 * the same ramp instead of inventing sizes.
 */
export type WordmarkSize = 'sm' | 'default' | 'lg' | 'xl';

const sizes: Record<WordmarkSize, string> = {
    sm: 'h-4',
    default: 'h-5',
    lg: 'h-6',
    xl: 'h-8',
};

/**
 * The artwork is served straight out of `public/`, so it is referenced by URL
 * rather than imported: it is chrome on every page and does not need to be part
 * of the bundle graph to be cached.
 *
 * The files are named for the ink, not for the theme — dark ink is what a light
 * surface needs.
 */
const darkInkSource = '/images/brand/mizita-logo-dark.png';
const lightInkSource = '/images/brand/mizita-logo-light.png';

/**
 * The artwork's own pixel size. Declaring it on every `<img>` is what lets the
 * browser reserve the box from the aspect ratio, so a sticky header never
 * reflows once the file arrives.
 */
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
    /**
     * The accessible name of the mark. The artwork carries no text layer, so the
     * app name still comes from the server rather than being written in here.
     */
    name: string;
    size?: WordmarkSize;
    className?: string;
};

/**
 * The artwork on its own, leading nowhere.
 *
 * It exists for the screens a person is not allowed to leave yet — onboarding,
 * where the header mark would otherwise be a link to a dashboard the server
 * bounces straight back here. A mark that goes nowhere is honest; a link that
 * returns you to where you started is not.
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
    /**
     * The accessible name of the mark. The artwork carries no text layer, so the
     * app name still comes from the server rather than being written in here.
     */
    name: string;
    /** Where the wordmark leads. Defaults to the public landing page. */
    href?: string;
    size?: WordmarkSize;
    className?: string;
};

/**
 * The product signature: the brand artwork, sized by the ramp above.
 *
 * Both inks are rendered and the `dark` variant picks one, so the right mark is
 * shown without a script deciding — the swap survives server-rendered HTML and a
 * theme that is set before paint. The trade is one extra request the browser
 * makes once and caches; the alternative is a flash of the wrong ink.
 *
 * The square that closes the mark stands for a booked block on an agenda column.
 * It used to be drawn in CSS next to live text; the artwork keeps it, which is
 * why it is no longer a separate element here.
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
