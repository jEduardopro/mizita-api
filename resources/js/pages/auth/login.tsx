import { useForm, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { LoginCard, type LoginForm } from '@/components/auth/LoginCard';
import { AuthTileLayout } from '@/layouts/AuthTileLayout';

export default function Login() {
    const { status } = usePage().props;
    const { t } = useTranslation('auth');
    const queryClient = useQueryClient();
    const form = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/login', {
            onBefore: () => {
                queryClient.clear();
            },
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <AuthTileLayout title={t('login.title')}>
            <LoginCard form={form} status={status} onSubmit={submit} />
        </AuthTileLayout>
    );
}
