import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { fieldMessage } from '@/components/form/FieldMessage';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useBookableServices } from '../queries';
import { APPOINTMENT_CONTROL_HEIGHT, AppointmentFormRow } from './AppointmentFormRow';
import { ServiceColorDot } from './ServiceColorDot';
import type { AppointmentFormController } from './use-appointment-form';

const FIELD_ID = 'appointment-service';

type Props = {
    form: AppointmentFormController;
};

export function AppointmentServiceField({ form }: Props) {
    const { t } = useTranslation('admin');
    const { data, isPending, isError } = useBookableServices();
    const selected = form.values.service;
    const error = form.errorFor('service');
    const message = fieldMessage({ id: FIELD_ID, error });
    const services = (data ?? []).filter((service) => service.active);

    return (
        <AppointmentFormRow
            icon={<ServiceColorDot color={selected?.color ?? null} />}
            label={t('calendar.appointment.form.service.label')}
            htmlFor={FIELD_ID}
            message={message}
        >
            <Select
                value={selected?.id ?? undefined}
                onValueChange={(nextId) => {
                    const service = services.find((candidate) => candidate.id === nextId) ?? null;

                    form.setService(
                        service === null
                            ? null
                            : {
                                  id: service.id,
                                  name: service.name,
                                  color: service.color,
                                  duration_minutes: service.duration_minutes,
                              },
                    );
                }}
                disabled={isPending || isError}
            >
                <SelectTrigger
                    id={FIELD_ID}
                    aria-invalid={!! error}
                    aria-describedby={message?.id}
                    className={cn('w-full', APPOINTMENT_CONTROL_HEIGHT)}
                >
                    <SelectValue placeholder={t('calendar.appointment.form.service.placeholder')}>
                        {selected?.name}
                    </SelectValue>
                </SelectTrigger>

                <SelectContent>
                    {services.map((service) => (
                        <SelectItem key={service.id} value={service.id}>
                            <ServiceColorDot color={service.color} />
                            {service.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </AppointmentFormRow>
    );
}
