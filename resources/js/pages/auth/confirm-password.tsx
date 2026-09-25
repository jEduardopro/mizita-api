import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { ConfirmPasswordCard, type ConfirmPasswordForm } from '@/components/auth/ConfirmPasswordCard';
import { AuthLayout } from '@/layouts/AuthLayout';

const CONFIRM_PASSWORD_URL = '/user/confirm-password';

export default function ConfirmPassword() {
    const { t } = useTranslation('auth');
    const form = useForm<ConfirmPasswordForm>({
        password: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(CONFIRM_PASSWORD_URL, {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <AuthLayout
            title={t('confirmPassword.title')}
            heading={t('confirmPassword.heading')}
            description={t('confirmPassword.description')}
        >
            <ConfirmPasswordCard form={form} onSubmit={submit} />
        </AuthLayout>
    );
}
