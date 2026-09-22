import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
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

    return (
        <Badge variant="secondary" className="bg-success/12 text-success">
            <Check aria-hidden="true" />
            {t('calendar.appointment.paid.badge')}
        </Badge>
    );
}
