import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { FormStatus } from '@/components/form/FormStatus';
import { Button } from '@/components/ui/button';
import { AuthLayout } from '@/layouts/AuthLayout';

export default function ForgotPassword() {
    const { status } = usePage().props;
    const { t } = useTranslation('auth');
    const form = useForm({
        email: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/forgot-password');
    }

    return (
        <AuthLayout
            title={t('forgotPassword.title')}
            heading={t('forgotPassword.heading')}
            description={t('forgotPassword.description')}
            footer={
                <p>
                    {t('forgotPassword.footer.prompt')}{' '}
                    <Link
                        href="/login"
                        className="rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        {t('forgotPassword.footer.link')}
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

                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? t('forgotPassword.submitting') : t('forgotPassword.submit')}
                </Button>
            </form>
        </AuthLayout>
    );
}
