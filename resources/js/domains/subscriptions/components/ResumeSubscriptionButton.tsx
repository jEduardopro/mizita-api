import { useTranslation } from 'react-i18next';
import { SubmitButton } from '@/components/form/SubmitButton';
import { formMessageFrom } from '@/lib/http';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { useResumeSubscription } from '../queries';

export function ResumeSubscriptionButton() {
    const { t } = useTranslation('admin');
    const resume = useResumeSubscription();

    async function resumeSubscription() {
        try {
            await resume.mutateAsync();

            raiseSuccessToast(t('plan.subscription.resumed'));
        } catch (error) {
            raiseErrorToast(formMessageFrom(error, t('plan.subscription.resumeFailed')));
        }
    }

    return (
        <SubmitButton
            type="button"
            variant="brand"
            label={t('plan.settings.resume')}
            submittingLabel={t('plan.settings.resuming')}
            isSubmitting={resume.isPending}
            onClick={() => void resumeSubscription()}
            className="h-11 px-4 md:h-9"
        />
    );
}
