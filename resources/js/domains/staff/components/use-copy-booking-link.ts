import { useTranslation } from 'react-i18next';
import { useCopyToClipboard } from '@/hooks/use-copy-to-clipboard';

export function useCopyBookingLink(): (url: string) => void {
    const { t } = useTranslation('admin');

    return useCopyToClipboard({
        copied: t('services.toasts.linkCopied'),
        failed: t('profile.bookingLink.copyFailed'),
    });
}
