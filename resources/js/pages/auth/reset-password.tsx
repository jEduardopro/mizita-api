import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/FormField';
import { Button } from '@/components/ui/button';
import { AuthLayout } from '@/layouts/AuthLayout';

/**
 * Rendered by `Inertia::render('auth/reset-password', ['token' => …, 'email' => …])`.
 *
 * Both props are identity carried by the reset link, not data: the token is the
 * credential Fortify checks and the email is the account it belongs to.
 */
type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
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
            title="Choose a new password"
            heading="Choose a new password"
            description="This link works once. Pick the password you will use from now on."
        >
            <form onSubmit={submit} className="grid gap-5">
                <FormField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="username"
                    readOnly
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                />

                <FormField
                    id="password"
                    label="New password"
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
                    label="Confirm new password"
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
                    {form.processing ? 'Saving password…' : 'Save password'}
                </Button>
            </form>
        </AuthLayout>
    );
}
