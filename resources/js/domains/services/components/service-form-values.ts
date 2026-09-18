import type { NumberValue } from '@/components/form/NumberField';
import type { ServiceColor } from '@/lib/service-color';
import type { Service, ServicePayload } from '../types';

const DEFAULT_COLOR: ServiceColor = 'blue';

const DEFAULT_DURATION_MINUTES = 60;

export type ServiceField =
    | 'name'
    | 'description'
    | 'durationMinutes'
    | 'bufferMinutes'
    | 'price'
    | 'color'
    | 'active'
    | 'staffIds';

export const serverFields: Record<ServiceField, string> = {
    name: 'name',
    description: 'description',
    durationMinutes: 'duration_minutes',
    bufferMinutes: 'buffer_minutes',
    price: 'price',
    color: 'color',
    active: 'active',
    staffIds: 'staff_ids',
};

export type ServiceFormValues = {
    name: string;
    description: string;
    durationMinutes: NumberValue;
    bufferMinutes: NumberValue;
    price: string;
    color: ServiceColor;
    active: boolean;
    staffIds: string[];
};

export function initialServiceValues(service: Service | null): ServiceFormValues {
    if (service === null) {
        return {
            name: '',
            description: '',
            durationMinutes: DEFAULT_DURATION_MINUTES,
            bufferMinutes: 0,
            price: '',
            color: DEFAULT_COLOR,
            active: true,
            staffIds: [],
        };
    }

    return {
        name: service.name,
        description: service.description ?? '',
        durationMinutes: service.duration_minutes,
        bufferMinutes: service.buffer_minutes,
        price: service.price,
        color: service.color,
        active: service.active,
        staffIds: service.staff.map((member) => member.id),
    };
}

export function servicePayloadFrom(values: ServiceFormValues): ServicePayload {
    const description = values.description.trim();

    return {
        name: values.name.trim(),
        description: description === '' ? null : description,
        duration_minutes: values.durationMinutes === '' ? 0 : values.durationMinutes,
        buffer_minutes: values.bufferMinutes === '' ? 0 : values.bufferMinutes,
        price: values.price.trim(),
        color: values.color,
        active: values.active,
        staff_ids: values.staffIds,
    };
}
