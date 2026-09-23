import { AppearanceSection } from './AppearanceSection';
import { BrandDetailsSection } from './BrandDetailsSection';
import { BusinessHoursSection } from './BusinessHoursSection';
import { ContactSection } from './ContactSection';
import { LinksSection } from './LinksSection';
import { LocationSection } from './LocationSection';
import {
    BUSINESS_SETTINGS_FORM_ID,
    type BusinessSettingsFormController,
} from './use-business-settings-form';

type Props = {
    form: BusinessSettingsFormController;
};

export function BusinessSettingsForm({ form }: Props) {
    return (
        <form
            id={BUSINESS_SETTINGS_FORM_ID}
            onSubmit={form.submit}
            className="grid gap-5 pb-16 sm:gap-6 sm:pb-24"
        >
            <BrandDetailsSection form={form} />

            <AppearanceSection form={form} />

            <ContactSection form={form} />

            <LocationSection form={form} />

            <BusinessHoursSection form={form} />

            <LinksSection form={form} />
        </form>
    );
}
