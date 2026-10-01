import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import type { PaymentTransactionReport } from '../../types';
import { TRANSACTION_TYPE_LABEL_KEYS } from './payment-filter-values';
import { SignedAmount } from './SignedAmount';
import { TransactionMethod } from './TransactionMethod';

type Params = {
    t: TFunction<'admin'>;
    formatInstant: (instant: string) => string;
};

export function transactionColumns({ t, formatInstant }: Params): ColumnDef<PaymentTransactionReport>[] {
    return [
        {
            id: 'processed_at',
            accessorKey: 'processed_at',
            sortDescFirst: true,
            header: t('payments.history.transactions.columns.processedAt'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap tabular-nums">{formatInstant(row.original.processed_at)}</span>
            ),
        },
        {
            id: 'customer',
            header: t('payments.history.transactions.columns.customer'),
            enableSorting: false,
            cell: ({ row }) => <span className="font-medium">{row.original.customer.name}</span>,
        },
        {
            id: 'amount',
            accessorKey: 'amount_cents',
            sortDescFirst: true,
            header: t('payments.history.transactions.columns.amount'),
            cell: ({ row }) => (
                <SignedAmount cents={row.original.amount_cents} currencyCode={row.original.currency_code} />
            ),
        },
        {
            id: 'type',
            header: t('payments.history.transactions.columns.type'),
            enableSorting: false,
            cell: ({ row }) => t(TRANSACTION_TYPE_LABEL_KEYS[row.original.type]),
        },
        {
            id: 'method',
            header: t('payments.history.transactions.columns.method'),
            enableSorting: false,
            cell: ({ row }) => <TransactionMethod code={row.original.method.code} name={row.original.method.name} />,
        },
    ];
}
