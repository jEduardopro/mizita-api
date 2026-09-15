import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { DataTableSort } from '@/components/shared/data-table/types';
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
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { AdminLayout } from '@/layouts/AdminLayout';

const SEARCH_DEBOUNCE_MS = 400;

const DEFAULT_SORT: DataTableSort<ServiceSortField> = { field: 'name', direction: 'asc' };

const DEFAULT_VIEW: ServicesView = 'table';

export default function ServicesIndex() {
    const { t } = useTranslation('admin');
    const url = useUrlQueryState();

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
    const view = SERVICES_VIEWS.find((candidate) => candidate === requestedView) ?? DEFAULT_VIEW;

    const toolbar = (
        <ServicesToolbar
            search={searchInput}
            onSearchChange={setSearchInput}
            view={view}
            onViewChange={(next) => url.write({ view: next === DEFAULT_VIEW ? null : next })}
        />
    );

    return (
        <AdminLayout
            title={t('services.title')}
            actions={
                <Button asChild variant="brand" className="h-11 px-4 md:h-9">
                    <Link href={NEW_SERVICE_URL}>
                        <Plus aria-hidden="true" />
                        <span className="sr-only sm:not-sr-only">{t('services.actions.create')}</span>
                    </Link>
                </Button>
            }
        >
            {view === 'table' ? (
                <ServicesTable
                    query={query}
                    toolbar={toolbar}
                    onClearSearch={() => setSearchInput('')}
                />
            ) : (
                <div className="grid gap-4">
                    {toolbar}

                    <ServicesList search={search} onClearSearch={() => setSearchInput('')} />
                </div>
            )}
        </AdminLayout>
    );
}
