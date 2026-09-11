import { Link, useForm, usePage } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/FormField';
import { FormStatus } from '@/components/FormStatus';
import { Button } from '@/components/ui/button';
import { AuthLayout } from '@/layouts/AuthLayout';

/**
 * Rendered by `Inertia::render('auth/login')`.
 *
 * The form posts to Fortify with Inertia's own `useForm`, not react-query: this
 * is a redirect-following POST whose failures come back as session errors on the
 * next page, which is exactly what `useForm` is built to read.
 */
export default function Login() {
    const { status } = usePage().props;
    const { t } = useTranslation('auth');
    const queryClient = useQueryClient();
    const form = useForm({
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
        <AuthLayout
            title={t('login.title')}
            heading={t('login.heading')}
            description={t('login.description')}
            footer={
                <p>
                    {t('login.footer.prompt')}{' '}
                    <Link
                        href="/register"
                        className="rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('login.footer.link')}
                    </Link>
                </p>
            }
        >
            <form onSubmit={submit} className="grid gap-5">
                <FormStatus message={status} />

                <FormField
                    id="email"
                    label={t('fields.email')}
                    type="email"
                    autoComplete="username"
                    autoFocus
                    required
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                />

                <FormField
                    id="password"
                    label={t('fields.password')}
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                />

                <div className="flex items-center justify-between gap-4">
                    <label
                        htmlFor="remember"
                        className="flex items-center gap-2 text-sm text-muted-foreground select-none"
                    >
                        <input
                            id="remember"
                            type="checkbox"
                            className="size-4 rounded-sm border-input accent-foreground outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                            checked={form.data.remember}
                            onChange={(event) => form.setData('remember', event.target.checked)}
                        />
                        {t('login.remember')}
                    </label>

                    <Link
                        href="/forgot-password"
                        className="rounded-sm text-sm text-muted-foreground underline underline-offset-4 outline-none hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('login.forgotPassword')}
                    </Link>
                </div>

                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? t('login.submitting') : t('login.submit')}
                </Button>
            </form>
        </AuthLayout>
    );
}
