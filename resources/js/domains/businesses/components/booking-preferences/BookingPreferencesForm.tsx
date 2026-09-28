import { useTranslation } from 'react-i18next';
import { PlanUpgradeNotice } from '@/components/admin/PlanUpgradeNotice';
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
    const { t } = useTranslation('admin');

    return (
        <form
            id={BOOKING_PREFERENCES_FORM_ID}
            onSubmit={form.submit}
            className="grid gap-5 pb-16 sm:gap-6 sm:pb-24"
        >
            {form.areBookingRulesLocked ? (
                <PlanUpgradeNotice description={t('plan.bookingRules.locked')} />
            ) : null}

            <BookingPolicySection form={form} />

            <ContactFieldsSection form={form} />
        </form>
    );
}
