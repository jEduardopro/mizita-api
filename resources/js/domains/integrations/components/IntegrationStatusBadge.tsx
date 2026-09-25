import { cn } from 'cn';
import { Check, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { ConnectionStatus } from '../types';

const STATUS_LABEL_KEYS = {
    connected: 'integrations.status.connected',
    needs_reconnect: 'integrations.status.needs_reconnect',
} as const satisfies Record<ConnectionStatus, string>;

const STATUS_TONE_CLASSES = {
    connected: 'bg-success/12 text-success',
    needs_reconnect: 'bg-warning/12 text-warning',
} as const satisfies Record<ConnectionStatus, string>;

const STATUS_ICONS = {
    connected: Check,
    needs_reconnect: TriangleAlert,
} as const satisfies Record<ConnectionStatus, unknown>;

type Props = {
    status: ConnectionStatus;
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
