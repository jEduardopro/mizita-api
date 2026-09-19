import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { CreateCustomerActions } from './CreateCustomerActions';
import { CustomerForm } from './CustomerForm';
import { useCreateCustomerSurface, type CreateCustomerSurfaceProps } from './use-create-customer-surface';

export function CreateCustomerSheetForm({ onCancel, ...params }: CreateCustomerSurfaceProps) {
    const { t } = useTranslation('admin');
    const formId = useId();
    const form = useCreateCustomerSurface(params);

    return (
        <>
            <SheetHeader>
                <SheetTitle>{t('customers.create.title')}</SheetTitle>
            </SheetHeader>

            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4">
                <CustomerForm form={form} id={formId} focusNameField />
            </div>

            <SheetFooter className="flex-row justify-end gap-2">
                <CreateCustomerActions
                    formId={formId}
                    isSubmitting={form.isSubmitting}
                    onCancel={onCancel}
                />
            </SheetFooter>
        </>
    );
}
