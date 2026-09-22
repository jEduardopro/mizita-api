import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

const ACTION_SIZE = 'h-11 px-4 md:h-9';

type Props = {
    actionLabel: string;
    isSubmitting: boolean;
    onCancel: () => void;
};

export function ChargeFlowActions({ actionLabel, isSubmitting, onCancel }: Props) {
    const { t } = useTranslation('common');

    return (
        <>
            <Button type="button" variant="ghost" onClick={onCancel} className={ACTION_SIZE}>
                {t('actions.cancel')}
            </Button>

            <Button type="submit" variant="brand" disabled={isSubmitting} className={ACTION_SIZE}>
                {actionLabel}
            </Button>
        </>
    );
}
