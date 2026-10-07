export const NOTIFICATION_SCOPES = ['mine', 'team'] as const;

export type NotificationScope = (typeof NOTIFICATION_SCOPES)[number];

export const NOTIFICATION_STATUSES = ['unread', 'all'] as const;

export type NotificationStatus = (typeof NOTIFICATION_STATUSES)[number];

export type NotificationRecipient = {
    id: string;
    name: string;
};

export type NotifiedAppointment = {
    id: string;
    starts_at: string;
    ends_at: string;
    service_name: string;
    reference_code: string;
};

export type NotifiedCustomer = {
    id: string;
    name: string;
};

export type NotifiedStaffMember = {
    id: string;
    name: string;
};

export type AppointmentBookedDetails = {
    appointment: NotifiedAppointment;
    customer: NotifiedCustomer;
};

export type StaffScheduleChangedDetails = {
    staff_member: NotifiedStaffMember;
};

type NotificationBase = {
    id: string;
    read_at: string | null;
    created_at: string;
    can_mark_as_read: boolean;
    recipient: NotificationRecipient;
};

export type AppointmentBookedNotification = NotificationBase & {
    type: 'appointment_booked';
    details: AppointmentBookedDetails;
};

export type StaffScheduleChangedNotification = NotificationBase & {
    type: 'staff_schedule_changed';
    details: StaffScheduleChangedDetails;
};

export type StaffNotification = AppointmentBookedNotification | StaffScheduleChangedNotification;

export type NotificationType = StaffNotification['type'];

export type NotificationOfType<Type extends NotificationType> = Extract<StaffNotification, { type: Type }>;

export type NotificationHandlers<Context, Result> = {
    [Type in NotificationType]: (notification: NotificationOfType<Type>, context: Context) => Result;
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
