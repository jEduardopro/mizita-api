import { useTranslation } from 'react-i18next';
import { normalizeAmountInput, padAmountInput } from '@/components/form/amount-input';
import { fieldMessage } from '@/components/form/FieldMessage';
import { Input } from '@/components/ui/input';

type Props = {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
};

export function ChargeAmountInput({ id, label, value, onChange, error }: Props) {
    const { t } = useTranslation('admin');
    const message = fieldMessage({ id, error });

    return (
        <div className="relative w-24 shrink-0 sm:w-32">
            <span
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-sm text-muted-foreground"
            >
                {t('services.currencySymbol')}
            </span>

            <Input
                id={id}
                aria-label={label}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                aria-invalid={!! error}
                aria-describedby={message?.id}
                value={value}
                onChange={(event) => onChange(normalizeAmountInput(event.target.value))}
                onBlur={() => onChange(padAmountInput(value))}
                className="h-11 pl-6 text-right text-base tabular-nums md:h-9"
            />
        </div>
    );
}
