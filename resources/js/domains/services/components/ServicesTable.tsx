import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTable } from '@/components/shared/data-table/DataTable';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableToolbar } from '@/components/shared/data-table/types';
import type { DataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { useBusinessCurrency } from '@/hooks/use-business-currency';
import { useServices } from '../queries';
import type { ServiceSortField } from '../types';
import { serviceColumns } from './service-columns';
import { ServiceListRow } from './ServiceListRow';
import { serviceListFilters } from './service-list-filters';

type Props = {
    query: DataTableQuery<ServiceSortField>;
    staffIds: readonly string[];
    toolbar: DataTableToolbar;
    emptyState: ReactNode;
};

export function ServicesTable({ query, staffIds, toolbar, emptyState }: Props) {
    const { t } = useTranslation('admin');
    const currencyCode = useBusinessCurrency();

    const services = useServices({
        page: query.page,
        per_page: query.perPage,
        sort: query.sort.field,
        direction: query.sort.direction,
        ...serviceListFilters(query.search, staffIds),
    });

    const columns = useMemo(() => serviceColumns({ t, currencyCode }), [t, currencyCode]);

    return (
        <DataTable
            columns={columns}
            data={services.data?.data ?? []}
            getRowId={(service) => service.id}
            caption={t('services.table.caption')}
            pagination={query.pagination}
            onPaginationChange={query.onPaginationChange}
            sorting={query.sorting}
            onSortingChange={query.onSortingChange}
            pageCount={services.data?.meta.last_page ?? 0}
            totalRows={services.data?.meta.total ?? 0}
            status={dataTableStatus(services.isPending, services.isError)}
            isFetching={services.isFetching}
            showsPreviousRows={services.isPlaceholderData}
            onRetry={() => void services.refetch()}
            emptyState={emptyState}
            renderCard={(service) => <ServiceListRow service={service} />}
            toolbar={toolbar}
        />
    );
}
