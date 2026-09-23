import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { appointmentPaymentBadge } from './appointment-payment-badge';
import type { Appointment } from '../types';

type Props = {
    appointment: Appointment;
};

export function TimeGridPaymentTag({ appointment }: Props) {
    const { t } = useTranslation('admin');
    const badge = appointmentPaymentBadge(appointment);

    if (badge === null) {
        return null;
    }

    const { icon: Icon, labelKey, toneClassName } = badge;

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-0.5 rounded-sm px-1 text-[0.85em] font-semibold whitespace-nowrap',
                toneClassName,
            )}
        >
            <Icon aria-hidden="true" className="size-[1.1em]" />
            {t(labelKey)}
        </span>
    );
}
