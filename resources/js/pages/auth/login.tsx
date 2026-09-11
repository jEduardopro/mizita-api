import { useForm, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { LoginCard, type LoginForm } from '@/components/auth/LoginCard';
import { AuthTileLayout } from '@/layouts/AuthTileLayout';

/**
 * Rendered by `Inertia::render('auth/login')`.
 *
 * The form posts to Fortify with Inertia's own `useForm`, not react-query: this
 * is a redirect-following POST whose failures come back as session errors on the
 * next page, which is exactly what `useForm` is built to read.
 *
 * The page is layout and composition. The panel the card is in, and every piece
 * of its markup, belong to `LoginCard`; what stays here is the request.
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
            // This has to happen in `onBefore`, which runs before the request:
            // `onSuccess` fires after the next page has already mounted, so it
            // would wipe the query the dashboard just started and leave its
            // observer pending forever. The block body is deliberate too —
            // returning `false` from `onBefore` cancels the visit.
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
