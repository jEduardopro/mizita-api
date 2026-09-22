import { DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ITEMS_STEP } from './charge-steps';
import { ChargeFlowActions } from './ChargeFlowActions';
import { ChargeFlowHeaderRow } from './ChargeFlowHeaderRow';
import { ChargeItemsStep } from './ChargeItemsStep';
import { ChargePaymentMethodStep } from './ChargePaymentMethodStep';
import type { ChargeFormSurfaceProps } from './use-charge-form';

export function ChargeDialogForm({
    form,
    title,
    actionLabel,
    backLabel,
    onBack,
    onCancel,
    onSubmit,
}: ChargeFormSurfaceProps) {
    return (
        <form onSubmit={onSubmit} className="grid gap-4">
            <DialogHeader>
                <ChargeFlowHeaderRow backLabel={backLabel} onBack={onBack}>
                    <DialogTitle>{title}</DialogTitle>
                </ChargeFlowHeaderRow>
            </DialogHeader>

            <div className="max-h-[calc(100svh-14rem)] overflow-y-auto overscroll-contain px-1">
                {form.step === ITEMS_STEP ? (
                    <ChargeItemsStep form={form} />
                ) : (
                    <ChargePaymentMethodStep form={form} />
                )}
            </div>

            <DialogFooter>
                <ChargeFlowActions
                    actionLabel={actionLabel}
                    isSubmitting={form.isSubmitting}
                    onCancel={onCancel}
                />
            </DialogFooter>
        </form>
    );
}
