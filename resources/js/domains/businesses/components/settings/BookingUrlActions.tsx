import { Copy, ExternalLink } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { raiseErrorToast, raiseSuccessToast } from '@/lib/toast';
import { BookingPageLink } from './BookingPageLink';
import { bookingPageUrl } from './booking-page-url';

type Props = {
    slug: string;
};

export function BookingUrlActions({ slug }: Props) {
    const { t } = useTranslation('admin');

    async function copyUrl() {
        try {
            await navigator.clipboard.writeText(bookingPageUrl(slug));
            raiseSuccessToast(t('businessSettings.brand.url.copied'));
        } catch {
            raiseErrorToast(t('businessSettings.brand.url.copyFailed'));
        }
    }

    return (
        <div className="flex shrink-0 items-center gap-1">
            <BookingPageLink slug={slug} className="size-11 p-0">
                <ExternalLink aria-hidden="true" />
                <span className="sr-only">{t('businessSettings.brand.url.open')}</span>
            </BookingPageLink>

            <Button
                type="button"
                variant="ghost"
                disabled={slug === ''}
                onClick={() => void copyUrl()}
                className="size-11 p-0"
            >
                <Copy aria-hidden="true" />
                <span className="sr-only">{t('businessSettings.brand.url.copy')}</span>
            </Button>
        </div>
    );
}
