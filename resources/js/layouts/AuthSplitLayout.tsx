import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AuthHeader } from '@/components/auth/AuthHeader';
import { HeroCollage } from '@/components/shared/hero/HeroCollage';

type Props = {
    /** The tab title. The app name is appended by the title callback in app.tsx. */
    title: string;
    /** The promise the left column carries. The largest type on the page. */
    heading: string;
    /** What actually changes for the visitor, in one or two sentences. */
    description: string;
    /** The header's right-hand action, the way out of this screen. */
    action: ReactNode;
    /** The card. Everything the visitor came here to do lives inside it. */
    children: ReactNode;
};

/**
 * The shell for the two screens that are also a pitch: an account is the one
 * thing a visitor arrives at without having been sold yet, so the left column
 * keeps selling while the right column takes the details.
 *
 * It does not replace `AuthLayout`. Password recovery is a task someone is
 * already committed to and has nothing to sell; it keeps the single narrow
 * column it has.
 *
 * The ground is `surface-muted` and the card inside `children` is `card`, which
 * is the whole reason the split reads as a form rather than as a page: the card
 * is the only white thing on the screen. Both tokens invert per theme, so the
 * relationship holds in dark mode without a second rule.
 */
export function AuthSplitLayout({ title, heading, description, action, children }: Props) {
    return (
        <div className="flex min-h-svh flex-col bg-surface-muted text-foreground">
            <Head title={title} />

            <AuthHeader action={action} />

            {/*
             * `content-start` and `items-start` together are what keep the card
             * still. The card changes height when it swaps panels, and anything
             * that centres a row would slide it up the screen the moment someone
             * clicks — the one thing a card that "switches in place" must not do.
             * They also stop the leftover height of a tall viewport being poured
             * into the gap between the copy and the card on a phone.
             */}
            <main className="mx-auto grid w-full max-w-6xl flex-1 content-start items-start gap-10 px-5 pt-4 pb-14 sm:px-8 lg:grid-cols-[1fr_25rem] lg:gap-16 lg:pt-8 lg:pb-20 xl:gap-24">
                <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-500">
                    <h1 className="max-w-[15ch] font-heading text-[clamp(2.25rem,5.5vw,3.75rem)] leading-[0.96] font-medium tracking-[-0.045em] text-balance">
                        {heading}
                    </h1>

                    <p className="mt-5 max-w-md text-base leading-relaxed text-muted-foreground">
                        {description}
                    </p>

                    {/*
                     * The collage is a 440×520 stage. Below `lg` the two columns
                     * stack, and dropping it there is what keeps the card on the
                     * first screen instead of a phone drawing pushing it under
                     * the fold. The headline and the promise still travel.
                     */}
                    <div className="mt-10 hidden lg:block">
                        <HeroCollage />
                    </div>
                </div>

                <div className="motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-3 motion-safe:duration-700">
                    {children}
                </div>
            </main>
        </div>
    );
}
