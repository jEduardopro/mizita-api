import { SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { ITEMS_STEP } from './charge-steps';
import { ChargeFlowActions } from './ChargeFlowActions';
import { ChargeFlowHeaderRow } from './ChargeFlowHeaderRow';
import { ChargeItemsStep } from './ChargeItemsStep';
import { ChargePaymentMethodStep } from './ChargePaymentMethodStep';
import type { ChargeFormSurfaceProps } from './use-charge-form';

export function ChargeSheetForm({
    form,
    title,
    actionLabel,
    backLabel,
    onBack,
    onCancel,
    onSubmit,
}: ChargeFormSurfaceProps) {
    return (
        <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
            <SheetHeader className="pb-0">
                <ChargeFlowHeaderRow backLabel={backLabel} onBack={onBack}>
                    <SheetTitle>{title}</SheetTitle>
                </ChargeFlowHeaderRow>
            </SheetHeader>

            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-2">
                {form.step === ITEMS_STEP ? (
                    <ChargeItemsStep form={form} />
                ) : (
                    <ChargePaymentMethodStep form={form} />
                )}
            </div>

            <SheetFooter className="flex-row justify-end gap-2 border-t border-border">
                <ChargeFlowActions
                    actionLabel={actionLabel}
                    isSubmitting={form.isSubmitting}
                    onCancel={onCancel}
                />
            </SheetFooter>
        </form>
    );
}
