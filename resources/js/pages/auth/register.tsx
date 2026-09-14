import { Link, useForm } from '@inertiajs/react';
import { useQueryClient } from '@tanstack/react-query';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { SignupCard, type SignupForm } from '@/components/auth/SignupCard';
import { Button } from '@/components/ui/button';
import { AuthSplitLayout } from '@/layouts/AuthSplitLayout';

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
