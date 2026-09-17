import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { DataTableSort, DataTableToolbar } from '@/components/shared/data-table/types';
import { useDataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { Button } from '@/components/ui/button';
import { ServicesList } from '@/domains/services/components/ServicesList';
import { ServicesTable } from '@/domains/services/components/ServicesTable';
import {
    SERVICES_VIEWS,
    ServicesToolbar,
    type ServicesView,
} from '@/domains/services/components/ServicesToolbar';
import { NEW_SERVICE_URL } from '@/domains/services/components/service-urls';
import { SERVICE_SORT_FIELDS, type ServiceSortField } from '@/domains/services/types';
import { useAuthorization } from '@/hooks/use-authorization';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { AdminLayout } from '@/layouts/AdminLayout';

const SEARCH_DEBOUNCE_MS = 400;

const DEFAULT_SORT: DataTableSort<ServiceSortField> = { field: 'name', direction: 'asc' };

const DEFAULT_VIEW: ServicesView = 'table';

const MOBILE_VIEW: ServicesView = 'list';

export default function ServicesIndex() {
    const { t } = useTranslation('admin');
    const url = useUrlQueryState();
    const { can } = useAuthorization();
    const isDesktop = useIsDesktop();

    const query = useDataTableQuery({
        sortableFields: SERVICE_SORT_FIELDS,
        defaultSort: DEFAULT_SORT,
    });

    const [searchInput, setSearchInput] = useState(query.search);
    const [syncedSearch, setSyncedSearch] = useState(query.search);
    const debouncedSearch = useDebouncedValue(searchInput, SEARCH_DEBOUNCE_MS);

    if (query.search !== syncedSearch) {
        setSyncedSearch(query.search);
        setSearchInput(query.search);
    }

    const { search, setSearch } = query;

    useEffect(() => {
        if (debouncedSearch === searchInput && debouncedSearch !== search) {
            setSearch(debouncedSearch);
        }
    }, [debouncedSearch, searchInput, search, setSearch]);

    const requestedView = url.read('view');
    const view = isDesktop
        ? (SERVICES_VIEWS.find((candidate) => candidate === requestedView) ?? DEFAULT_VIEW)
        : MOBILE_VIEW;

    const toolbar: DataTableToolbar = {
        content: (
            <ServicesToolbar
                search={searchInput}
                onSearchChange={setSearchInput}
                view={view}
                onViewChange={(next) => url.write({ view: next === DEFAULT_VIEW ? null : next })}
            />
        ),
        hasActiveFilters: search !== '',
    };

    return (
        <AdminLayout
            title={t('services.title')}
            actions={
                can('create_service') ? (
                    <Button asChild variant="brand" className="h-11 px-4 md:h-9">
                        <Link href={NEW_SERVICE_URL}>
                            <Plus aria-hidden="true" />
                            {t('services.actions.create')}
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            {view === 'table' ? (
                <ServicesTable
                    query={query}
                    toolbar={toolbar}
                    onClearSearch={() => setSearchInput('')}
                />
            ) : (
                <ServicesList
                    search={search}
                    onClearSearch={() => setSearchInput('')}
                    toolbar={toolbar}
                />
            )}
        </AdminLayout>
    );
}
