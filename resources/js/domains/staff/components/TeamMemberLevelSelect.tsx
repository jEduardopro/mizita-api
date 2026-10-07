import { useTranslation } from 'react-i18next';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { AssignableStaffRole } from '../types';
import { assignableStaffRoleFrom, ROLE_LABEL_KEYS } from './profile-role';
import { TeamLevelOptions } from './TeamLevelOptions';
import { useTeamMemberLevel } from './use-team-member-level';

const INLINE_TRIGGER =
    'w-fit gap-1.5 rounded-sm border-0 bg-transparent p-0 text-muted-foreground hover:text-foreground data-[size=default]:h-11 data-[state=open]:text-foreground md:data-[size=default]:h-9 dark:bg-transparent dark:hover:bg-transparent [&_svg]:size-3.5 [&_svg]:opacity-60';

type Props = {
    memberId: string;
    savedLevel: AssignableStaffRole;
};

export function TeamMemberLevelSelect({ memberId, savedLevel }: Props) {
    const { t } = useTranslation('admin');
    const { level, change, isPending } = useTeamMemberLevel(memberId, savedLevel);

    function select(next: string) {
        const selected = assignableStaffRoleFrom(next);

        if (selected !== undefined) {
            change(selected);
        }
    }

    return (
        <Select value={level} onValueChange={select} disabled={isPending}>
            <SelectTrigger aria-label={t('profile.about.role')} aria-busy={isPending} className={INLINE_TRIGGER}>
                <SelectValue>{t(ROLE_LABEL_KEYS[level])}</SelectValue>
            </SelectTrigger>

            <TeamLevelOptions align="start" />
        </Select>
    );
}
