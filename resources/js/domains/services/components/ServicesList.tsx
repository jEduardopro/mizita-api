import { LoaderCircle } from 'lucide-react';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTableError } from '@/components/shared/data-table/DataTableError';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useIntersection } from '@/hooks/use-intersection';
import { useInfiniteServices } from '../queries';
import { ServiceListRow } from './ServiceListRow';
import { ServicesEmptyState } from './ServicesEmptyState';

const PLACEHOLDER_ROWS = [0, 1, 2, 3, 4];

type Props = {
    search: string;
    onClearSearch: () => void;
};

export function ServicesList({ search, onClearSearch }: Props) {
    const { t } = useTranslation('common');
    const services = useInfiniteServices(search);

    const { fetchNextPage, hasNextPage, isFetchingNextPage } = services;

    const loadMore = useCallback(() => void fetchNextPage(), [fetchNextPage]);

    const sentinelRef = useIntersection({
        enabled: hasNextPage && ! isFetchingNextPage,
        onIntersect: loadMore,
    });

    if (services.isPending) {
        return (
            <div role="status" aria-busy="true" className="grid gap-3">
                {PLACEHOLDER_ROWS.map((row) => (
                    <Skeleton key={row} className="h-[4.5rem] rounded-xl" />
                ))}
            </div>
        );
    }

    if (services.isError) {
        return <DataTableError onRetry={() => void services.refetch()} />;
    }

    const rows = services.data.pages.flatMap((page) => page.data);

    if (rows.length === 0) {
        return <ServicesEmptyState search={search} onClearSearch={onClearSearch} />;
    }

    return (
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
                            <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
                            {t('actions.loadingMore')}
                        </>
                    ) : (
                        t('actions.loadMore')
                    )}
                </Button>
            ) : null}
        </div>
    );
}
