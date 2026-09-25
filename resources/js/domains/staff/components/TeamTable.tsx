import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { DataTable } from '@/components/shared/data-table/DataTable';
import { dataTableStatus } from '@/components/shared/data-table/status';
import type { DataTableToolbar } from '@/components/shared/data-table/types';
import type { DataTableQuery } from '@/components/shared/data-table/use-data-table-query';
import { useTeamMembers } from '../queries';
import type { TeamSortField } from '../types';
import { teamColumns } from './team-columns';
import { TeamEmptyState } from './TeamEmptyState';
import { TeamMemberListRow } from './TeamMemberListRow';

type Props = {
    query: DataTableQuery<TeamSortField>;
    toolbar: DataTableToolbar;
    onClearSearch: () => void;
    onInvite: () => void;
};

export function TeamTable({ query, toolbar, onClearSearch, onInvite }: Props) {
    const { t } = useTranslation('admin');

    const team = useTeamMembers({
        page: query.page,
        per_page: query.perPage,
        sort: query.sort.field,
        direction: query.sort.direction,
        search: query.search === '' ? undefined : query.search,
    });

    const columns = useMemo(() => teamColumns({ t }), [t]);

    return (
        <DataTable
            columns={columns}
            data={team.data?.data ?? []}
            getRowId={(member) => member.id}
            caption={t('team.table.caption')}
            pagination={query.pagination}
            onPaginationChange={query.onPaginationChange}
            sorting={query.sorting}
            onSortingChange={query.onSortingChange}
            pageCount={team.data?.meta.last_page ?? 0}
            totalRows={team.data?.meta.total ?? 0}
            status={dataTableStatus(team.isPending, team.isError)}
            isFetching={team.isFetching}
            showsPreviousRows={team.isPlaceholderData}
            onRetry={() => void team.refetch()}
            emptyState={
                <TeamEmptyState
                    search={query.search}
                    onClearSearch={onClearSearch}
                    onInvite={onInvite}
                />
            }
            renderCard={(member) => <TeamMemberListRow member={member} />}
            toolbar={toolbar}
        />
    );
}
