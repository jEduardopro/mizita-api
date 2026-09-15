import { Avatar, AvatarFallback, AvatarGroup, AvatarGroupCount } from '@/components/ui/avatar';
import { initialsFrom } from '@/lib/initials';
import type { ServiceStaffMember } from '../types';

const SHOWN_AVATARS = 3;

type Props = {
    staff: ServiceStaffMember[];
};

export function ServiceStaffAvatars({ staff }: Props) {
    if (staff.length === 0) {
        return null;
    }

    const shown = staff.slice(0, SHOWN_AVATARS);
    const remaining = staff.length - shown.length;

    return (
        <AvatarGroup role="img" aria-label={staff.map((member) => member.name).join(', ')}>
            {shown.map((member) => (
                <Avatar key={member.id} size="sm" title={member.name}>
                    <AvatarFallback>{initialsFrom(member.name)}</AvatarFallback>
                </Avatar>
            ))}

            {remaining > 0 ? <AvatarGroupCount>+{remaining}</AvatarGroupCount> : null}
        </AvatarGroup>
    );
}
