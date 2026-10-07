import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type Props = {
    notFound: boolean;
    onRetry: () => void;
};

export function NotificationLoadError({ notFound, onRetry }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    if (notFound) {
        return (
            <Alert className="max-w-lg gap-2 p-4">
                <AlertTitle>{t('notifications.show.notFound.title')}</AlertTitle>
                <AlertDescription>{t('notifications.show.notFound.body')}</AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert className="grid max-w-lg gap-3 p-4">
            <TriangleAlert aria-hidden="true" />

            <AlertTitle>{t('notifications.show.failed.title')}</AlertTitle>

            <AlertDescription>{t('notifications.show.failed.body')}</AlertDescription>

            <div className="col-start-2">
                <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-4 md:h-9">
                    {tCommon('actions.tryAgain')}
                </Button>
            </div>
        </Alert>
    );
}
