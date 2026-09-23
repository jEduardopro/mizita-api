import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { PAID_BADGE } from './appointment-payment-badge';
import { isPaid } from './appointment-payment-status';
import type { Appointment } from '../types';

type Props = {
    appointment: Appointment;
};

export function AppointmentPaidBadge({ appointment }: Props) {
    const { t } = useTranslation('admin');

    if (! isPaid(appointment)) {
        return null;
    }

    const { icon: Icon, labelKey, toneClassName } = PAID_BADGE;

    return (
        <Badge variant="secondary" className={toneClassName}>
            <Icon aria-hidden="true" />
            {t(labelKey)}
        </Badge>
    );
}
