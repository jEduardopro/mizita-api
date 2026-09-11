import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { TileBackdrop } from '@/components/auth/TileBackdrop';

type Props = {
    /** The tab title. The app name is appended by the title callback in app.tsx. */
    title: string;
    /** The sheet. Everything the visitor came here to do lives inside it. */
    children: ReactNode;
};

/**
 * The shell for the screen that has nothing to sell: a wall of tiles, and a
 * sheet standing on the left edge of it.
 *
 * Signing in is a return, not a decision. Someone arriving here already chose
 * this product, so where registration spends its left column on a promise, this
 * one spends the whole window on the brand's own texture and puts the sheet
 * nearest the edge a returning visitor's eye starts from.
 *
 * There is no header and no centred container, and both absences are the point.
 * The sheet carries the wordmark and the phone number itself, so nothing floats
 * on the mosaic outside it; and with no `max-w` wrapper the gutter around the
 * sheet is the same few pixels on every monitor, which is what keeps it anchored
 * to the edge instead of drifting inwards on a wide screen. Everything the sheet
 * does not cover is mosaic.
 *
 * `min-h-svh` and not `h-svh`: in a short window the content pushes and the page
 * scrolls, instead of the sheet clipping what it holds.
 */
export function AuthTileLayout({ title, children }: Props) {
    return (
        <div className="relative min-h-svh text-foreground">
            <Head title={title} />

            <TileBackdrop />

            <main className="flex min-h-svh w-full items-stretch p-3 sm:p-5 lg:p-6">
                {/*
                 * Below `lg` the sheet takes the full width, the way it does on a
                 * phone: there is no room for a mosaic margin worth showing, and
                 * a form squeezed into a column beside one would be worse than no
                 * mosaic at all.
                 */}
                <div className="flex w-full flex-col lg:w-[28rem] lg:shrink-0 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-3 motion-safe:duration-700">
                    {children}
                </div>
            </main>
        </div>
    );
}
