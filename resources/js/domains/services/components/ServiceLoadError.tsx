import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type Props = {
    notFound: boolean;
    onRetry: () => void;
};

export function ServiceLoadError({ notFound, onRetry }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    if (notFound) {
        return (
            <Alert className="max-w-lg gap-2 p-4">
                <AlertTitle>{t('services.edit.notFound.title')}</AlertTitle>
                <AlertDescription>{t('services.edit.notFound.body')}</AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert className="grid max-w-lg gap-3 p-4">
            <TriangleAlert aria-hidden="true" />

            <AlertTitle>{t('services.edit.failed.title')}</AlertTitle>

            <AlertDescription>{t('services.edit.failed.body')}</AlertDescription>

            <div className="col-start-2">
                <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-4 md:h-9">
                    {tCommon('actions.tryAgain')}
                </Button>
            </div>
        </Alert>
    );
}
