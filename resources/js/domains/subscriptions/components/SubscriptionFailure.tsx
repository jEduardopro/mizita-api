import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Alert, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

type Props = {
    message: string;
    onRetry?: () => void;
};

export function SubscriptionFailure({ message, onRetry }: Props) {
    const { t } = useTranslation('common');

    return (
        <Alert className="grid gap-3 p-4">
            <TriangleAlert aria-hidden="true" />

            <AlertTitle className="leading-relaxed font-normal text-pretty">{message}</AlertTitle>

            {onRetry !== undefined ? (
                <div className="col-start-2">
                    <Button type="button" variant="outline" onClick={onRetry} className="h-11 px-4 md:h-9">
                        {t('actions.tryAgain')}
                    </Button>
                </div>
            ) : null}
        </Alert>
    );
}
