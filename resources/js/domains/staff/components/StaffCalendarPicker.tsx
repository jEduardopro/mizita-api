import { Check, CirclePause } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { useIsTeamMemberPaused } from '@/hooks/use-is-team-member-paused';
import type { StaffMember } from '../types';
import { TeamMemberAvatar } from './TeamMemberAvatar';

type PickableStaffMember = Pick<StaffMember, 'id' | 'name' | 'role' | 'photo_url'>;

type Props = {
    ownStaffMember: PickableStaffMember;
    teamMembers: PickableStaffMember[];
    selectedStaffMemberId: string;
    onSelect: (staffMemberId: string) => void;
};

export function StaffCalendarPicker({ ownStaffMember, teamMembers, selectedStaffMemberId, onSelect }: Props) {
    const { t } = useTranslation('admin');

    return (
        <div className="grid gap-4 p-2">
            <StaffCalendarGroup
                label={t('calendar.staffPicker.yourCalendar')}
                members={[ownStaffMember]}
                selectedStaffMemberId={selectedStaffMemberId}
                onSelect={onSelect}
            />

            <StaffCalendarGroup
                label={t('calendar.staffPicker.team')}
                members={teamMembers}
                selectedStaffMemberId={selectedStaffMemberId}
                onSelect={onSelect}
            />
        </div>
    );
}

type GroupProps = {
    label: string;
    members: PickableStaffMember[];
    selectedStaffMemberId: string;
    onSelect: (staffMemberId: string) => void;
};

function StaffCalendarGroup({ label, members, selectedStaffMemberId, onSelect }: GroupProps) {
    const labelId = useId();
    const isPaused = useIsTeamMemberPaused();

    return (
        <div className="grid gap-1">
            <p id={labelId} className="px-2 pt-2 text-xs font-medium text-muted-foreground">
                {label}
            </p>

            <ul aria-labelledby={labelId} className="grid gap-0.5">
                {members.map((member) => (
                    <li key={member.id}>
                        <StaffCalendarOption
                            name={member.name}
                            photoUrl={member.photo_url}
                            paused={isPaused(member.role)}
                            selected={member.id === selectedStaffMemberId}
                            onSelect={() => onSelect(member.id)}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}

type OptionProps = {
    name: string;
    photoUrl: string | null;
    paused: boolean;
    selected: boolean;
    onSelect: () => void;
};

function StaffCalendarPausedMark() {
    const { t } = useTranslation('admin');

    return (
        <Badge variant="outline" className="bg-muted text-muted-foreground">
            <CirclePause aria-hidden="true" />
            {t('plan.team.paused.badge')}
        </Badge>
    );
}

function StaffCalendarOption({ name, photoUrl, paused, selected, onSelect }: OptionProps) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={onSelect}
            className="flex min-h-11 w-full min-w-0 items-center gap-3 rounded-md px-2 text-left text-sm outline-none transition-colors hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 aria-pressed:bg-muted aria-pressed:font-medium motion-reduce:transition-none"
        >
            <TeamMemberAvatar name={name} photoUrl={photoUrl} size="sm" />

            <span className="min-w-0 flex-1 truncate">{name}</span>

            {paused ? <StaffCalendarPausedMark /> : null}

            {selected ? <Check className="size-4 shrink-0" aria-hidden="true" /> : null}
        </button>
    );
}
