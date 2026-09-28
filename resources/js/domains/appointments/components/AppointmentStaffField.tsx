import { cn } from 'cn';
import { UserRound } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { fieldMessage, type FieldMessageState } from '@/components/form/FieldMessage';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuthorization } from '@/hooks/use-authorization';
import { initialsFrom } from '@/lib/initials';
import { APPOINTMENT_CONTROL_HEIGHT, AppointmentFormRow } from './AppointmentFormRow';
import { PausedStaffMemberMark } from './PausedStaffMemberMark';
import type { AppointmentFormMode, AppointmentFormController } from './use-appointment-form';
import { useAppointmentStaffOptions, type AppointmentStaffOption } from './use-appointment-staff-options';

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

type PickerProps = {
    options: AppointmentStaffOption[];
    value: string;
    onChange: (staffMemberId: string) => void;
    invalid: boolean;
    message: FieldMessageState | null;
};

function StaffMemberPicker({ options, value, onChange, invalid, message }: PickerProps) {
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
                {options.map((option) => (
                    <SelectItem key={option.id} value={option.id}>
                        <span className="min-w-0 truncate">{option.name}</span>
                        {option.paused ? <PausedStaffMemberMark /> : null}
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
    assignedStaffMemberId: string | null;
};

export function AppointmentStaffField({ form, mode, assignedStaffMemberId }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const { options, defaultStaffMemberId, isPending } = useAppointmentStaffOptions(assignedStaffMemberId);
    const { staffMemberId } = form.values;
    const error = form.errorFor('staffMemberId');
    const message = fieldMessage({ id: FIELD_ID, error });
    const selected = options.find((option) => option.id === staffMemberId);
    const isSelectionOffered = selected !== undefined;

    useEffect(() => {
        if (mode !== 'create' || isSelectionOffered || defaultStaffMemberId === undefined) {
            return;
        }

        form.update('staffMemberId', defaultStaffMemberId);
    }, [mode, isSelectionOffered, defaultStaffMemberId, form]);

    return (
        <AppointmentFormRow
            icon={<StaffAvatar name={selected?.name ?? null} />}
            label={t('calendar.appointment.form.staff.label')}
            htmlFor={FIELD_ID}
            message={message}
        >
            {can('manage_all_calendars') ? (
                <StaffMemberPicker
                    options={options}
                    value={staffMemberId}
                    onChange={(nextId) => form.update('staffMemberId', nextId)}
                    invalid={!! error}
                    message={message}
                />
            ) : (
                <StaffMemberReadout
                    name={selected?.name}
                    loading={isPending}
                    message={message}
                />
            )}
        </AppointmentFormRow>
    );
}
