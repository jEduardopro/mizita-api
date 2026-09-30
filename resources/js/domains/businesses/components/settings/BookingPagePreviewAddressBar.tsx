import { Share } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useShareLink } from '@/hooks/use-share-link';
import { bookingPageHost, bookingPageUrl } from './booking-page-url';

type Props = {
    slug: string;
    businessName: string;
};

export function BookingPagePreviewAddressBar({ slug, businessName }: Props) {
    const { t } = useTranslation('admin');

    const share = useShareLink({
        title: t('businessSettings.preview.share.title', { name: businessName }),
        copied: t('businessSettings.preview.share.copied'),
        failed: t('businessSettings.preview.share.copyFailed'),
    });

    return (
        <div className="flex items-center gap-1 rounded-full border border-border bg-muted/60 pl-4 text-xs">
            <p className="flex min-w-0 flex-1">
                <span className="truncate text-muted-foreground">{bookingPageHost()}/</span>
                <span className="truncate font-medium text-foreground">{slug}</span>
            </p>

            <Button
                type="button"
                variant="ghost"
                disabled={slug === ''}
                onClick={() => share(bookingPageUrl(slug))}
                className="size-11 shrink-0 rounded-full p-0"
            >
                <Share aria-hidden="true" className="size-4" />
                <span className="sr-only">{t('businessSettings.preview.share.label')}</span>
            </Button>
        </div>
    );
}
