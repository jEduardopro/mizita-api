import { Receipt } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTable } from '@/components/shared/data-table/DataTable';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableSort } from '@/components/shared/data-table/types';
import { useDataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { useSales } from '../../queries';
import { SALE_SORT_FIELDS, type SaleSortField } from '../../types';
import { FilteredPaymentsEmptyState } from './FilteredPaymentsEmptyState';
import { paymentInstantFormatter } from './payment-instant';
import { PaymentHistoryEmptyState } from './PaymentHistoryEmptyState';
import { SaleCard } from './SaleCard';
import { saleColumns } from './sale-columns';
import { SalesFilterBar } from './SalesFilterBar';
import { useSalesFilters } from './use-sales-filters';

const DEFAULT_SORT: DataTableSort<SaleSortField> = { field: 'created_at', direction: 'desc' };

type Props = {
    timezone: string;
    today: string;
    customerFilter: ReactNode;
};

export function SalesTable({ timezone, today, customerFilter }: Props) {
    const { t, i18n } = useTranslation('admin');
    const query = useDataTableQuery({ sortableFields: SALE_SORT_FIELDS, defaultSort: DEFAULT_SORT });
    const filters = useSalesFilters(today);

    const sales = useSales({
        page: query.page,
        per_page: query.perPage,
        sort: query.sort.field,
        direction: query.sort.direction,
        ...filters.criteria,
    });

    const formatInstant = useMemo(
        () => paymentInstantFormatter(timezone, i18n.language),
        [timezone, i18n.language],
    );

    const columns = useMemo(() => saleColumns({ t, formatInstant }), [t, formatInstant]);

    return (
        <DataTable
            columns={columns}
            data={sales.data?.data ?? []}
            getRowId={(sale) => sale.id}
            caption={t('payments.history.sales.caption')}
            pagination={query.pagination}
            onPaginationChange={query.onPaginationChange}
            sorting={query.sorting}
            onSortingChange={query.onSortingChange}
            pageCount={sales.data?.meta.last_page ?? 0}
            totalRows={sales.data?.meta.total ?? 0}
            status={dataTableStatus(sales.isPending, sales.isError)}
            isFetching={sales.isFetching}
            showsPreviousRows={sales.isPlaceholderData}
            onRetry={() => void sales.refetch()}
            emptyState={
                filters.hasActiveFilters ? (
                    <FilteredPaymentsEmptyState onClearFilters={filters.clearFilters} />
                ) : (
                    <PaymentHistoryEmptyState
                        icon={Receipt}
                        title={t('payments.history.sales.empty.title')}
                        body={t('payments.history.sales.empty.body')}
                    />
                )
            }
            renderCard={(sale) => <SaleCard sale={sale} createdAt={formatInstant(sale.created_at)} />}
            toolbar={{
                content: <SalesFilterBar filters={filters} today={today} customerFilter={customerFilter} />,
                hasActiveFilters: filters.hasActiveFilters,
            }}
        />
    );
}
