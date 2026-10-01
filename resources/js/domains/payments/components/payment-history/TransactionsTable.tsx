import { ArrowLeftRight } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTable } from '@/components/shared/data-table/DataTable';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableSort } from '@/components/shared/data-table/types';
import { useDataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { useTransactions } from '../../queries';
import { TRANSACTION_SORT_FIELDS, type TransactionSortField } from '../../types';
import { FilteredPaymentsEmptyState } from './FilteredPaymentsEmptyState';
import { paymentInstantFormatter } from './payment-instant';
import { PaymentHistoryEmptyState } from './PaymentHistoryEmptyState';
import { TransactionCard } from './TransactionCard';
import { transactionColumns } from './transaction-columns';
import { TransactionsFilterBar } from './TransactionsFilterBar';
import { useTransactionsFilters } from './use-transactions-filters';

const DEFAULT_SORT: DataTableSort<TransactionSortField> = { field: 'processed_at', direction: 'desc' };

type Props = {
    timezone: string;
    today: string;
    customerFilter: ReactNode;
};

export function TransactionsTable({ timezone, today, customerFilter }: Props) {
    const { t, i18n } = useTranslation('admin');
    const query = useDataTableQuery({ sortableFields: TRANSACTION_SORT_FIELDS, defaultSort: DEFAULT_SORT });
    const filters = useTransactionsFilters(today);

    const transactions = useTransactions({
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

    const columns = useMemo(() => transactionColumns({ t, formatInstant }), [t, formatInstant]);

    return (
        <DataTable
            columns={columns}
            data={transactions.data?.data ?? []}
            getRowId={(transaction) => transaction.id}
            caption={t('payments.history.transactions.caption')}
            pagination={query.pagination}
            onPaginationChange={query.onPaginationChange}
            sorting={query.sorting}
            onSortingChange={query.onSortingChange}
            pageCount={transactions.data?.meta.last_page ?? 0}
            totalRows={transactions.data?.meta.total ?? 0}
            status={dataTableStatus(transactions.isPending, transactions.isError)}
            isFetching={transactions.isFetching}
            showsPreviousRows={transactions.isPlaceholderData}
            onRetry={() => void transactions.refetch()}
            emptyState={
                filters.hasActiveFilters ? (
                    <FilteredPaymentsEmptyState onClearFilters={filters.clearFilters} />
                ) : (
                    <PaymentHistoryEmptyState
                        icon={ArrowLeftRight}
                        title={t('payments.history.transactions.empty.title')}
                        body={t('payments.history.transactions.empty.body')}
                    />
                )
            }
            renderCard={(transaction) => (
                <TransactionCard transaction={transaction} processedAt={formatInstant(transaction.processed_at)} />
            )}
            toolbar={{
                content: <TransactionsFilterBar filters={filters} today={today} customerFilter={customerFilter} />,
                hasActiveFilters: filters.hasActiveFilters,
            }}
        />
    );
}
