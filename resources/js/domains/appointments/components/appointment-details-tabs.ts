export const APPOINTMENT_DETAILS_TABS = ['details', 'payments'] as const;

export type AppointmentDetailsTab = (typeof APPOINTMENT_DETAILS_TABS)[number];

export const DEFAULT_APPOINTMENT_DETAILS_TAB: AppointmentDetailsTab = 'details';

export function appointmentDetailsTabFrom(value: string | null): AppointmentDetailsTab {
    return (
        APPOINTMENT_DETAILS_TABS.find((candidate) => candidate === value)
        ?? DEFAULT_APPOINTMENT_DETAILS_TAB
    );
}
