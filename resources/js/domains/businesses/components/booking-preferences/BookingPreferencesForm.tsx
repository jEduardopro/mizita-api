import { BookingPolicySection } from './BookingPolicySection';
import { ContactFieldsSection } from './ContactFieldsSection';
import {
    BOOKING_PREFERENCES_FORM_ID,
    type BookingPreferencesFormController,
} from './use-booking-preferences-form';

type Props = {
    form: BookingPreferencesFormController;
};

export function BookingPreferencesForm({ form }: Props) {
    return (
        <form
            id={BOOKING_PREFERENCES_FORM_ID}
            onSubmit={form.submit}
            className="grid gap-5 pb-16 sm:gap-6 sm:pb-24"
        >
            <BookingPolicySection form={form} />

            <ContactFieldsSection form={form} />
        </form>
    );
}
