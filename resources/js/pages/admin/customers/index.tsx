import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { DataTableSort, DataTableToolbar } from '@/components/shared/data-table/types';
import { useDataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { Button } from '@/components/ui/button';
import { useBusinessTimezone } from '@/domains/businesses/queries';
import { CustomersList } from '@/domains/customers/components/CustomersList';
import { CustomersTable } from '@/domains/customers/components/CustomersTable';
import {
    CUSTOMERS_VIEWS,
    CustomersToolbar,
    type CustomersView,
} from '@/domains/customers/components/CustomersToolbar';
import { NEW_CUSTOMER_URL } from '@/domains/customers/components/customer-urls';
import { useCustomerRegistrationRange } from '@/domains/customers/components/use-customer-registration-range';
import { CUSTOMER_SORT_FIELDS, type CustomerSortField } from '@/domains/customers/types';
import { useAuthorization } from '@/hooks/use-authorization';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useIsDesktop } from '@/hooks/use-is-desktop';
import { useUrlQueryState } from '@/hooks/use-url-query-state';
import { AdminLayout } from '@/layouts/AdminLayout';
import { isoDateIn } from '@/lib/timezone';

const SEARCH_DEBOUNCE_MS = 400;

const DEFAULT_SORT: DataTableSort<CustomerSortField> = { field: 'name', direction: 'asc' };

const DEFAULT_VIEW: CustomersView = 'table';

const MOBILE_VIEW: CustomersView = 'list';

export default function CustomersIndex() {
    const { t } = useTranslation('admin');
    const url = useUrlQueryState();
    const { can } = useAuthorization();
    const isDesktop = useIsDesktop();
    const { registrationRange, applyRegistrationRange, clearRegistrationRange } =
        useCustomerRegistrationRange();
    const businessTimezone = useBusinessTimezone();
    const businessToday =
        businessTimezone === null ? undefined : (isoDateIn(businessTimezone, new Date()) ?? undefined);

    const query = useDataTableQuery({
        sortableFields: CUSTOMER_SORT_FIELDS,
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
        ? (CUSTOMERS_VIEWS.find((candidate) => candidate === requestedView) ?? DEFAULT_VIEW)
        : MOBILE_VIEW;

    function clearFilters() {
        setSearchInput('');
        clearRegistrationRange();
    }

    const toolbar: DataTableToolbar = {
        content: (
            <CustomersToolbar
                search={searchInput}
                onSearchChange={setSearchInput}
                registrationRange={registrationRange}
                onRegistrationRangeApply={applyRegistrationRange}
                onRegistrationRangeClear={clearRegistrationRange}
                today={businessToday}
                view={view}
                onViewChange={(next) => url.write({ view: next === DEFAULT_VIEW ? null : next })}
            />
        ),
        hasActiveFilters: search !== '' || registrationRange !== null,
    };

    return (
        <AdminLayout
            title={t('customers.title')}
            frame={view === 'table' ? 'viewport' : 'page'}
            actions={
                can('create_customer') ? (
                    <Button asChild variant="brand" className="h-11 px-4 md:h-9">
                        <Link href={NEW_CUSTOMER_URL}>
                            <Plus aria-hidden="true" />
                            {t('customers.actions.create')}
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            {view === 'table' ? (
                <CustomersTable
                    query={query}
                    registrationRange={registrationRange}
                    toolbar={toolbar}
                    onClearFilters={clearFilters}
                />
            ) : (
                <CustomersList
                    search={search}
                    registrationRange={registrationRange}
                    onClearFilters={clearFilters}
                    toolbar={toolbar}
                />
            )}
        </AdminLayout>
    );
}
