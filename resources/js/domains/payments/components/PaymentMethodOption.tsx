import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { RadioGroupItem } from '@/components/ui/radio-group';
import { paymentMethodIcon, paymentMethodLabelKey } from './payment-method-labels';

type Props = {
    id: string;
    code: string;
    label: string;
    enabled: boolean;
};

export function PaymentMethodOption({ id, code, label, enabled }: Props) {
    const { t } = useTranslation('admin');
    const Icon = paymentMethodIcon(code);
    const labelKey = paymentMethodLabelKey(code);
    const optionId = `payment-method-${id}`;

    return (
        <label
            htmlFor={optionId}
            className={cn(
                'flex min-h-14 items-center gap-3 rounded-xl border border-border px-3 py-2.5 transition-colors md:min-h-12',
                enabled
                    ? 'cursor-pointer hover:bg-muted/60 has-data-checked:border-primary has-data-checked:bg-muted/50'
                    : 'cursor-not-allowed text-muted-foreground opacity-60',
            )}
        >
            <RadioGroupItem id={optionId} value={id} disabled={! enabled} />

            <Icon aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />

            <span className="min-w-0 flex-1 truncate text-sm">
                {labelKey === null ? label : t(labelKey)}
            </span>

            {enabled ? null : (
                <span className="shrink-0 text-xs text-muted-foreground">
                    {t('payments.method.unavailable')}
                </span>
            )}
        </label>
    );
}
