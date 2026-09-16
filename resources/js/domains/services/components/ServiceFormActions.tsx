import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { SERVICE_FORM_ID } from './ServiceForm';
import { SERVICES_URL } from './service-urls';
import type { ServiceFormController, ServiceFormMode } from './use-service-form';

const SUBMIT_KEYS = {
    create: 'services.form.submit.create',
    edit: 'services.form.submit.edit',
} as const satisfies Record<ServiceFormMode, string>;

const SUBMITTING_KEYS = {
    create: 'services.form.submit.creating',
    edit: 'services.form.submit.saving',
} as const satisfies Record<ServiceFormMode, string>;

type Props = {
    mode: ServiceFormMode;
    form: ServiceFormController;
};

export function ServiceFormActions({ mode, form }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <>
            <Button asChild variant="outline" className="h-11 px-4 md:h-9">
                <Link href={SERVICES_URL}>{tCommon('actions.cancel')}</Link>
            </Button>

            <SubmitButton
                form={SERVICE_FORM_ID}
                variant="brand"
                className="h-11 px-4 md:h-9"
                label={t(SUBMIT_KEYS[mode])}
                submittingLabel={t(SUBMITTING_KEYS[mode])}
                isSubmitting={form.isSubmitting}
            />
        </>
    );
}
