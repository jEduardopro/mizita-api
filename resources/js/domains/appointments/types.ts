import type { ServiceColor } from '@/lib/service-color';

export type AppointmentCustomerPhone = {
    country_code: string;
    national_number: string;
};

export type AppointmentCustomer = {
    id: string;
    name: string;
    email: string | null;
    phone: AppointmentCustomerPhone | null;
};

export type AppointmentService = {
    id: string;
    name: string;
    color: ServiceColor;
    duration_minutes: number;
    buffer_minutes: number;
    price: string;
};

export type AppointmentStaffMember = {
    id: string;
    name: string;
};

export type AppointmentStatus = 'booked' | 'cancelled';

export type AppointmentCanceller = 'customer' | 'business';

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
    status: AppointmentStatus;
    cancelled_at: string | null;
    cancelled_by: AppointmentCanceller | null;
    reference_code: string | null;
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
    buffer_minutes: number;
    price: string;
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
