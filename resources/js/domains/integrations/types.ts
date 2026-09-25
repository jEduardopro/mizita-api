export type IntegrationKey = 'google_calendar';

export type IntegrationCategory = 'calendar_sync';

export type ConnectionStatus = 'connected' | 'needs_reconnect';

export type IntegrationConnection = {
    id: string;
    status: ConnectionStatus;
    account_email: string;
    connected_at: string;
};

export type Integration = {
    key: IntegrationKey;
    category: IntegrationCategory;
    connection: IntegrationConnection | null;
};

export type CalendarAuthorization = {
    authorization_url: string;
};

export const GOOGLE_CALENDAR_CONNECTED_STATUS = 'google-calendar-connected';
