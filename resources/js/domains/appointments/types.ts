import type { ServiceColor } from '@/lib/service-color';

export type AppointmentCustomer = {
    id: string;
    name: string;
    email: string | null;
};

export type AppointmentService = {
    id: string;
    name: string;
    color: ServiceColor;
    duration_minutes: number;
};

export type AppointmentStaffMember = {
    id: string;
    name: string;
};

export type Appointment = {
    id: string;
    customer: AppointmentCustomer;
    service: AppointmentService;
    staff_member: AppointmentStaffMember;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    notes: string | null;
    created_at: string;
};

export type AppointmentRange = {
    from: string;
    to: string;
};

export type AppointmentPayload = {
    customer_id: string;
    service_id: string;
    staff_member_id: string;
    starts_at: string;
    ends_at: string | null;
    notes: string | null;
};

export type BookableService = {
    id: string;
    name: string;
    color: ServiceColor;
    duration_minutes: number;
    active: boolean;
};

export type BookableStaffMember = {
    id: string;
    name: string;
    email: string;
};

export type BookableCustomer = {
    id: string;
    name: string;
    email: string | null;
};
