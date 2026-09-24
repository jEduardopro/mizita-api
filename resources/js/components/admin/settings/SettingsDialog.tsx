import { X, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { FormDensityProvider } from '@/components/form/form-density';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { useIsDesktop } from '@/hooks/use-is-desktop';

export type SettingsDialogItem<TId extends string> = {
    id: TId;
    label: string;
    icon: LucideIcon;
};

type NavProps<TId extends string> = {
    navLabel: string;
    items: readonly SettingsDialogItem<TId>[];
    activeId: TId;
    onActiveChange: (id: TId) => void;
};

function SettingsDialogNav<TId extends string>({
    navLabel,
    items,
    activeId,
    onActiveChange,
}: NavProps<TId>) {
    return (
        <nav aria-label={navLabel} className="-mx-5 md:mx-0">
            <ul className="flex gap-2 overflow-x-auto px-5 pb-3 [scrollbar-width:none] md:flex-col md:gap-0.5 md:overflow-visible md:px-0 md:pb-0 [&::-webkit-scrollbar]:hidden">
                {items.map((item) => {
                    const Icon = item.icon;
                    const isActive = item.id === activeId;

                    return (
                        <li key={item.id} className="shrink-0">
                            <button
                                type="button"
                                aria-current={isActive ? 'page' : undefined}
                                onClick={() => onActiveChange(item.id)}
                                className="flex min-h-11 w-full items-center gap-2.5 rounded-full border border-border px-4 text-left text-sm whitespace-nowrap text-foreground/80 transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 aria-[current=page]:border-transparent aria-[current=page]:bg-muted aria-[current=page]:font-medium aria-[current=page]:text-foreground md:rounded-lg md:border-transparent md:px-3 md:py-2 md:whitespace-normal"
                            >
                                <Icon aria-hidden="true" className="size-4 shrink-0" />
                                {item.label}
                            </button>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

type FrameProps<TId extends string> = NavProps<TId> & {
    title: ReactNode;
    closeLabel: string;
    identity: ReactNode;
    children: ReactNode;
};

function SettingsDialogFrame<TId extends string>({
    title,
    closeLabel,
    identity,
    children,
    ...nav
}: FrameProps<TId>) {
    return (
        <>
            <div className="flex shrink-0 items-center justify-between gap-2 px-5 pt-3 md:px-6 md:pt-5">
                {title}

                <DialogClose asChild>
                    <Button type="button" variant="ghost" aria-label={closeLabel} className="-mr-3 size-11 p-0">
                        <X aria-hidden="true" className="size-5" />
                    </Button>
                </DialogClose>
            </div>

            <div className="flex min-h-0 flex-1 flex-col md:grid md:grid-cols-[15rem_minmax(0,1fr)] md:grid-rows-[minmax(0,1fr)] md:pb-4">
                <aside className="flex shrink-0 flex-col gap-3 border-b border-border px-5 pt-2 md:gap-5 md:overflow-y-auto md:border-r md:border-b-0 md:px-4 md:pt-4">
                    {identity}

                    <SettingsDialogNav {...nav} />
                </aside>

                <div className="flex min-h-0 flex-1 flex-col">{children}</div>
            </div>
        </>
    );
}

type Props<TId extends string> = NavProps<TId> & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    closeLabel: string;
    identity: ReactNode;
    children: ReactNode;
};

const TITLE_CLASSES = 'text-lg font-semibold tracking-tight';

export function SettingsDialog<TId extends string>({
    open,
    onOpenChange,
    title,
    ...frame
}: Props<TId>) {
    const isDesktop = useIsDesktop();

    if (isDesktop) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent
                    showCloseButton={false}
                    aria-describedby={undefined}
                    onOpenAutoFocus={(event) => event.preventDefault()}
                    className="flex h-[min(46rem,calc(100svh-4rem))] flex-col gap-0 overflow-hidden p-0 sm:max-w-[min(56rem,calc(100%-2rem))]"
                >
                    <FormDensityProvider density="compact">
                        <SettingsDialogFrame
                            {...frame}
                            title={<DialogTitle className={TITLE_CLASSES}>{title}</DialogTitle>}
                        />
                    </FormDensityProvider>
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                showCloseButton={false}
                aria-describedby={undefined}
                onOpenAutoFocus={(event) => event.preventDefault()}
                className="gap-0 overflow-hidden rounded-t-2xl pb-[env(safe-area-inset-bottom)] data-[side=bottom]:h-[calc(100svh-1.5rem)]"
            >
                <SettingsDialogFrame
                    {...frame}
                    title={<SheetTitle className={TITLE_CLASSES}>{title}</SheetTitle>}
                />
            </SheetContent>
        </Sheet>
    );
}
