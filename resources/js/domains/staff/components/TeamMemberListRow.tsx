import { Link } from '@inertiajs/react';
import type { TeamMember } from '../types';
import { TeamMemberAvatar } from './TeamMemberAvatar';
import { TeamMemberBadges } from './TeamMemberBadges';
import { TeamMemberRowActions } from './TeamMemberRowActions';
import { teamMemberShowUrl } from './team-urls';

type Props = {
    member: TeamMember;
};

export function TeamMemberListRow({ member }: Props) {
    return (
        <article className="relative flex items-center gap-3 rounded-xl border border-border bg-card py-2.5 pr-2.5 pl-4">
            <TeamMemberAvatar name={member.name} photoUrl={member.photo_url} size="lg" />

            <div className="grid min-w-0 flex-1 gap-1">
                <div className="grid min-w-0 gap-0.5">
                    <Link
                        href={teamMemberShowUrl(member.id)}
                        className="truncate text-sm font-medium outline-none after:absolute after:inset-0 after:rounded-xl hover:underline focus-visible:underline focus-visible:after:ring-3 focus-visible:after:ring-ring/50"
                    >
                        {member.name}
                    </Link>

                    <p className="truncate text-xs text-muted-foreground">{member.email}</p>
                </div>

                <TeamMemberBadges level={member.level} invitationPending={member.invitation_pending} />
            </div>

            <div className="relative">
                <TeamMemberRowActions member={member} />
            </div>
        </article>
    );
}
