import { cn } from 'cn';
import { UserRound } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { fieldMessage, type FieldMessageState } from '@/components/form/FieldMessage';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuthorization } from '@/hooks/use-authorization';
import { useCurrentUser } from '@/hooks/use-current-user';
import { initialsFrom } from '@/lib/initials';
import { useBookableStaffMembers } from '../queries';
import type { BookableStaffMember } from '../types';
import { APPOINTMENT_CONTROL_HEIGHT, AppointmentFormRow } from './AppointmentFormRow';
import type { AppointmentFormMode, AppointmentFormController } from './use-appointment-form';

const FIELD_ID = 'appointment-staff';

const UNRESOLVED_NAME = '—';

type AvatarProps = {
    name: string | null;
};

function StaffAvatar({ name }: AvatarProps) {
    return (
        <Avatar size="sm">
            <AvatarFallback className="font-semibold">
                {name === null ? <UserRound aria-hidden="true" className="size-3.5" /> : initialsFrom(name)}
            </AvatarFallback>
        </Avatar>
    );
}

function useOwnStaffMember(members: BookableStaffMember[] | undefined): BookableStaffMember | undefined {
    const { data: currentUser } = useCurrentUser();

    if (members === undefined || currentUser === undefined) {
        return undefined;
    }

    return members.find((member) => member.email === currentUser.email);
}

type PickerProps = {
    members: BookableStaffMember[];
    value: string;
    onChange: (staffMemberId: string) => void;
    invalid: boolean;
    message: FieldMessageState | null;
};

function StaffMemberPicker({ members, value, onChange, invalid, message }: PickerProps) {
    const { t } = useTranslation('admin');

    return (
        <Select value={value === '' ? undefined : value} onValueChange={onChange}>
            <SelectTrigger
                id={FIELD_ID}
                aria-invalid={invalid}
                aria-describedby={message?.id}
                className={cn('w-full', APPOINTMENT_CONTROL_HEIGHT)}
            >
                <SelectValue placeholder={t('calendar.appointment.form.staff.placeholder')} />
            </SelectTrigger>

            <SelectContent>
                {members.map((member) => (
                    <SelectItem key={member.id} value={member.id}>
                        {member.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

type ReadoutProps = {
    name: string | undefined;
    loading: boolean;
    message: FieldMessageState | null;
};

function StaffMemberReadout({ name, loading, message }: ReadoutProps) {
    if (loading) {
        return <Skeleton className={cn('w-40', APPOINTMENT_CONTROL_HEIGHT)} />;
    }

    return (
        <output
            id={FIELD_ID}
            aria-describedby={message?.id}
            className={cn(
                'flex items-center text-base font-medium md:text-sm',
                name === undefined && 'text-muted-foreground',
                APPOINTMENT_CONTROL_HEIGHT,
            )}
        >
            {name ?? UNRESOLVED_NAME}
        </output>
    );
}

type Props = {
    form: AppointmentFormController;
    mode: AppointmentFormMode;
};

export function AppointmentStaffField({ form, mode }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const { data: staffMembers, isPending: isStaffPending } = useBookableStaffMembers();
    const ownStaffMember = useOwnStaffMember(staffMembers);
    const { staffMemberId } = form.values;
    const error = form.errorFor('staffMemberId');
    const message = fieldMessage({ id: FIELD_ID, error });

    useEffect(() => {
        if (mode !== 'create' || staffMemberId !== '' || ownStaffMember === undefined) {
            return;
        }

        form.update('staffMemberId', ownStaffMember.id);
    }, [mode, staffMemberId, ownStaffMember, form]);

    const members = staffMembers ?? [];
    const selected = members.find((member) => member.id === staffMemberId);

    return (
        <AppointmentFormRow
            icon={<StaffAvatar name={selected?.name ?? null} />}
            label={t('calendar.appointment.form.staff.label')}
            htmlFor={FIELD_ID}
            message={message}
        >
            {can('manage_all_calendars') ? (
                <StaffMemberPicker
                    members={members}
                    value={staffMemberId}
                    onChange={(nextId) => form.update('staffMemberId', nextId)}
                    invalid={!! error}
                    message={message}
                />
            ) : (
                <StaffMemberReadout
                    name={selected?.name}
                    loading={isStaffPending}
                    message={message}
                />
            )}
        </AppointmentFormRow>
    );
}
