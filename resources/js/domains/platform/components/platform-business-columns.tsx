import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import type { PlatformBusiness } from '../types';
import { ImpersonateOwnerButton } from './ImpersonateOwnerButton';
import { PlatformBusinessOwner } from './PlatformBusinessOwner';
import { PlatformPlanBadge } from './PlatformPlanBadge';

type Params = {
    t: TFunction<'platform'>;
    formatCreatedAt: (instant: string) => string;
};

function CountCell({ value }: { value: number }) {
    return <span className="tabular-nums">{value}</span>;
}

export function platformBusinessColumns({ t, formatCreatedAt }: Params): ColumnDef<PlatformBusiness>[] {
    return [
        {
            id: 'name',
            accessorKey: 'name',
            header: t('businesses.columns.business'),
            cell: ({ row }) => (
                <span className="grid min-w-0 leading-tight">
                    <span className="truncate font-medium">{row.original.name}</span>
                    <span className="truncate text-xs text-muted-foreground">/{row.original.slug}</span>
                </span>
            ),
        },
        {
            id: 'owner',
            header: t('businesses.columns.owner'),
            enableSorting: false,
            cell: ({ row }) => <PlatformBusinessOwner owner={row.original.owner} />,
        },
        {
            id: 'created_at',
            accessorKey: 'created_at',
            sortDescFirst: true,
            header: t('businesses.columns.createdAt'),
            cell: ({ row }) => (
                <time dateTime={row.original.created_at} className="whitespace-nowrap tabular-nums">
                    {formatCreatedAt(row.original.created_at)}
                </time>
            ),
        },
        {
            id: 'services_count',
            accessorKey: 'services_count',
            sortDescFirst: true,
            header: t('businesses.columns.services'),
            cell: ({ row }) => <CountCell value={row.original.services_count} />,
        },
        {
            id: 'customers_count',
            accessorKey: 'customers_count',
            sortDescFirst: true,
            header: t('businesses.columns.customers'),
            cell: ({ row }) => <CountCell value={row.original.customers_count} />,
        },
        {
            id: 'plan',
            header: t('businesses.columns.plan'),
            enableSorting: false,
            cell: ({ row }) => <PlatformPlanBadge plan={row.original.plan} />,
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">{t('businesses.columns.actions')}</span>,
            enableSorting: false,
            cell: ({ row }) => {
                const { id, name, owner } = row.original;

                if (owner === null) {
                    return null;
                }

                return (
                    <div className="flex justify-end">
                        <ImpersonateOwnerButton
                            businessId={id}
                            businessName={name}
                            ownerName={owner.name}
                            label={t('businesses.impersonate.shortAction')}
                            className="h-9 px-3"
                        />
                    </div>
                );
            },
        },
    ];
}
