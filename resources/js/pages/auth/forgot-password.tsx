import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/FormField';
import { FormStatus } from '@/components/FormStatus';
import { Button } from '@/components/ui/button';
import { AuthLayout } from '@/layouts/AuthLayout';

/**
 * Rendered by `Inertia::render('auth/forgot-password')`.
 *
 * Fortify answers a successful request with a flash `status`, which is the only
 * confirmation the screen gets: whether the address exists is never revealed.
 */
export default function ForgotPassword() {
    const { status } = usePage().props;
    const form = useForm({
        email: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/forgot-password');
    }

    return (
        <AuthLayout
            title="Reset password"
            heading="Reset your password"
            description="Tell us the email on the account and we will send a reset link."
            footer={
                <p>
                    Remembered it?{' '}
                    <Link
                        href="/login"
                        className="rounded-sm font-medium text-foreground underline underline-offset-4 outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        Log in
                    </Link>
                </p>
            }
        >
            <form onSubmit={submit} className="grid gap-5">
                <FormStatus message={status} />

                <FormField
                    id="email"
                    label="Email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    required
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    error={form.errors.email}
                />

                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? 'Sending link…' : 'Send reset link'}
                </Button>
            </form>
        </AuthLayout>
    );
}
