import { LogOut } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useLogOut } from '@/hooks/use-log-out';
import { OnboardingLayout } from '@/layouts/OnboardingLayout';

function PausedMark() {
    return (
        <div
            aria-hidden="true"
            className="flex size-14 items-center justify-center gap-1.5 rounded-2xl border border-border bg-muted"
        >
            <span className="h-5 w-1.5 rounded-full bg-foreground/60" />
            <span className="h-5 w-1.5 rounded-full bg-foreground/60" />
        </div>
    );
}

export default function TeamAccessPaused() {
    const { t } = useTranslation('admin');
    const logOut = useLogOut();
    const headingId = useId();

    return (
        <OnboardingLayout title={t('plan.teamAccessPaused.title')}>
            <section aria-labelledby={headingId} className="grid justify-items-start gap-7">
                <PausedMark />

                <div>
                    <h1
                        id={headingId}
                        className="font-heading text-[clamp(1.5rem,6vw,1.875rem)] font-medium tracking-[-0.03em] text-balance"
                    >
                        {t('plan.teamAccessPaused.title')}
                    </h1>

                    <p className="mt-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                        {t('plan.teamAccessPaused.body')}
                    </p>
                </div>

                <Button type="button" variant="outline" onClick={logOut} className="h-11 w-full px-4 sm:w-auto">
                    <LogOut aria-hidden="true" />
                    {t('plan.teamAccessPaused.signOut')}
                </Button>
            </section>
        </OnboardingLayout>
    );
}
