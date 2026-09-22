import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import type { ChargeServiceLine } from './charge-form-values';
import { ITEMS_STEP } from './charge-steps';
import { ChargeDialogForm } from './ChargeDialogForm';
import { ChargeSheetForm } from './ChargeSheetForm';
import { useChargeForm, type ChargeFormSurfaceProps } from './use-charge-form';
import type { AppointmentPayment } from '../types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    appointmentId: string;
    customerName: string;
    serviceLine: ChargeServiceLine;
    currencyCode: string;
    existingPayment: AppointmentPayment | null;
    onPaid: (payment: AppointmentPayment) => void;
};

export function ChargeAppointmentDialog({
    open,
    onOpenChange,
    appointmentId,
    customerName,
    serviceLine,
    currencyCode,
    existingPayment,
    onPaid,
}: Props) {
    const { t } = useTranslation('admin');
    const isDesktop = useIsDesktop();

    const form = useChargeForm({
        open,
        appointmentId,
        customerName,
        serviceLine,
        currencyCode,
        existingPayment,
        onPaid: (payment) => {
            onOpenChange(false);
            onPaid(payment);
        },
    });

    const surface: ChargeFormSurfaceProps = {
        form,
        title: t('payments.title'),
        actionLabel:
            form.step === ITEMS_STEP ? t('payments.actions.continue') : t('payments.actions.pay'),
        backLabel: t('payments.back'),
        onBack: form.canGoBack ? form.goBack : () => onOpenChange(false),
        onCancel: () => onOpenChange(false),
        onSubmit: (event) => {
            event.preventDefault();
            void form.advance();
        },
    };

    if (isDesktop) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-md">
                    <ChargeDialogForm {...surface} />
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="flex max-h-[92svh] flex-col gap-0 rounded-t-2xl pb-[env(safe-area-inset-bottom)]"
            >
                <ChargeSheetForm {...surface} />
            </SheetContent>
        </Sheet>
    );
}
