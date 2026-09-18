import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { ServiceColorTile } from '@/components/shared/ServiceColorTile';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useBookableServices } from '../queries';
import type { AppointmentFormController } from './use-appointment-form';

const FIELD_ID = 'appointment-service';

type Props = {
    form: AppointmentFormController;
};

export function AppointmentServiceField({ form }: Props) {
    const { t } = useTranslation('admin');
    const { data, isPending, isError } = useBookableServices();
    const error = form.errorFor('service');
    const message = fieldMessage({ id: FIELD_ID, error });
    const services = (data ?? []).filter((service) => service.active);

    return (
        <div className="grid gap-2">
            <Label htmlFor={FIELD_ID}>{t('calendar.appointment.form.service.label')}</Label>

            <Select
                value={form.values.service?.id ?? undefined}
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
                    className="h-11 w-full md:h-10"
                >
                    <SelectValue placeholder={t('calendar.appointment.form.service.placeholder')} />
                </SelectTrigger>

                <SelectContent>
                    {services.map((service) => (
                        <SelectItem key={service.id} value={service.id}>
                            <ServiceColorTile color={service.color} imageUrl={null} className="size-6 rounded-md" />
                            {service.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <FieldMessage message={message} />
        </div>
    );
}
