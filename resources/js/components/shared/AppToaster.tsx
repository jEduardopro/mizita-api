import { useTranslation } from 'react-i18next';
import { Toaster } from '@/components/ui/sonner';
import { useAppearance } from '@/hooks/use-appearance';

export function AppToaster() {
    const { t } = useTranslation('common');
    const { resolvedAppearance } = useAppearance();

    return (
        <Toaster
            theme={resolvedAppearance}
            position="bottom-right"
            closeButton
            visibleToasts={3}
            containerAriaLabel={t('notifications.region')}
            toastOptions={{
                closeButtonAriaLabel: t('notifications.dismiss'),
                classNames: { toast: 'cn-toast' },
            }}
        />
    );
}
