import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { comboboxOptionsStatus } from '@/components/form/ComboboxPanel';
import type { DataTableSort, DataTableToolbar } from '@/components/shared/data-table/types';
import { useDataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { Button } from '@/components/ui/button';
import { ActiveServiceQuotaMeter } from '@/domains/services/components/ActiveServiceQuotaMeter';
import { ServicesEmptyState } from '@/domains/services/components/ServicesEmptyState';
import { ServicesList } from '@/domains/services/components/ServicesList';
import { ServiceStaffFilter } from '@/domains/services/components/ServiceStaffFilter';
import { ServicesTable } from '@/domains/services/components/ServicesTable';
import {
    SERVICES_VIEWS,
    ServicesToolbar,
    type ServicesView,
} from '@/domains/services/components/ServicesToolbar';
import { NEW_SERVICE_URL } from '@/domains/services/components/service-urls';
import { useActiveServiceAllowance } from '@/domains/services/components/use-active-service-allowance';
import { useServiceStaffFilter } from '@/domains/services/components/use-service-staff-filter';
import { SERVICE_SORT_FIELDS, type ServiceSortField } from '@/domains/services/types';
import { useStaffChoices } from '@/domains/staff/queries';
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
    const allowance = useActiveServiceAllowance();
    const staff = useStaffChoices();
    const { staffIds, setStaffIds } = useServiceStaffFilter();

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
                filters={
                    <ServiceStaffFilter
                        options={staff.options}
                        status={comboboxOptionsStatus(staff.isPending, staff.isError)}
                        onRetry={staff.refetch}
                        value={staffIds}
                        onChange={setStaffIds}
                    />
                }
                view={view}
                onViewChange={(next) => url.write({ view: next === DEFAULT_VIEW ? null : next })}
            />
        ),
        hasActiveFilters: search !== '' || staffIds.length > 0,
    };

    const emptyState = (
        <ServicesEmptyState
            search={search}
            filteredByStaff={staffIds.length > 0}
            onClearSearch={() => setSearchInput('')}
            onClearStaffFilter={() => setStaffIds([])}
        />
    );

    return (
        <AdminLayout
            title={t('services.title')}
            frame={view === 'table' ? 'viewport' : 'page'}
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
            <div className="flex min-h-0 flex-col gap-4">
                <ActiveServiceQuotaMeter allowance={allowance} />

                {view === 'table' ? (
                    <ServicesTable
                        query={query}
                        staffIds={staffIds}
                        toolbar={toolbar}
                        emptyState={emptyState}
                    />
                ) : (
                    <ServicesList
                        search={search}
                        staffIds={staffIds}
                        toolbar={toolbar}
                        emptyState={emptyState}
                    />
                )}
            </div>
        </AdminLayout>
    );
}
