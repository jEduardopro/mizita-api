import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { TileBackdrop } from '@/components/auth/TileBackdrop';

type Props = {
    /** The tab title. The app name is appended by the title callback in app.tsx. */
    title: string;
    children: ReactNode;
};

/**
 * No `max-w` wrapper on purpose: the gutter around the sheet is the same few
 * pixels on every monitor, which is what keeps it anchored to the edge instead
 * of drifting inwards on a wide screen.
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
                {/* Below `lg` the sheet takes the full width: there is no room for
                    a mosaic margin worth showing. */}
                <div className="flex w-full flex-col lg:w-[28rem] lg:shrink-0 motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-3 motion-safe:duration-700">
                    {children}
                </div>
            </main>
        </div>
    );
}
