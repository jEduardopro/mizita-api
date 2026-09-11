import { Link, useForm } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { SignupCard, type SignupForm } from '@/components/auth/SignupCard';
import { Button } from '@/components/ui/button';
import { AuthSplitLayout } from '@/layouts/AuthSplitLayout';

/**
 * Rendered by `Inertia::render('auth/register')`. Posts to Fortify's
 * `POST /register`; `CreateNewUser` is the only definition of what is valid, so
 * there is no client-side schema here.
 *
 * Registration is the one flow without a confirmation field — the card offers a
 * reveal toggle instead, so someone can read what they typed rather than type it
 * twice. Reset and update keep the confirmation, where a typo locks a person out
 * of an account they already own.
 *
 * The page is layout and composition. The mode the card is in, and every piece of
 * its markup, belong to `SignupCard`; what stays here is the request.
 */
export default function Register() {
    const { t } = useTranslation('auth');
    const queryClient = useQueryClient();
    const form = useForm<SignupForm>({
        name: '',
        email: '',
        password: '',
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
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <AuthSplitLayout
            title={t('register.title')}
            heading={t('register.promise')}
            description={t('register.description')}
            action={
                <Button asChild variant="outline" size="lg" className="rounded-full px-5">
                    <Link href="/login">{t('register.footer.link')}</Link>
                </Button>
            }
        >
            <SignupCard form={form} onSubmit={submit} />
        </AuthSplitLayout>
    );
}
