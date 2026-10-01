import { useTranslation } from 'react-i18next';
import { formatMoneyFromCents } from '@/lib/money';
import type { Sale } from '../../types';
import { PaymentStatusBadge } from '../PaymentStatusBadge';
import { ReferenceCode } from './ReferenceCode';

type Props = {
    sale: Sale;
    createdAt: string;
};

export function SaleCard({ sale, createdAt }: Props) {
    const { t } = useTranslation('admin');

    return (
        <article className="grid gap-2 rounded-xl border border-border bg-card px-4 py-3">
            <div className="flex items-baseline justify-between gap-3">
                <p className="min-w-0 truncate text-sm font-medium">{sale.customer.name}</p>

                <p className="shrink-0 text-sm font-semibold tabular-nums">
                    {formatMoneyFromCents(sale.total_cents, sale.currency_code)}
                </p>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
                <p className="text-xs text-muted-foreground tabular-nums">{createdAt}</p>

                <PaymentStatusBadge status={sale.status} />
            </div>

            <p className="text-xs text-muted-foreground">
                {t('payments.history.sales.columns.reference')}{' '}
                <span className="text-foreground">
                    <ReferenceCode code={sale.reference_code} />
                </span>
            </p>
        </article>
    );
}
