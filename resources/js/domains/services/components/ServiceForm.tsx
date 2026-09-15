import { ServiceDetailsFields } from './ServiceDetailsFields';
import { ServiceTeamFields, type StaffChoices } from './ServiceTeamFields';
import type { ServiceFormController } from './use-service-form';

export const SERVICE_FORM_ID = 'service-form';

type Props = {
    form: ServiceFormController;
    staff: StaffChoices;
};

export function ServiceForm({ form, staff }: Props) {
    return (
        <form
            id={SERVICE_FORM_ID}
            onSubmit={form.submit}
            className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
        >
            <ServiceDetailsFields form={form} />

            <ServiceTeamFields form={form} staff={staff} />
        </form>
    );
}
