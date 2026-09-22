import type { ComponentProps } from 'react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { PaymentStatus } from '../types';

type BadgeVariant = ComponentProps<typeof Badge>['variant'];

const STATUS_LABEL_KEYS = {
    pending: 'payments.status.pending',
    partially_paid: 'payments.status.partiallyPaid',
    paid: 'payments.status.paid',
} as const satisfies Record<PaymentStatus, string>;

const STATUS_VARIANTS = {
    pending: 'outline',
    partially_paid: 'secondary',
    paid: 'secondary',
} as const satisfies Record<PaymentStatus, BadgeVariant>;

const STATUS_CLASSES = {
    pending: undefined,
    partially_paid: undefined,
    paid: 'bg-success/12 text-success',
} as const satisfies Record<PaymentStatus, string | undefined>;

type Props = {
    status: PaymentStatus;
};

export function PaymentStatusBadge({ status }: Props) {
    const { t } = useTranslation('admin');

    return (
        <Badge variant={STATUS_VARIANTS[status]} className={STATUS_CLASSES[status]}>
            {t(STATUS_LABEL_KEYS[status])}
        </Badge>
    );
}
