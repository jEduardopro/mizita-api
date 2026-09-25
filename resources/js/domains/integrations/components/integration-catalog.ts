import type { ComponentType, SVGProps } from 'react';
import type { IntegrationCategory, IntegrationConnection, IntegrationKey } from '../types';
import { GoogleCalendarIcon } from './GoogleCalendarIcon';

export type IntegrationDetailProps = {
    connection: IntegrationConnection | null;
    businessName: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export const INTEGRATION_PRESENTATIONS = {
    google_calendar: {
        Logo: GoogleCalendarIcon,
        nameKey: 'integrations.googleCalendar.name',
        descriptionKey: 'integrations.googleCalendar.description',
    },
} as const satisfies Record<
    IntegrationKey,
    { Logo: ComponentType<SVGProps<SVGSVGElement>>; nameKey: string; descriptionKey: string }
>;

export const CATEGORY_PRESENTATIONS = {
    calendar_sync: {
        titleKey: 'integrations.categories.calendar_sync.title',
        descriptionKey: 'integrations.categories.calendar_sync.description',
    },
} as const satisfies Record<IntegrationCategory, { titleKey: string; descriptionKey: string }>;
