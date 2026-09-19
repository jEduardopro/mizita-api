import type { CreatedCustomer } from '@/hooks/use-customer-creation';
import { useCustomerForm, type CustomerFormController } from './use-customer-form';

export type CreateCustomerSurfaceProps = {
    suggestedName: string;
    onCreated: (customer: CreatedCustomer) => void;
    onCancel: () => void;
};

type Params = Omit<CreateCustomerSurfaceProps, 'onCancel'>;

export function useCreateCustomerSurface({
    suggestedName,
    onCreated,
}: Params): CustomerFormController {
    return useCustomerForm({
        mode: 'create',
        customer: null,
        initialName: suggestedName,
        onSaved: (customer) => onCreated({ id: customer.id, name: customer.name }),
    });
}
