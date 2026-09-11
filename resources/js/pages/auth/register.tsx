import { Link, useForm } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/FormField';
import { Button } from '@/components/ui/button';
import { AuthLayout } from '@/layouts/AuthLayout';

/**
 * Rendered by `Inertia::render('auth/register')`. Posts to Fortify's
 * `POST /register`; the rules behind it are the only definition of what is
 * valid, so there is no client-side schema here.
 */
export default function Register() {
    const { t } = useTranslation('auth');
    const queryClient = useQueryClient();
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/register', {
            // Cleared before the request, not after: `onSuccess` runs once the
            // dashboard has mounted and would drop the query it just started.
            // Returning `false` from `onBefore` would cancel the visit, hence
            // the block body.
            onBefore: () => {
                queryClient.clear();
            },
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout
            title={t('register.title')}
            heading={t('register.heading')}
            description={t('register.description')}
            footer={
                <p>
                    {t('register.footer.prompt')}{' '}
                    <Link
                        href="/login"
                        className="rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('register.footer.link')}
                    </Link>
                </p>
            }
        >
            <form onSubmit={submit} className="grid gap-5">
                <FormField
                    id="name"
                    label={t('fields.name')}
                    autoComplete="name"
                    autoFocus
                    required
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    error={form.errors.name}
                />

                <FormField
                    id="email"
                    label={t('fields.email')}
                    type="email"
                    autoComplete="username"
                    required
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                />

                <FormField
                    id="password"
                    label={t('fields.password')}
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                />

                <FormField
                    id="password_confirmation"
                    label={t('fields.passwordConfirmation')}
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password_confirmation}
                    onChange={(event) =>
                        form.setData('password_confirmation', event.target.value)
                    }
                    error={form.errors.password_confirmation}
                />

                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? t('register.submitting') : t('register.submit')}
                </Button>
            </form>
        </AuthLayout>
    );
}
