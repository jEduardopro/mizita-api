import type { FormEvent } from 'react';
import { useAppointmentForm, type AppointmentFormController, type AppointmentFormParams } from './use-appointment-form';

export type AppointmentFormSurfaceProps = AppointmentFormParams & {
    title: string;
    actionLabel: string;
    onCancel: () => void;
};

type AppointmentFormSurface = {
    form: AppointmentFormController;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

export function useAppointmentFormSurface(params: AppointmentFormParams): AppointmentFormSurface {
    const form = useAppointmentForm(params);

    return {
        form,
        submit: (event) => {
            event.preventDefault();
            void form.submit();
        },
    };
}
