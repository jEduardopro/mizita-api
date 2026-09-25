import { cn } from 'cn';
import type { ReactNode } from 'react';
import { FormDensityProvider } from '@/components/form/form-density';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useIsDesktop } from '@/hooks/use-is-desktop';

type Props = {
    title: string;
    onClose: () => void;
    footer: ReactNode;
    children: ReactNode;
    className?: string;
};

function preventAutoFocus(event: Event) {
    event.preventDefault();
}

export function TeamSurface({ title, onClose, footer, children, className }: Props) {
    const isDesktop = useIsDesktop();

    function closeOnDismiss(open: boolean) {
        if (! open) {
            onClose();
        }
    }

    if (isDesktop) {
        return (
            <Dialog open onOpenChange={closeOnDismiss}>
                <DialogContent
                    aria-describedby={undefined}
                    onOpenAutoFocus={preventAutoFocus}
                    className={cn('grid gap-4', className)}
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                    </DialogHeader>

                    <div className="-mx-1 max-h-[calc(100svh-14rem)] overflow-y-auto overscroll-contain px-1 pb-1">
                        <FormDensityProvider density="compact">{children}</FormDensityProvider>
                    </div>

                    <DialogFooter>{footer}</DialogFooter>
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Sheet open onOpenChange={closeOnDismiss}>
            <SheetContent
                side="bottom"
                aria-describedby={undefined}
                onOpenAutoFocus={preventAutoFocus}
                className="flex max-h-[92svh] flex-col pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                </SheetHeader>

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-1">
                    {children}
                </div>

                <SheetFooter className="flex-row justify-end gap-2 border-t border-border">
                    {footer}
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
