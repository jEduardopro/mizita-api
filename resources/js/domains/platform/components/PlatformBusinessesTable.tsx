import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTable } from '@/components/shared/data-table/DataTable';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableSort } from '@/components/shared/data-table/types';
import { useDataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { useDebouncedSearch } from '@/components/shared/data-table/use-debounced-search';
import { usePlatformBusinesses } from '../queries';
import { PLATFORM_BUSINESS_SORT_FIELDS, type PlatformBusinessSortField } from '../types';
import { createdDateFormatter } from './platform-business-format';
import { platformBusinessColumns } from './platform-business-columns';
import { PlatformBusinessCard } from './PlatformBusinessCard';
import { PlatformBusinessesEmptyState } from './PlatformBusinessesEmptyState';
import { PlatformBusinessesToolbar } from './PlatformBusinessesToolbar';
import { useExpiredSessionRedirect } from './use-expired-session-redirect';

const DEFAULT_SORT: DataTableSort<PlatformBusinessSortField> = { field: 'created_at', direction: 'desc' };

const SEARCH_DEBOUNCE_MS = 400;

export function PlatformBusinessesTable() {
    const { t, i18n } = useTranslation('platform');

    const query = useDataTableQuery({
        sortableFields: PLATFORM_BUSINESS_SORT_FIELDS,
        defaultSort: DEFAULT_SORT,
    });

    const searchInput = useDebouncedSearch(query.search, query.setSearch, SEARCH_DEBOUNCE_MS);

    const businesses = usePlatformBusinesses({
        page: query.page,
        per_page: query.perPage,
        sort: query.sort.field,
        direction: query.sort.direction,
        search: query.search === '' ? undefined : query.search,
    });

    useExpiredSessionRedirect(businesses.error);

    const formatCreatedAt = useMemo(() => createdDateFormatter(i18n.language), [i18n.language]);

    const columns = useMemo(
        () => platformBusinessColumns({ t, formatCreatedAt }),
        [t, formatCreatedAt],
    );

    return (
        <DataTable
            columns={columns}
            data={businesses.data?.data ?? []}
            getRowId={(business) => business.id}
            caption={t('businesses.caption')}
            pagination={query.pagination}
            onPaginationChange={query.onPaginationChange}
            sorting={query.sorting}
            onSortingChange={query.onSortingChange}
            pageCount={businesses.data?.meta.last_page ?? 0}
            totalRows={businesses.data?.meta.total ?? 0}
            status={dataTableStatus(businesses.isPending, businesses.isError)}
            isFetching={businesses.isFetching}
            showsPreviousRows={businesses.isPlaceholderData}
            onRetry={() => void businesses.refetch()}
            emptyState={
                <PlatformBusinessesEmptyState search={query.search} onClearSearch={searchInput.clear} />
            }
            renderCard={(business) => (
                <PlatformBusinessCard business={business} createdAt={formatCreatedAt(business.created_at)} />
            )}
            toolbar={{
                content: (
                    <PlatformBusinessesToolbar
                        search={searchInput.input}
                        onSearchChange={searchInput.setInput}
                    />
                ),
                hasActiveFilters: query.search !== '',
            }}
        />
    );
}
