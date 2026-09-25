import { useTranslation } from 'react-i18next';
import { SettingsPaneBody, SettingsPaneFooter } from '@/components/admin/settings/SettingsPane';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { FormField } from '@/components/form/FormField';
import { TEXTAREA_DENSITY_CLASSES, useFormDensity } from '@/components/form/form-density';
import { PhoneField } from '@/components/form/PhoneField';
import { SubmitButton } from '@/components/form/SubmitButton';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { SUPPORTED_PHONE_COUNTRIES, type PhoneCountryCode } from '@/lib/phone';
import {
    PROFILE_ABOUT_MAX_LENGTH,
    PROFILE_JOB_TITLE_MAX_LENGTH,
    PROFILE_NAME_MAX_LENGTH,
    PROFILE_PHONE_MAX_LENGTH,
    type AssignableStaffRole,
    type UpdateTeamMemberPayload,
} from '../types';
import { LockedEmailField } from './LockedEmailField';
import type { ProfileFormSource } from './profile-form-values';
import { PROFILE_SECTION_DIVIDED, PROFILE_SECTION_HEADING } from './profile-section';
import { ProfileLevelSection } from './ProfileLevelSection';
import { useProfileForm } from './use-profile-form';

const COUNTRY_NAME_KEYS = {
    MX: 'profile.form.phone.countries.MX',
    US: 'profile.form.phone.countries.US',
} as const satisfies Record<PhoneCountryCode, string>;

type Props = {
    profile: ProfileFormSource;
    email: string;
    editableLevel?: AssignableStaffRole;
    onSave: (payload: UpdateTeamMemberPayload) => Promise<unknown>;
    onCancel: () => void;
};

export function ProfileDetailsForm({ profile, email, editableLevel, onSave, onCancel }: Props) {
    const { t } = useTranslation('admin');
    const density = useFormDensity();
    const form = useProfileForm({ profile, editableLevel, onSave });
    const level = form.values.level;

    const aboutMessage = fieldMessage({ id: 'profile-about', error: form.errorFor('about') });

    function selectPhoneCountry(code: string) {
        const country = SUPPORTED_PHONE_COUNTRIES.find((supported) => supported.code === code);

        if (country !== undefined) {
            form.update('phoneCountry', country.code);
        }
    }

    function cancel() {
        form.discard();
        onCancel();
    }

    return (
        <form onSubmit={form.submit} className="flex min-h-0 flex-1 flex-col">
            <SettingsPaneBody className="grid content-start gap-6 pt-2 md:pt-5">
                <section aria-labelledby="profile-details-heading" className="grid max-w-xl gap-5">
                    <h3 id="profile-details-heading" className={PROFILE_SECTION_HEADING}>
                        {t('profile.form.details')}
                    </h3>

                    <FormField
                        id="profile-name"
                        label={t('profile.form.name.label')}
                        autoComplete="name"
                        required
                        maxLength={PROFILE_NAME_MAX_LENGTH}
                        value={form.values.name}
                        onChange={(event) => form.update('name', event.target.value)}
                        error={form.errorFor('name')}
                    />

                    <PhoneField
                        id="profile-phone"
                        label={t('profile.form.phone.label')}
                        countryLabel={t('profile.form.phone.country')}
                        numberLabel={t('profile.form.phone.number')}
                        placeholder={t('profile.form.phone.placeholder')}
                        countries={SUPPORTED_PHONE_COUNTRIES.map((country) => ({
                            code: country.code,
                            name: t(COUNTRY_NAME_KEYS[country.code]),
                            dialCode: country.dialCode,
                        }))}
                        country={form.values.phoneCountry}
                        onCountryChange={selectPhoneCountry}
                        number={form.values.phoneNumber}
                        onNumberChange={(value) =>
                            form.update('phoneNumber', value.slice(0, PROFILE_PHONE_MAX_LENGTH))
                        }
                        error={form.errorFor('phoneNumber') ?? form.errorFor('phoneCountry')}
                    />

                    <LockedEmailField
                        id="profile-email"
                        label={t('profile.form.email.label')}
                        lockedReason={t('profile.form.email.locked')}
                        value={email}
                    />

                    <FormField
                        id="profile-job-title"
                        label={t('profile.form.jobTitle.label')}
                        placeholder={t('profile.form.jobTitle.placeholder')}
                        autoComplete="organization-title"
                        maxLength={PROFILE_JOB_TITLE_MAX_LENGTH}
                        value={form.values.jobTitle}
                        onChange={(event) => form.update('jobTitle', event.target.value)}
                        error={form.errorFor('jobTitle')}
                    />
                </section>

                <section className={PROFILE_SECTION_DIVIDED}>
                    <Label htmlFor="profile-about" className={PROFILE_SECTION_HEADING}>
                        {t('profile.form.about.label')}
                    </Label>

                    <Textarea
                        id="profile-about"
                        placeholder={t('profile.form.about.placeholder')}
                        maxLength={PROFILE_ABOUT_MAX_LENGTH}
                        aria-invalid={!! form.errorFor('about')}
                        aria-describedby={aboutMessage?.id}
                        value={form.values.about}
                        onChange={(event) => form.update('about', event.target.value)}
                        className={TEXTAREA_DENSITY_CLASSES[density]}
                    />

                    <FieldMessage message={aboutMessage} />
                </section>

                {level === null ? null : (
                    <ProfileLevelSection
                        value={level}
                        onChange={(next) => form.update('level', next)}
                        error={form.errorFor('level')}
                    />
                )}
            </SettingsPaneBody>

            <SettingsPaneFooter>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={cancel}
                    disabled={form.isSubmitting}
                    className="h-11 px-4 md:h-9"
                >
                    {t('profile.form.cancel')}
                </Button>

                <SubmitButton
                    variant="brand"
                    className="h-11 px-5 md:h-9"
                    label={t('profile.form.save')}
                    submittingLabel={t('profile.form.saving')}
                    isSubmitting={form.isSubmitting}
                    disabled={! form.isDirty}
                />
            </SettingsPaneFooter>
        </form>
    );
}
