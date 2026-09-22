import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { formatMoneyFromCents } from '@/lib/money';
import type { PaymentItem } from '../types';

type AmountRowProps = {
    label: string;
    amount: string;
    className?: string;
};

function AmountRow({ label, amount, className }: AmountRowProps) {
    return (
        <div className={cn('flex items-baseline justify-between gap-4 text-sm', className)}>
            <span className="min-w-0 text-pretty break-words">{label}</span>

            <span className="shrink-0 tabular-nums">{amount}</span>
        </div>
    );
}

type Props = {
    items: PaymentItem[];
    subtotalCents: number;
    discountCents: number;
    totalCents: number;
    currencyCode: string;
};

export function PaymentPurchasesSection({
    items,
    subtotalCents,
    discountCents,
    totalCents,
    currencyCode,
}: Props) {
    const { t, i18n } = useTranslation('admin');

    const money = (cents: number) => formatMoneyFromCents(cents, currencyCode, i18n.language);

    return (
        <section className="grid gap-3">
            <h3 className="text-sm font-medium text-muted-foreground">{t('payments.panel.purchases')}</h3>

            <div className="grid gap-2">
                {items.map((item) => (
                    <AmountRow key={item.id} label={item.name} amount={money(item.amount_cents)} />
                ))}
            </div>

            <div className="grid gap-2 border-t border-border pt-3">
                <AmountRow
                    label={t('payments.panel.subtotal')}
                    amount={money(subtotalCents)}
                    className="text-muted-foreground"
                />

                {discountCents > 0 ? (
                    <AmountRow
                        label={t('payments.panel.discount')}
                        amount={`−${money(discountCents)}`}
                        className="text-muted-foreground"
                    />
                ) : null}

                <AmountRow
                    label={t('payments.panel.total')}
                    amount={money(totalCents)}
                    className="font-semibold"
                />
            </div>
        </section>
    );
}
