import { UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { DataTableSort } from '@/components/shared/data-table/types';
import { useDataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { Button } from '@/components/ui/button';
import { BRAND_SETTINGS_URL } from '@/domains/businesses/components/settings-urls';
import { InviteTeamMembersDialog } from '@/domains/staff/components/InviteTeamMembersDialog';
import { TeamTable } from '@/domains/staff/components/TeamTable';
import { TeamToolbar } from '@/domains/staff/components/TeamToolbar';
import { TEAM_SORT_FIELDS, type TeamSortField } from '@/domains/staff/types';
import { useAuthorization } from '@/hooks/use-authorization';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { AdminLayout } from '@/layouts/AdminLayout';

const SEARCH_DEBOUNCE_MS = 400;

const DEFAULT_SORT: DataTableSort<TeamSortField> = { field: 'name', direction: 'asc' };

export default function Team() {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const [isInviting, setIsInviting] = useState(false);

    const query = useDataTableQuery({
        sortableFields: TEAM_SORT_FIELDS,
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
            title={t('team.title')}
            description={t('team.description')}
            breadcrumbs={[
                { label: t('nav.settings'), href: BRAND_SETTINGS_URL },
                { label: t('nav.team') },
            ]}
            actions={
                can('create_staff_member') ? (
                    <Button
                        type="button"
                        variant="brand"
                        onClick={() => setIsInviting(true)}
                        className="h-11 px-4 md:h-9"
                    >
                        <UserPlus aria-hidden="true" />
                        {t('team.actions.invite')}
                    </Button>
                ) : undefined
            }
        >
            <TeamTable
                query={query}
                toolbar={{
                    content: <TeamToolbar search={searchInput} onSearchChange={setSearchInput} />,
                    hasActiveFilters: search !== '',
                }}
                onClearSearch={() => setSearchInput('')}
                onInvite={() => setIsInviting(true)}
            />

            {isInviting ? <InviteTeamMembersDialog onClose={() => setIsInviting(false)} /> : null}
        </AdminLayout>
    );
}
