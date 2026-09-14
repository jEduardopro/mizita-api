import { useForm, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { LoginCard, type LoginForm } from '@/components/auth/LoginCard';
import { AuthTileLayout } from '@/layouts/AuthTileLayout';

/**
 * Posts to Fortify with Inertia's own `useForm`, not react-query: this is a
 * redirect-following POST whose failures come back as session errors on the next
 * page, which is what `useForm` is built to read.
 */
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
            // The cache is not tenant-keyed, so a new session must start empty.
            // Cleared in `onBefore`: `onSuccess` fires after the next page has
            // mounted and would wipe the query the dashboard just started.
            // Block body, because returning a value from `onBefore` cancels the
            // visit.
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
