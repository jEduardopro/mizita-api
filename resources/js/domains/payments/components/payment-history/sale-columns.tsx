import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { formatMoneyFromCents } from '@/lib/money';
import type { Sale } from '../../types';
import { PaymentStatusBadge } from '../PaymentStatusBadge';
import { ReferenceCode } from './ReferenceCode';

type Params = {
    t: TFunction<'admin'>;
    formatInstant: (instant: string) => string;
};

export function saleColumns({ t, formatInstant }: Params): ColumnDef<Sale>[] {
    return [
        {
            id: 'created_at',
            accessorKey: 'created_at',
            sortDescFirst: true,
            header: t('payments.history.sales.columns.createdAt'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap tabular-nums">{formatInstant(row.original.created_at)}</span>
            ),
        },
        {
            id: 'customer',
            header: t('payments.history.sales.columns.customer'),
            enableSorting: false,
            cell: ({ row }) => <span className="font-medium">{row.original.customer.name}</span>,
        },
        {
            id: 'status',
            header: t('payments.history.sales.columns.status'),
            enableSorting: false,
            cell: ({ row }) => <PaymentStatusBadge status={row.original.status} />,
        },
        {
            id: 'total',
            accessorKey: 'total_cents',
            sortDescFirst: true,
            header: t('payments.history.sales.columns.total'),
            cell: ({ row }) => (
                <span className="font-medium whitespace-nowrap tabular-nums">
                    {formatMoneyFromCents(row.original.total_cents, row.original.currency_code)}
                </span>
            ),
        },
        {
            id: 'reference',
            header: t('payments.history.sales.columns.reference'),
            enableSorting: false,
            cell: ({ row }) => <ReferenceCode code={row.original.reference_code} />,
        },
    ];
}
