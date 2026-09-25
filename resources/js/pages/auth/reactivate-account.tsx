import { useTranslation } from 'react-i18next';
import { ReactivateAccountPanel } from '@/domains/accounts/components/ReactivateAccountPanel';
import { useAccountReactivationFlow } from '@/domains/accounts/components/use-account-reactivation-flow';
import { AuthLayout } from '@/layouts/AuthLayout';

export default function ReactivateAccount() {
    const { t } = useTranslation('auth');
    const flow = useAccountReactivationFlow();

    return (
        <AuthLayout
            title={t('reactivateAccount.title')}
            heading={t('reactivateAccount.heading')}
            description={t('reactivateAccount.description')}
        >
            <ReactivateAccountPanel
                status={flow.status}
                hasFailed={flow.hasFailed}
                isRetrying={flow.isRetrying}
                onRetry={flow.retry}
                onReactivate={flow.reactivate}
                onDismiss={flow.dismiss}
                pendingAction={flow.pendingAction}
            />
        </AuthLayout>
    );
}
