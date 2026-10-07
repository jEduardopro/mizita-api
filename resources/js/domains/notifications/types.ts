export const NOTIFICATION_SCOPES = ['mine', 'team'] as const;

export type NotificationScope = (typeof NOTIFICATION_SCOPES)[number];

export const NOTIFICATION_STATUSES = ['unread', 'all'] as const;

export type NotificationStatus = (typeof NOTIFICATION_STATUSES)[number];

export type NotificationType = 'appointment_booked' | 'staff_schedule_changed';

export type NotificationRecipient = {
    id: string;
    name: string;
};

export type NotifiedAppointment = {
    id: string;
    starts_at: string;
    ends_at: string;
    service_name: string;
    reference_code: string | null;
};

export type NotifiedCustomer = {
    id: string;
    name: string;
};

export type NotifiedStaffMember = {
    id: string;
    name: string;
};

export type StaffNotification = {
    id: string;
    type: NotificationType;
    read_at: string | null;
    created_at: string;
    can_mark_as_read: boolean;
    recipient: NotificationRecipient;
    appointment: NotifiedAppointment | null;
    customer: NotifiedCustomer | null;
    staff_member: NotifiedStaffMember | null;
};

export type NotificationListFilters = {
    scope: NotificationScope;
    status: NotificationStatus;
};

export type NotificationListParams = NotificationListFilters & {
    page: number;
    per_page: number;
};

export type UnreadNotificationCount = {
    count: number;
};
