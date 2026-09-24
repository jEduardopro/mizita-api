import { cn } from 'cn';
import { ChevronLeft } from 'lucide-react';
import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type BackAction = {
    label: string;
    onBack: () => void;
};

type Props = {
    title: string;
    back?: BackAction;
    children: ReactNode;
};

export function SettingsPane({ title, back, children }: Props) {
    const headingId = useId();
    const headingRef = useRef<HTMLHeadingElement>(null);
    const isSubScreen = back !== undefined;

    useEffect(() => {
        if (isSubScreen) {
            headingRef.current?.focus({ preventScroll: true });
        }
    }, [isSubScreen]);

    return (
        <section aria-labelledby={headingId} className="flex min-h-0 flex-1 flex-col">
            <header className="flex min-h-14 shrink-0 items-center gap-1 px-5 pt-2 md:px-6 md:pt-4">
                {back === undefined ? null : (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={back.onBack}
                        aria-label={back.label}
                        className="-ml-3 size-11 p-0"
                    >
                        <ChevronLeft aria-hidden="true" className="size-5" />
                    </Button>
                )}

                <h3
                    ref={headingRef}
                    id={headingId}
                    tabIndex={-1}
                    className="text-sm font-semibold text-foreground outline-none"
                >
                    {title}
                </h3>
            </header>

            {children}
        </section>
    );
}

type RegionProps = {
    children: ReactNode;
    className?: string;
};

export function SettingsPaneBody({ children, className }: RegionProps) {
    return (
        <div
            className={cn(
                'min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-6 md:px-6',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function SettingsPaneFooter({ children, className }: RegionProps) {
    return (
        <div
            className={cn(
                'flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-3 md:px-6 md:py-4',
                className,
            )}
        >
            {children}
        </div>
    );
}
