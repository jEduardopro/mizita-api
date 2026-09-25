import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { formatPhoneNumber } from '@/lib/phone';
import type { TeamMember } from '../types';
import { TeamMemberAvatar } from './TeamMemberAvatar';
import { TeamMemberBadges } from './TeamMemberBadges';
import { TeamMemberRowActions } from './TeamMemberRowActions';
import { teamMemberShowUrl } from './team-urls';

type Params = {
    t: TFunction<'admin'>;
};

export function teamColumns({ t }: Params): ColumnDef<TeamMember>[] {
    const notProvided = t('team.notProvided');

    return [
        {
            id: 'name',
            accessorKey: 'name',
            header: t('team.columns.name'),
            cell: ({ row }) => (
                <div className="flex min-w-0 items-center gap-3">
                    <TeamMemberAvatar name={row.original.name} photoUrl={row.original.photo_url} />

                    <div className="grid min-w-0">
                        <Link
                            href={teamMemberShowUrl(row.original.id)}
                            className="truncate font-medium outline-none hover:underline focus-visible:underline"
                        >
                            {row.original.name}
                        </Link>

                        {row.original.job_title === null ? null : (
                            <span className="truncate text-xs text-muted-foreground">
                                {row.original.job_title}
                            </span>
                        )}
                    </div>
                </div>
            ),
        },
        {
            id: 'email',
            header: t('team.columns.email'),
            enableSorting: false,
            cell: ({ row }) => <span className="text-muted-foreground">{row.original.email}</span>,
        },
        {
            id: 'phone',
            header: t('team.columns.phone'),
            enableSorting: false,
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.phone === null ? notProvided : formatPhoneNumber(row.original.phone)}
                </span>
            ),
        },
        {
            id: 'level',
            header: t('team.columns.level'),
            enableSorting: false,
            cell: ({ row }) => (
                <TeamMemberBadges
                    level={row.original.level}
                    invitationPending={row.original.invitation_pending}
                />
            ),
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">{t('team.columns.actions')}</span>,
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <TeamMemberRowActions member={row.original} />
                </div>
            ),
        },
    ];
}
