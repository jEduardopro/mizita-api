import type { InertiaFormProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { SubmitButton } from '@/components/form/SubmitButton';

export type ConfirmPasswordForm = {
    password: string;
};

type Props = {
    form: InertiaFormProps<ConfirmPasswordForm>;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export function ConfirmPasswordCard({ form, onSubmit }: Props) {
    const { t } = useTranslation('auth');

    return (
        <form onSubmit={onSubmit} className="grid gap-5">
            <FormField
                id="password"
                label={t('fields.password')}
                type="password"
                autoComplete="current-password"
                autoFocus
                required
                value={form.data.password}
                onChange={(event) => form.setData('password', event.target.value)}
                error={form.errors.password}
            />

            <SubmitButton
                size="lg"
                label={t('confirmPassword.submit')}
                submittingLabel={t('confirmPassword.submitting')}
                isSubmitting={form.processing}
            />
        </form>
    );
}
