import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { TwoFactorStatus } from '../types';

export const SECURITY_BADGE_CLASSES = 'rounded-md font-normal';

export const SECURITY_ON_TONE_CLASSES = 'bg-success/12 text-success';

const STATUS_LABEL_KEYS = {
    disabled: 'security.disabled',
    pending: 'security.pending',
    enabled: 'security.enabled',
} as const satisfies Record<TwoFactorStatus, string>;

const STATUS_TONE_CLASSES = {
    disabled: undefined,
    pending: 'bg-warning/12 text-warning',
    enabled: SECURITY_ON_TONE_CLASSES,
} as const satisfies Record<TwoFactorStatus, string | undefined>;

type Props = {
    status: TwoFactorStatus;
};

export function TwoFactorStatusBadge({ status }: Props) {
    const { t } = useTranslation('admin');

    return (
        <Badge variant="secondary" className={cn(SECURITY_BADGE_CLASSES, STATUS_TONE_CLASSES[status])}>
            {t(STATUS_LABEL_KEYS[status])}
        </Badge>
    );
}
