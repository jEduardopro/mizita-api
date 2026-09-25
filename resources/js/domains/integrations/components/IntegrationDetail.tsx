import type { ComponentType } from 'react';
import type { Integration, IntegrationKey } from '../types';
import { GoogleCalendarDetail } from './GoogleCalendarDetail';
import type { IntegrationDetailProps } from './integration-catalog';

const INTEGRATION_DETAILS = {
    google_calendar: GoogleCalendarDetail,
} as const satisfies Record<IntegrationKey, ComponentType<IntegrationDetailProps>>;

type Props = Omit<IntegrationDetailProps, 'connection'> & {
    integration: Integration;
};

export function IntegrationDetail({ integration, ...detail }: Props) {
    const Detail = INTEGRATION_DETAILS[integration.key];

    return <Detail connection={integration.connection} {...detail} />;
}
