import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type Props = {
    isRetrying: boolean;
    onRetry: () => void;
};

export function IntegrationsLoadError({ isRetrying, onRetry }: Props) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <Alert className="grid max-w-lg gap-3 p-4">
            <TriangleAlert aria-hidden="true" />

            <AlertTitle>{t('integrations.loadFailed.title')}</AlertTitle>

            <AlertDescription>{t('integrations.loadFailed.body')}</AlertDescription>

            <div className="col-start-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={isRetrying}
                    onClick={onRetry}
                    className="h-11 px-4 md:h-9"
                >
                    {isRetrying ? tCommon('actions.retrying') : tCommon('actions.tryAgain')}
                </Button>
            </div>
        </Alert>
    );
}
