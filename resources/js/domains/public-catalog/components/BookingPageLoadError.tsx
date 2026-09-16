import { Head } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type Props = {
    onRetry: () => void;
};

export function BookingPageLoadError({ onRetry }: Props) {
    const { t } = useTranslation('public');
    const { t: tCommon } = useTranslation('common');

    return (
        <div className="grid min-h-svh place-items-center bg-background px-5 py-16 text-foreground sm:px-8">
            <Head title={t('booking.pageTitle')} />

            <Alert className="grid max-w-lg gap-3 p-4">
                <TriangleAlert aria-hidden="true" />

                <AlertTitle>{t('booking.failed.title')}</AlertTitle>

                <AlertDescription>{t('booking.failed.body')}</AlertDescription>

                <div className="col-start-2">
                    <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-4">
                        {tCommon('actions.tryAgain')}
                    </Button>
                </div>
            </Alert>
        </div>
    );
}
