import { cn } from 'cn';
import { CustomerAddressFields } from './CustomerAddressFields';
import { CustomerDetailsFields } from './CustomerDetailsFields';
import type { CustomerFormController } from './use-customer-form';

export const CUSTOMER_FORM_ID = 'customer-form';

type CustomerFormLayout = 'columns' | 'stacked';

const LAYOUT_CLASSES: Record<CustomerFormLayout, string> = {
    columns: 'lg:grid-cols-2 lg:items-start',
    stacked: 'lg:grid-cols-1',
};

type Props = {
    form: CustomerFormController;
    id?: string;
    focusNameField?: boolean;
    layout?: CustomerFormLayout;
};

export function CustomerForm({
    form,
    id = CUSTOMER_FORM_ID,
    focusNameField = false,
    layout = 'columns',
}: Props) {
    return (
        <form
            id={id}
            onSubmit={form.submit}
            className={cn('grid gap-6', LAYOUT_CLASSES[layout])}
        >
            <CustomerDetailsFields form={form} focusNameField={focusNameField} />

            <CustomerAddressFields form={form} />
        </form>
    );
}
