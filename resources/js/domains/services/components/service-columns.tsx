import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { ServiceColorTile } from '@/components/shared/ServiceColorTile';
import { Badge } from '@/components/ui/badge';
import { formatBuffer, formatDuration, formatPrice } from '@/lib/service-format';
import type { Service } from '../types';
import { ServiceRowActions } from './ServiceRowActions';
import { serviceEditUrl } from './service-urls';
import { ServiceStaffAvatars } from './ServiceStaffAvatars';

type Params = {
    t: TFunction<'admin'>;
    locale: string;
};

export function serviceColumns({ t, locale }: Params): ColumnDef<Service>[] {
    return [
        {
            id: 'name',
            accessorKey: 'name',
            header: t('services.columns.name'),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <ServiceColorTile
                        color={row.original.color}
                        imageUrl={row.original.image_url}
                        className="size-9 rounded-lg"
                    />

                    <Link
                        href={serviceEditUrl(row.original.id)}
                        className="font-medium outline-none hover:underline focus-visible:underline"
                    >
                        {row.original.name}
                    </Link>

                    {row.original.active ? null : (
                        <Badge variant="outline">{t('services.hidden')}</Badge>
                    )}
                </div>
            ),
        },
        {
            id: 'duration',
            accessorFn: (service) => service.duration_minutes,
            header: t('services.columns.duration'),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {formatDuration(row.original.duration_minutes, t)}
                </span>
            ),
        },
        {
            id: 'buffer',
            header: t('services.columns.buffer'),
            enableSorting: false,
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {formatBuffer(row.original.buffer_minutes, t)}
                </span>
            ),
        },
        {
            id: 'price',
            accessorKey: 'price',
            header: t('services.columns.price'),
            cell: ({ row }) => formatPrice(row.original.price, locale, t),
        },
        {
            id: 'staff',
            header: t('services.columns.staff'),
            enableSorting: false,
            cell: ({ row }) => <ServiceStaffAvatars staff={row.original.staff} />,
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">{t('services.columns.actions')}</span>,
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <ServiceRowActions service={row.original} />
                </div>
            ),
        },
    ];
}
