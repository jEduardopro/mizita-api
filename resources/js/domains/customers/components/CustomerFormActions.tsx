import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { CUSTOMER_FORM_ID } from './CustomerForm';
import type { CustomerFormController, CustomerFormMode } from './use-customer-form';

const SUBMIT_KEYS = {
    create: 'customers.form.submit.create',
    edit: 'customers.form.submit.edit',
} as const satisfies Record<CustomerFormMode, string>;

const SUBMITTING_KEYS = {
    create: 'customers.form.submit.creating',
    edit: 'customers.form.submit.saving',
} as const satisfies Record<CustomerFormMode, string>;

type Props = {
    mode: CustomerFormMode;
    form: CustomerFormController;
    returnTo: string;
};

export function CustomerFormActions({ mode, form, returnTo }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <>
            <Button asChild variant="outline" className="h-11 px-4 md:h-9">
                <Link href={returnTo}>{tCommon('actions.cancel')}</Link>
            </Button>

            <SubmitButton
                form={CUSTOMER_FORM_ID}
                variant="brand"
                className="h-11 px-4 md:h-9"
                label={t(SUBMIT_KEYS[mode])}
                submittingLabel={t(SUBMITTING_KEYS[mode])}
                isSubmitting={form.isSubmitting}
            />
        </>
    );
}
