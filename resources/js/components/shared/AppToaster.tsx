import { useTranslation } from 'react-i18next';
import { Toaster } from '@/components/ui/sonner';

export function AppToaster() {
    const { t } = useTranslation('common');

    return (
        <Toaster
            position="top-center"
            closeButton
            containerAriaLabel={t('notifications.region')}
            toastOptions={{
                closeButtonAriaLabel: t('notifications.dismiss'),
                classNames: { toast: 'cn-toast' },
            }}
        />
    );
}
