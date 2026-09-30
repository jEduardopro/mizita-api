import { LoaderCircle } from 'lucide-react';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTableError } from '@/components/shared/data-table/DataTableError';
import { DataTableToolbarSlot } from '@/components/shared/data-table/DataTableToolbarSlot';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableToolbar } from '@/components/shared/data-table/types';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useIntersection } from '@/hooks/use-intersection';
import { useInfiniteCustomers } from '../queries';
import type { CustomerRegistrationRange } from '../types';
import { customerListFilters } from './customer-list-filters';
import { CustomerListRow } from './CustomerListRow';
import { CustomersEmptyState } from './CustomersEmptyState';

const PLACEHOLDER_ROWS = [0, 1, 2, 3, 4];

type Props = {
    search: string;
    registrationRange: CustomerRegistrationRange | null;
    onClearFilters: () => void;
    toolbar: DataTableToolbar;
};

export function CustomersList({ search, registrationRange, onClearFilters, toolbar }: Props) {
    const { t } = useTranslation('common');
    const customers = useInfiniteCustomers(customerListFilters(search, registrationRange));

    const { fetchNextPage, hasNextPage, isFetchingNextPage } = customers;

    const loadMore = useCallback(() => void fetchNextPage(), [fetchNextPage]);

    const sentinelRef = useIntersection({
        enabled: hasNextPage && ! isFetchingNextPage,
        onIntersect: loadMore,
    });

    const status = dataTableStatus(customers.isPending, customers.isError);
    const rows = customers.data?.pages.flatMap((page) => page.data) ?? [];

    return (
        <div className="grid gap-4">
            <DataTableToolbarSlot
                toolbar={toolbar}
                status={status}
                rowCount={rows.length}
                showsPreviousRows={customers.isPlaceholderData}
            />

            {status === 'pending' ? (
                <div role="status" aria-busy="true" className="grid gap-3">
                    {PLACEHOLDER_ROWS.map((row) => (
                        <Skeleton key={row} className="h-[4.5rem] rounded-xl" />
                    ))}
                </div>
            ) : null}

            {status === 'error' ? (
                <DataTableError onRetry={() => void customers.refetch()} />
            ) : null}

            {status === 'ready' && rows.length === 0 ? (
                <CustomersEmptyState
                    search={search}
                    hasRegistrationRange={registrationRange !== null}
                    onClearFilters={onClearFilters}
                />
            ) : null}

            {status === 'ready' && rows.length > 0 ? (
                <div className="grid gap-3">
                    {rows.map((customer) => (
                        <CustomerListRow key={customer.id} customer={customer} />
                    ))}

                    <div ref={sentinelRef} aria-hidden="true" />

                    {hasNextPage ? (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isFetchingNextPage}
                            onClick={loadMore}
                            className="h-11 justify-self-center px-5 md:h-9"
                        >
                            {isFetchingNextPage ? (
                                <>
                                    <LoaderCircle
                                        aria-hidden="true"
                                        className="motion-safe:animate-spin"
                                    />
                                    {t('actions.loadingMore')}
                                </>
                            ) : (
                                t('actions.loadMore')
                            )}
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
