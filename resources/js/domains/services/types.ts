export const SERVICE_COLORS = [
    'red',
    'orange',
    'amber',
    'purple',
    'blue',
    'sand',
    'slate',
    'teal',
    'green',
] as const;

export type ServiceColor = (typeof SERVICE_COLORS)[number];

export const SERVICE_SORT_FIELDS = ['name', 'price', 'duration', 'created_at'] as const;

export type ServiceSortField = (typeof SERVICE_SORT_FIELDS)[number];

export type ServiceStaffMember = {
    id: string;
    name: string;
};

export type Service = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    duration_minutes: number;
    buffer_minutes: number;
    price: string;
    color: ServiceColor;
    active: boolean;
    image_url: string | null;
    booking_url: string;
    staff: ServiceStaffMember[];
    created_at: string;
};

export type ServiceListParams = {
    page: number;
    per_page: number;
    sort: ServiceSortField;
    direction: 'asc' | 'desc';
    search?: string;
};

export type ServicePayload = {
    name: string;
    description: string | null;
    duration_minutes: number;
    buffer_minutes: number;
    price: string;
    color: ServiceColor;
    active: boolean;
    staff_ids: string[];
};

export type DuplicateServicePayload = {
    name: string;
};

export const SERVICE_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;

export const SERVICE_IMAGE_MAXIMUM_BYTES = 2 * 1024 * 1024;
