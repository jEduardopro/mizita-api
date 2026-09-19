import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { CreateCustomerActions } from './CreateCustomerActions';
import { CustomerForm } from './CustomerForm';
import { useCreateCustomerSurface, type CreateCustomerSurfaceProps } from './use-create-customer-surface';

export function CreateCustomerDialogForm({ onCancel, ...params }: CreateCustomerSurfaceProps) {
    const { t } = useTranslation('admin');
    const formId = useId();
    const form = useCreateCustomerSurface(params);

    return (
        <div className="grid gap-4">
            <DialogHeader>
                <DialogTitle>{t('customers.create.title')}</DialogTitle>
            </DialogHeader>

            <div className="max-h-[calc(100svh-14rem)] overflow-y-auto overscroll-contain pr-1">
                <CustomerForm form={form} id={formId} focusNameField layout="stacked" />
            </div>

            <DialogFooter>
                <CreateCustomerActions
                    formId={formId}
                    isSubmitting={form.isSubmitting}
                    onCancel={onCancel}
                />
            </DialogFooter>
        </div>
    );
}
