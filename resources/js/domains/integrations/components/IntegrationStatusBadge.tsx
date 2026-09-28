import { cn } from 'cn';
import { Check, CirclePause, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { ConnectionStatus } from '../types';

export type IntegrationBadgeStatus = ConnectionStatus | 'sync_paused';

const STATUS_LABEL_KEYS = {
    connected: 'integrations.status.connected',
    needs_reconnect: 'integrations.status.needs_reconnect',
    sync_paused: 'plan.integrations.syncPaused.title',
} as const satisfies Record<IntegrationBadgeStatus, string>;

const STATUS_TONE_CLASSES = {
    connected: 'bg-success/12 text-success',
    needs_reconnect: 'bg-warning/12 text-warning',
    sync_paused: 'bg-muted text-muted-foreground',
} as const satisfies Record<IntegrationBadgeStatus, string>;

const STATUS_ICONS = {
    connected: Check,
    needs_reconnect: TriangleAlert,
    sync_paused: CirclePause,
} as const satisfies Record<IntegrationBadgeStatus, unknown>;

type Props = {
    status: IntegrationBadgeStatus;
};

export function IntegrationStatusBadge({ status }: Props) {
    const { t } = useTranslation('admin');
    const Icon = STATUS_ICONS[status];

    return (
        <Badge variant="secondary" className={cn('rounded-md font-normal', STATUS_TONE_CLASSES[status])}>
            <Icon aria-hidden="true" data-icon="inline-start" />
            {t(STATUS_LABEL_KEYS[status])}
        </Badge>
    );
}
