import { SearchX } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { PaymentHistoryEmptyState } from './PaymentHistoryEmptyState';

type Props = {
    onClearFilters: () => void;
};

export function FilteredPaymentsEmptyState({ onClearFilters }: Props) {
    const { t } = useTranslation('admin');

    return (
        <PaymentHistoryEmptyState
            icon={SearchX}
            title={t('payments.history.empty.filtered.title')}
            body={t('payments.history.empty.filtered.body')}
            action={
                <Button type="button" variant="outline" onClick={onClearFilters} className="h-11 px-4 md:h-9">
                    {t('payments.history.empty.filtered.action')}
                </Button>
            }
        />
    );
}
