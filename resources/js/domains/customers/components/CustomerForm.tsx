import { CustomerAddressFields } from './CustomerAddressFields';
import { CustomerDetailsFields } from './CustomerDetailsFields';
import type { CustomerFormController } from './use-customer-form';

export const CUSTOMER_FORM_ID = 'customer-form';

type Props = {
    form: CustomerFormController;
    focusNameField?: boolean;
};

export function CustomerForm({ form, focusNameField = false }: Props) {
    return (
        <form
            id={CUSTOMER_FORM_ID}
            onSubmit={form.submit}
            className="grid gap-6 lg:grid-cols-2 lg:items-start"
        >
            <CustomerDetailsFields form={form} focusNameField={focusNameField} />

            <CustomerAddressFields form={form} />
        </form>
    );
}
