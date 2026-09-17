import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTable } from '@/components/shared/data-table/DataTable';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableToolbar } from '@/components/shared/data-table/types';
import type { DataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { useServices } from '../queries';
import type { ServiceSortField } from '../types';
import { serviceColumns } from './service-columns';
import { ServiceListRow } from './ServiceListRow';
import { ServicesEmptyState } from './ServicesEmptyState';

type Props = {
    query: DataTableQuery<ServiceSortField>;
    toolbar: DataTableToolbar;
    onClearSearch: () => void;
};

export function ServicesTable({ query, toolbar, onClearSearch }: Props) {
    const { t, i18n } = useTranslation('admin');
    const locale = i18n.language;

    const services = useServices({
        page: query.page,
        per_page: query.perPage,
        sort: query.sort.field,
        direction: query.sort.direction,
        search: query.search === '' ? undefined : query.search,
    });

    const columns = useMemo(() => serviceColumns({ t, locale }), [t, locale]);

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
            emptyState={
                <ServicesEmptyState search={query.search} onClearSearch={onClearSearch} />
            }
            renderCard={(service) => <ServiceListRow service={service} />}
            toolbar={toolbar}
        />
    );
}
