import { cn } from 'cn';
import { UserRound } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { fieldMessage } from '@/components/form/FieldMessage';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCurrentUser } from '@/hooks/use-current-user';
import { initialsFrom } from '@/lib/initials';
import { useBookableStaffMembers } from '../queries';
import { APPOINTMENT_CONTROL_HEIGHT, AppointmentFormRow } from './AppointmentFormRow';
import type { AppointmentFormMode, AppointmentFormController } from './use-appointment-form';

const FIELD_ID = 'appointment-staff';

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

type Props = {
    form: AppointmentFormController;
    mode: AppointmentFormMode;
};

export function AppointmentStaffField({ form, mode }: Props) {
    const { t } = useTranslation('admin');
    const { data: staffMembers } = useBookableStaffMembers();
    const { data: currentUser } = useCurrentUser();
    const { staffMemberId } = form.values;
    const error = form.errorFor('staffMemberId');
    const message = fieldMessage({ id: FIELD_ID, error });

    useEffect(() => {
        if (mode !== 'create' || staffMemberId !== '' || staffMembers === undefined || currentUser === undefined) {
            return;
        }

        const own = staffMembers.find((member) => member.email === currentUser.email);

        if (own !== undefined) {
            form.update('staffMemberId', own.id);
        }
    }, [mode, staffMemberId, staffMembers, currentUser, form]);

    const members = staffMembers ?? [];
    const selected = members.find((member) => member.id === staffMemberId);

    return (
        <AppointmentFormRow
            icon={<StaffAvatar name={selected?.name ?? null} />}
            label={t('calendar.appointment.form.staff.label')}
            htmlFor={FIELD_ID}
            message={message}
        >
            <Select
                value={staffMemberId === '' ? undefined : staffMemberId}
                onValueChange={(nextId) => form.update('staffMemberId', nextId)}
            >
                <SelectTrigger
                    id={FIELD_ID}
                    aria-invalid={!! error}
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
        </AppointmentFormRow>
    );
}
