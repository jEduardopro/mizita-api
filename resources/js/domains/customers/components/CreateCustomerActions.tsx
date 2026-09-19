import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';

const ACTION_SIZE = 'h-11 px-4 md:h-9';

type Props = {
    formId: string;
    isSubmitting: boolean;
    onCancel: () => void;
};

export function CreateCustomerActions({ formId, isSubmitting, onCancel }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <>
            <Button type="button" variant="outline" onClick={onCancel} className={ACTION_SIZE}>
                {tCommon('actions.cancel')}
            </Button>

            <SubmitButton
                form={formId}
                variant="brand"
                className={ACTION_SIZE}
                label={t('customers.form.submit.create')}
                submittingLabel={t('customers.form.submit.creating')}
                isSubmitting={isSubmitting}
            />
        </>
    );
}
