import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import type { Customer } from '../types';
import { CustomerAvatar } from './CustomerAvatar';
import { customerEditUrl } from './customer-urls';
import { formatPhone } from './customer-format';
import { CustomerRowActions } from './CustomerRowActions';

type Params = {
    t: TFunction<'admin'>;
};

type ContactCellProps = {
    value: string | null;
    fallback: string;
};

function ContactCell({ value, fallback }: ContactCellProps) {
    return <span className="text-muted-foreground">{value ?? fallback}</span>;
}

export function customerColumns({ t }: Params): ColumnDef<Customer>[] {
    const notProvided = t('customers.notProvided');

    return [
        {
            id: 'name',
            accessorKey: 'name',
            header: t('customers.columns.name'),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <CustomerAvatar name={row.original.name} photoUrl={row.original.photo_url} />

                    <Link
                        href={customerEditUrl(row.original.id)}
                        className="font-medium outline-none hover:underline focus-visible:underline"
                    >
                        {row.original.name}
                    </Link>
                </div>
            ),
        },
        {
            id: 'email',
            header: t('customers.columns.email'),
            enableSorting: false,
            cell: ({ row }) => <ContactCell value={row.original.email} fallback={notProvided} />,
        },
        {
            id: 'phone',
            header: t('customers.columns.phone'),
            enableSorting: false,
            cell: ({ row }) => (
                <ContactCell value={formatPhone(row.original.phone)} fallback={notProvided} />
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">{t('customers.columns.actions')}</span>,
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <CustomerRowActions customer={row.original} />
                </div>
            ),
        },
    ];
}
