import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { usePendingCustomerCreation } from '@/hooks/use-customer-creation';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { CreateCustomerDialogForm } from './CreateCustomerDialogForm';
import { CreateCustomerSheetForm } from './CreateCustomerSheetForm';
import type { CreateCustomerSurfaceProps } from './use-create-customer-surface';

export function CreateCustomerDialog() {
    const { request, dismiss } = usePendingCustomerCreation();
    const isDesktop = useIsDesktop();

    if (request === null) {
        return null;
    }

    function dismissOnClose(open: boolean) {
        if (! open) {
            dismiss();
        }
    }

    const surface: CreateCustomerSurfaceProps = {
        suggestedName: request.suggestedName,
        onCreated: (customer) => {
            request.onCreated(customer);
            dismiss();
        },
        onCancel: dismiss,
    };

    if (isDesktop) {
        return (
            <Dialog open onOpenChange={dismissOnClose}>
                <DialogContent
                    aria-describedby={undefined}
                    onOpenAutoFocus={(event) => event.preventDefault()}
                    className="sm:max-w-xl"
                >
                    <CreateCustomerDialogForm {...surface} />
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Sheet open onOpenChange={dismissOnClose}>
            <SheetContent
                side="bottom"
                aria-describedby={undefined}
                onOpenAutoFocus={(event) => event.preventDefault()}
                className="flex max-h-[92svh] flex-col pb-[env(safe-area-inset-bottom)]"
            >
                <CreateCustomerSheetForm {...surface} />
            </SheetContent>
        </Sheet>
    );
}
