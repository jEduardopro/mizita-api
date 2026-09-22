import { X } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { ChargeAmountInput } from './ChargeAmountInput';
import { ChargePercentInput } from './ChargePercentInput';

type Props = {
    percent: string;
    amount: string;
    onPercentChange: (percent: string) => void;
    onAmountChange: (amount: string) => void;
    onRemove: () => void;
};

export function ChargeDiscountRow({
    percent,
    amount,
    onPercentChange,
    onAmountChange,
    onRemove,
}: Props) {
    const { t } = useTranslation('admin');
    const rowId = useId();

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="min-w-0 flex-1 text-sm">{t('payments.discount.label')}</span>

            <ChargePercentInput
                id={`${rowId}-percent`}
                label={t('payments.discount.percentLabel')}
                value={percent}
                onChange={onPercentChange}
            />

            <span aria-hidden="true" className="text-sm text-muted-foreground">
                &minus;
            </span>

            <ChargeAmountInput
                id={`${rowId}-amount`}
                label={t('payments.discount.amountLabel')}
                value={amount}
                onChange={onAmountChange}
            />

            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onRemove}
                aria-label={t('payments.discount.remove')}
                className="size-11 md:size-8"
            >
                <X />
            </Button>
        </div>
    );
}
