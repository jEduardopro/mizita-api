import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { FormField } from '@/components/form/FormField';
import { Button } from '@/components/ui/button';
import { AuthLayout } from '@/layouts/AuthLayout';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    const { t } = useTranslation('auth');
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout
            title={t('resetPassword.title')}
            heading={t('resetPassword.heading')}
            description={t('resetPassword.description')}
        >
            <form onSubmit={submit} className="grid gap-5">
                <FormField
                    id="email"
                    label={t('fields.email')}
                    type="email"
                    autoComplete="username"
                    readOnly
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                />

                <FormField
                    id="password"
                    label={t('fields.newPassword')}
                    type="password"
                    autoComplete="new-password"
                    autoFocus
                    required
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    error={form.errors.password}
                />

                <FormField
                    id="password_confirmation"
                    label={t('fields.newPasswordConfirmation')}
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password_confirmation}
                    onChange={(event) =>
                        form.setData('password_confirmation', event.target.value)
                    }
                    error={form.errors.password_confirmation}
                />

                {form.errors.token ? (
                    <p className="text-sm text-destructive">{form.errors.token}</p>
                ) : null}

                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? t('resetPassword.submitting') : t('resetPassword.submit')}
                </Button>
            </form>
        </AuthLayout>
    );
}
