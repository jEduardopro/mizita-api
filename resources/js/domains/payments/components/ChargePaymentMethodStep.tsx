import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { RadioGroup } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { formatMoneyFromCents } from '@/lib/money';
import { AMOUNT_FIELD, PAYMENT_METHOD_FIELD } from './charge-form-values';
import { ChargeAmountRow } from './ChargeAmountRow';
import { PaymentMethodOption } from './PaymentMethodOption';
import type { ChargeFormController } from './use-charge-form';

const PLACEHOLDER_ROWS = [0, 1];

type Props = {
    form: ChargeFormController;
};

export function ChargePaymentMethodStep({ form }: Props) {
    const { t, i18n } = useTranslation('admin');
    const fieldId = useId();
    const { values, totals } = form;
    const methodError = form.errorFor(PAYMENT_METHOD_FIELD);

    function money(cents: number): string {
        return formatMoneyFromCents(cents, form.currencyCode, i18n.language);
    }

    function methodList() {
        if (form.isLoadingMethods) {
            return (
                <div className="grid gap-2">
                    {PLACEHOLDER_ROWS.map((row) => (
                        <Skeleton key={row} className="h-14 rounded-xl md:h-12" />
                    ))}
                </div>
            );
        }

        if (form.hasMethodsError) {
            return (
                <p className="text-sm text-destructive">{t('payments.errors.methodsLoad')}</p>
            );
        }

        if (form.methods.length === 0) {
            return (
                <p className="text-sm text-muted-foreground">{t('payments.method.unavailable')}</p>
            );
        }

        return (
            <RadioGroup
                value={values.paymentMethodId}
                onValueChange={form.selectMethod}
                aria-label={t('payments.method.title')}
                aria-invalid={methodError !== undefined}
            >
                {form.methods.map((method) => (
                    <PaymentMethodOption
                        key={method.id}
                        id={method.id}
                        code={method.code}
                        label={method.label}
                        enabled={method.enabled}
                    />
                ))}
            </RadioGroup>
        );
    }

    return (
        <div className="grid gap-4">
            <p className="text-sm font-medium">{t('payments.method.title')}</p>

            {methodList()}

            <FieldMessage message={fieldMessage({ id: `${fieldId}-method`, error: methodError })} />

            <Separator />

            <ChargeAmountRow
                label={t('payments.method.total')}
                value={money(totals.amountCents)}
                tone="strong"
                error={form.errorFor(AMOUNT_FIELD)}
            />

            {values.partial ? (
                <ChargeAmountRow
                    label={t('payments.partial.remaining')}
                    value={money(totals.remainingCents)}
                />
            ) : null}
        </div>
    );
}
