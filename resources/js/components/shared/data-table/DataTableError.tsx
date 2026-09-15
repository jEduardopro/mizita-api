import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type Props = {
    onRetry: () => void;
};

export function DataTableError({ onRetry }: Props) {
    const { t } = useTranslation('common');

    return (
        <Alert className="grid gap-3 p-4">
            <TriangleAlert aria-hidden="true" />

            <AlertTitle>{t('table.error.title')}</AlertTitle>

            <AlertDescription>{t('table.error.body')}</AlertDescription>

            <div className="col-start-2">
                <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-4 md:h-9">
                    {t('actions.tryAgain')}
                </Button>
            </div>
        </Alert>
    );
}
