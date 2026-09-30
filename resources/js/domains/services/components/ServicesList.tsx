import { LoaderCircle } from 'lucide-react';
import { useCallback, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTableError } from '@/components/shared/data-table/DataTableError';
import { DataTableToolbarSlot } from '@/components/shared/data-table/DataTableToolbarSlot';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableToolbar } from '@/components/shared/data-table/types';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useIntersection } from '@/hooks/use-intersection';
import { useInfiniteServices } from '../queries';
import { ServiceListRow } from './ServiceListRow';
import { serviceListFilters } from './service-list-filters';

const PLACEHOLDER_ROWS = [0, 1, 2, 3, 4];

type Props = {
    search: string;
    staffIds: readonly string[];
    toolbar: DataTableToolbar;
    emptyState: ReactNode;
};

export function ServicesList({ search, staffIds, toolbar, emptyState }: Props) {
    const { t } = useTranslation('common');
    const services = useInfiniteServices(serviceListFilters(search, staffIds));

    const { fetchNextPage, hasNextPage, isFetchingNextPage } = services;

    const loadMore = useCallback(() => void fetchNextPage(), [fetchNextPage]);

    const sentinelRef = useIntersection({
        enabled: hasNextPage && ! isFetchingNextPage,
        onIntersect: loadMore,
    });

    const status = dataTableStatus(services.isPending, services.isError);
    const rows = services.data?.pages.flatMap((page) => page.data) ?? [];

    return (
        <div className="grid gap-4">
            <DataTableToolbarSlot
                toolbar={toolbar}
                status={status}
                rowCount={rows.length}
                showsPreviousRows={services.isPlaceholderData}
            />

            {status === 'pending' ? (
                <div role="status" aria-busy="true" className="grid gap-3">
                    {PLACEHOLDER_ROWS.map((row) => (
                        <Skeleton key={row} className="h-[4.5rem] rounded-xl" />
                    ))}
                </div>
            ) : null}

            {status === 'error' ? (
                <DataTableError onRetry={() => void services.refetch()} />
            ) : null}

            {status === 'ready' && rows.length === 0 ? emptyState : null}

            {status === 'ready' && rows.length > 0 ? (
                <div className="grid gap-3">
                    {rows.map((service) => (
                        <ServiceListRow key={service.id} service={service} />
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
