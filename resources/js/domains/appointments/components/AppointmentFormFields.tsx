import { AppointmentCustomerField } from './AppointmentCustomerField';
import { AppointmentDateTimeField } from './AppointmentDateTimeField';
import { AppointmentNotesField } from './AppointmentNotesField';
import { AppointmentServiceField } from './AppointmentServiceField';
import { AppointmentStaffField } from './AppointmentStaffField';
import type { AppointmentFormController, AppointmentFormMode } from './use-appointment-form';

type Props = {
    form: AppointmentFormController;
    mode: AppointmentFormMode;
};

export function AppointmentFormFields({ form, mode }: Props) {
    return (
        <div className="grid gap-4">
            <AppointmentServiceField form={form} />
            <AppointmentCustomerField form={form} />
            <AppointmentStaffField form={form} mode={mode} />
            <AppointmentDateTimeField form={form} />
            <AppointmentNotesField form={form} />
        </div>
    );
}
