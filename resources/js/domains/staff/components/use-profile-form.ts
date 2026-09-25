import { useMemo, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import type { AssignableStaffRole, UpdateTeamMemberPayload } from '../types';
import {
    profilePayloadFrom,
    profileServerFields,
    profileValuesFrom,
    sameProfileValues,
    type ProfileField,
    type ProfileFormSource,
    type ProfileFormValues,
} from './profile-form-values';

export type ProfileFormController = {
    values: ProfileFormValues;
    update: <TKey extends ProfileField>(key: TKey, value: ProfileFormValues[TKey]) => void;
    errorFor: (field: ProfileField) => string | undefined;
    isDirty: boolean;
    isSubmitting: boolean;
    submit: (event: FormEvent<HTMLFormElement>) => void;
    discard: () => void;
};

type Params = {
    profile: ProfileFormSource;
    editableLevel: AssignableStaffRole | undefined;
    onSave: (payload: UpdateTeamMemberPayload) => Promise<unknown>;
};

export function useProfileForm({ profile, editableLevel, onSave }: Params): ProfileFormController {
    const { t } = useTranslation('admin');
    const { fieldErrors, capture, clearField, reset } = useServerErrors();

    const baseline = useMemo(() => profileValuesFrom(profile, editableLevel), [profile, editableLevel]);
    const [draft, setDraft] = useState<ProfileFormValues | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const values = draft ?? baseline;

    function update<TKey extends ProfileField>(key: TKey, value: ProfileFormValues[TKey]) {
        setDraft((current) => ({ ...(current ?? baseline), [key]: value }));
        clearField(profileServerFields[key]);
    }

    function discard() {
        reset();
        setDraft(null);
    }

    async function save() {
        reset();
        setIsSubmitting(true);

        try {
            await onSave(profilePayloadFrom(values));
            setDraft(null);
            raiseSuccessToast(t('profile.form.saved'));
        } catch (error) {
            capture(error, t('profile.form.unexpected'));
        } finally {
            setIsSubmitting(false);
        }
    }

    return {
        values,
        update,
        errorFor: (field) => fieldErrors[profileServerFields[field]],
        isDirty: draft !== null && ! sameProfileValues(draft, baseline),
        isSubmitting,
        submit: (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            void save();
        },
        discard,
    };
}
