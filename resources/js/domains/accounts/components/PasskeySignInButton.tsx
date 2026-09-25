import { cn } from 'cn';
import { KeyRound, LoaderCircle } from 'lucide-react';
import { useId, type ComponentProps } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { usePasskeySignIn } from './use-passkey-sign-in';

type Props = {
    remember: boolean;
};

export function PasskeySignInButton({ remember }: Props) {
    const { t } = useTranslation('auth');
    const passkey = usePasskeySignIn({ remember });
    const noticeId = useId();

    if (! passkey.isSupported) {
        return (
            <div className="grid gap-2">
                <PasskeyButton
                    label={t('login.methods.passkey')}
                    disabled
                    aria-describedby={noticeId}
                />

                <p id={noticeId} className="text-center text-xs text-muted-foreground">
                    {t('login.methods.passkeyUnsupported')}
                </p>
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <PasskeyButton
                label={passkey.isSigningIn ? t('login.methods.passkeyPending') : t('login.methods.passkey')}
                isBusy={passkey.isSigningIn}
                disabled={passkey.isSigningIn}
                onClick={passkey.signIn}
            />

            {passkey.hasFailed ? (
                <p role="alert" className="text-center text-xs text-destructive">
                    {t('login.methods.passkeyFailed')}
                </p>
            ) : null}
        </div>
    );
}

type PasskeyButtonProps = Omit<ComponentProps<typeof Button>, 'children'> & {
    label: string;
    isBusy?: boolean;
};

function PasskeyButton({ label, isBusy = false, className, ...props }: PasskeyButtonProps) {
    const Icon = isBusy ? LoaderCircle : KeyRound;

    return (
        <Button
            type="button"
            variant="outline"
            size="lg"
            aria-busy={isBusy}
            {...props}
            className={cn('h-12 w-full gap-3 rounded-xl text-sm', className)}
        >
            <Icon aria-hidden="true" className={cn('size-5', isBusy && 'motion-safe:animate-spin')} />
            {label}
        </Button>
    );
}
