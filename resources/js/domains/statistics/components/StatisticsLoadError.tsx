import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formMessageFrom, isValidationError } from '@/lib/http';

type Props = {
    error: unknown;
    onRetry: () => void;
};

export function StatisticsLoadError({ error, onRetry }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    if (isValidationError(error)) {
        return (
            <Alert className="grid max-w-lg gap-2 p-4">
                <TriangleAlert aria-hidden="true" />
                <AlertTitle>{t('statistics.errors.range.title')}</AlertTitle>
                <AlertDescription>{formMessageFrom(error, t('statistics.errors.range.body'))}</AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert className="grid max-w-lg gap-3 p-4">
            <TriangleAlert aria-hidden="true" />

            <AlertTitle>{t('statistics.errors.load.title')}</AlertTitle>

            <AlertDescription>{t('statistics.errors.load.body')}</AlertDescription>

            <div className="col-start-2">
                <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-4 md:h-9">
                    {tCommon('actions.tryAgain')}
                </Button>
            </div>
        </Alert>
    );
}
