import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { SelectField } from '@/components/form/SelectField';
import { useCurrentUser } from '@/hooks/use-current-user';
import { useBookableStaffMembers } from '../queries';
import type { AppointmentFormMode, AppointmentFormController } from './use-appointment-form';

type Props = {
    form: AppointmentFormController;
    mode: AppointmentFormMode;
};

export function AppointmentStaffField({ form, mode }: Props) {
    const { t } = useTranslation('admin');
    const { data: staffMembers } = useBookableStaffMembers();
    const { data: currentUser } = useCurrentUser();
    const { staffMemberId } = form.values;

    useEffect(() => {
        if (mode !== 'create' || staffMemberId !== '' || staffMembers === undefined || currentUser === undefined) {
            return;
        }

        const own = staffMembers.find((member) => member.email === currentUser.email);

        if (own !== undefined) {
            form.update('staffMemberId', own.id);
        }
    }, [mode, staffMemberId, staffMembers, currentUser, form]);

    const options = [
        { value: '', label: t('calendar.appointment.form.staff.placeholder') },
        ...(staffMembers ?? []).map((member) => ({ value: member.id, label: member.name })),
    ];

    return (
        <SelectField
            id="appointment-staff"
            label={t('calendar.appointment.form.staff.label')}
            options={options}
            value={staffMemberId}
            onChange={(event) => form.update('staffMemberId', event.target.value)}
            error={form.errorFor('staffMemberId')}
        />
    );
}
