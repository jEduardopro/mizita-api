import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { DataTableSort } from '@/components/shared/data-table/types';
import { useDataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { Button } from '@/components/ui/button';
import { CustomersTable } from '@/domains/customers/components/CustomersTable';
import { CustomersToolbar } from '@/domains/customers/components/CustomersToolbar';
import { NEW_CUSTOMER_URL } from '@/domains/customers/components/customer-urls';
import { CUSTOMER_SORT_FIELDS, type CustomerSortField } from '@/domains/customers/types';
import { useAuthorization } from '@/hooks/use-authorization';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { AdminLayout } from '@/layouts/AdminLayout';

const SEARCH_DEBOUNCE_MS = 400;

const DEFAULT_SORT: DataTableSort<CustomerSortField> = { field: 'name', direction: 'asc' };

export default function CustomersIndex() {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();

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

    return (
        <AdminLayout
            title={t('customers.title')}
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
            <CustomersTable
                query={query}
                toolbar={{
                    content: (
                        <CustomersToolbar search={searchInput} onSearchChange={setSearchInput} />
                    ),
                    hasActiveFilters: search !== '',
                }}
                onClearSearch={() => setSearchInput('')}
            />
        </AdminLayout>
    );
}
