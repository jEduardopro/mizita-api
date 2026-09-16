import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import type { BrandColorClasses } from '@/lib/booking-brand';
import { initialsFrom } from '@/lib/initials';
import type { PublicTeamMember } from '../types';

type Props = {
    team: PublicTeamMember[];
    accent: BrandColorClasses;
};

export function BookingTeam({ team, accent }: Props) {
    return (
        <ul className="flex flex-wrap gap-x-6 gap-y-4">
            {team.map((member) => (
                <li key={member.id} className="flex min-w-0 items-center gap-3">
                    <Avatar size="lg">
                        <AvatarFallback className={accent.surface}>
                            {initialsFrom(member.name)}
                        </AvatarFallback>
                    </Avatar>

                    <span className="truncate text-sm font-medium">{member.name}</span>
                </li>
            ))}
        </ul>
    );
}
