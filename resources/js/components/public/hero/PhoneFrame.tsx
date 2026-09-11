import { cn } from 'cn';
import type { ReactNode } from 'react';

/** The bezel around the screen, on every side. */
export const PHONE_BEZEL = 12;

/** The device's own strip at the top of the screen, above the app's content. */
export const PHONE_STATUS_BAR = 22;

type Props = {
    /** The screen, in px, excluding the bezel. The frame is sized from it. */
    screenWidth: number;
    /** The screen's full height, status bar included. */
    screenHeight: number;
    className?: string;
    /** The app's screen, rendered below the status bar. */
    children: ReactNode;
};

/**
 * A device shell for the product screens the landing page shows: a bezel, a
 * speaker pill, a status strip and the screen itself.
 *
 * The bezel changes direction between themes, because a single hard-coded grey
 * only ever reads on one of them. In light it is the deepest step of the brand
 * ramp, darker than the screen it holds; in dark it is the muted surface, one
 * step lighter than the screen and two above the page. Either way the device
 * reads as a device, and the brand blue stays spent on actions rather than on
 * a piece of furniture.
 *
 * It is chrome, not content: nothing inside is focusable and the collage that
 * composes these is hidden from assistive technology as a whole.
 */
export function PhoneFrame({ screenWidth, screenHeight, className, children }: Props) {
    return (
        <div
            className={cn(
                'relative rounded-4xl bg-brand-950 p-3 shadow-2xl shadow-brand-950/25 ring-1 ring-white/10 ring-inset dark:bg-muted dark:shadow-black/50 dark:ring-white/15',
                className,
            )}
            style={{
                width: screenWidth + PHONE_BEZEL * 2,
                height: screenHeight + PHONE_BEZEL * 2,
            }}
        >
            <span className="absolute top-[5px] left-1/2 h-1 w-9 -translate-x-1/2 rounded-full bg-white/25" />

            <div className="flex h-full w-full flex-col overflow-hidden rounded-3xl bg-card">
                {/*
                 * The status strip carries no copy on purpose — a fake clock in
                 * the wrong language is the kind of detail that gives a mockup
                 * away. Shapes say "this is a phone" without saying anything.
                 */}
                <div
                    className="flex shrink-0 items-center justify-between px-3.5"
                    style={{ height: PHONE_STATUS_BAR }}
                >
                    <span className="h-1 w-6 rounded-full bg-foreground/15" />
                    <span className="flex items-center gap-0.5">
                        <span className="h-1 w-1 rounded-full bg-foreground/15" />
                        <span className="h-1 w-1 rounded-full bg-foreground/15" />
                        <span className="h-1 w-3 rounded-full bg-foreground/15" />
                    </span>
                </div>

                <div className="min-h-0 flex-1">{children}</div>
            </div>
        </div>
    );
}
