import { Share } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useShareLink } from './use-share-link';

type Props = {
    url: string;
    title: string;
};

export function BookingShareButton({ url, title }: Props) {
    const { t } = useTranslation('public');

    const share = useShareLink({
        title,
        copied: t('booking.flow.pinned.copied'),
        failed: t('booking.flow.pinned.copyFailed'),
    });

    return (
        <Button
            type="button"
            variant="ghost"
            onClick={() => share(url)}
            className="size-11 rounded-full p-0"
        >
            <Share aria-hidden="true" className="size-5" />
            <span className="sr-only">{t('booking.flow.pinned.share')}</span>
        </Button>
    );
}
