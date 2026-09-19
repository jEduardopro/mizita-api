import { Head } from '@inertiajs/react';
import { Link2Off } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export function BookingLinkInvalid() {
    const { t } = useTranslation('public');

    return (
        <div className="grid min-h-svh place-items-center bg-background px-5 py-16 text-foreground sm:px-8">
            <Head title={t('booking.manage.invalid.title')} />

            <Alert className="grid max-w-lg gap-3 p-4">
                <Link2Off aria-hidden="true" />

                <AlertTitle>{t('booking.manage.invalid.title')}</AlertTitle>

                <AlertDescription>{t('booking.manage.invalid.body')}</AlertDescription>
            </Alert>
        </div>
    );
}
