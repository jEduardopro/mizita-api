import { AppointmentCustomerField } from './AppointmentCustomerField';
import { AppointmentDateTimeField } from './AppointmentDateTimeField';
import { AppointmentNotesField } from './AppointmentNotesField';
import { AppointmentServiceField } from './AppointmentServiceField';
import { AppointmentStaffField } from './AppointmentStaffField';
import type { AppointmentFormController, AppointmentFormMode } from './use-appointment-form';

type Props = {
    form: AppointmentFormController;
    mode: AppointmentFormMode;
    assignedStaffMemberId: string | null;
};

export function AppointmentFormFields({ form, mode, assignedStaffMemberId }: Props) {
    return (
        <div className="grid gap-4">
            <AppointmentServiceField form={form} />
            <AppointmentCustomerField form={form} />
            <AppointmentStaffField form={form} mode={mode} assignedStaffMemberId={assignedStaffMemberId} />
            <AppointmentDateTimeField form={form} />
            <AppointmentNotesField form={form} />
        </div>
    );
}
