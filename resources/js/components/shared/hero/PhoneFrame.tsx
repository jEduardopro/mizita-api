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
 * The bezel changes direction between themes, because a single hard-coded grey
 * only reads on one of them: darker than the screen in light, lighter in dark.
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
                {/* No copy on purpose: a fake clock in the wrong language is what
                    gives a mockup away. */}
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
