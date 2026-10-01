import { useTranslation } from 'react-i18next';
import type { PaymentTransactionReport } from '../../types';
import { TRANSACTION_TYPE_LABEL_KEYS } from './payment-filter-values';
import { SignedAmount } from './SignedAmount';
import { TransactionMethod } from './TransactionMethod';

type Props = {
    transaction: PaymentTransactionReport;
    processedAt: string;
};

export function TransactionCard({ transaction, processedAt }: Props) {
    const { t } = useTranslation('admin');

    return (
        <article className="grid gap-2 rounded-xl border border-border bg-card px-4 py-3">
            <div className="flex items-baseline justify-between gap-3">
                <p className="min-w-0 truncate text-sm font-medium">{transaction.customer.name}</p>

                <SignedAmount
                    cents={transaction.amount_cents}
                    currencyCode={transaction.currency_code}
                    className="shrink-0 text-sm"
                />
            </div>

            <p className="text-xs text-muted-foreground tabular-nums">{processedAt}</p>

            <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                <span>{t(TRANSACTION_TYPE_LABEL_KEYS[transaction.type])}</span>

                <span aria-hidden="true" className="text-muted-foreground">
                    ·
                </span>

                <TransactionMethod code={transaction.method.code} name={transaction.method.name} />
            </div>
        </article>
    );
}
