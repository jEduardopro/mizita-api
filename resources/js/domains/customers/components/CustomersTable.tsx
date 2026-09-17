import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTable } from '@/components/shared/data-table/DataTable';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableToolbar } from '@/components/shared/data-table/types';
import type { DataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { useCustomers } from '../queries';
import type { CustomerSortField } from '../types';
import { customerColumns } from './customer-columns';
import { CustomerListRow } from './CustomerListRow';
import { CustomersEmptyState } from './CustomersEmptyState';

type Props = {
    query: DataTableQuery<CustomerSortField>;
    toolbar: DataTableToolbar;
    onClearSearch: () => void;
};

export function CustomersTable({ query, toolbar, onClearSearch }: Props) {
    const { t } = useTranslation('admin');

    const customers = useCustomers({
        page: query.page,
        per_page: query.perPage,
        sort: query.sort.field,
        direction: query.sort.direction,
        search: query.search === '' ? undefined : query.search,
    });

    const columns = useMemo(() => customerColumns({ t }), [t]);

    return (
        <DataTable
            columns={columns}
            data={customers.data?.data ?? []}
            getRowId={(customer) => customer.id}
            caption={t('customers.table.caption')}
            pagination={query.pagination}
            onPaginationChange={query.onPaginationChange}
            sorting={query.sorting}
            onSortingChange={query.onSortingChange}
            pageCount={customers.data?.meta.last_page ?? 0}
            totalRows={customers.data?.meta.total ?? 0}
            status={dataTableStatus(customers.isPending, customers.isError)}
            isFetching={customers.isFetching}
            showsPreviousRows={customers.isPlaceholderData}
            onRetry={() => void customers.refetch()}
            emptyState={
                <CustomersEmptyState search={query.search} onClearSearch={onClearSearch} />
            }
            renderCard={(customer) => <CustomerListRow customer={customer} />}
            toolbar={toolbar}
        />
    );
}
