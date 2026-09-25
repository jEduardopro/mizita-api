import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPane, SettingsPaneBody } from '@/components/admin/settings/SettingsPane';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useSignInSecurity } from '../queries';
import type { SignInSecurity } from '../types';
import { PasskeysSection } from './PasskeysSection';
import { PasswordForm } from './PasswordForm';
import { RecoveryCodesPanel } from './RecoveryCodesPanel';
import { SignInOverview } from './SignInOverview';
import { TwoFactorSection } from './TwoFactorSection';
import { TwoFactorSetup } from './TwoFactorSetup';

type SecurityView = 'overview' | 'password' | 'methods' | 'twoFactorSetup' | 'recoveryCodes';

type PasswordOrigin = Extract<SecurityView, 'overview' | 'methods'>;

const METHOD_SKELETON_ROWS = ['twoFactor', 'passkeys'] as const;

function SecurityMethodsSkeleton() {
    return (
        <SettingsPaneBody className="divide-y divide-border">
            {METHOD_SKELETON_ROWS.map((row) => (
                <div key={row} aria-hidden="true" className="grid gap-2.5 py-5 first:pt-1">
                    <Skeleton className="h-5 w-48" />
                    <Skeleton className="h-4 w-full max-w-sm" />
                    <Skeleton className="mt-1 h-11 w-32 rounded-full md:h-9" />
                </div>
            ))}
        </SettingsPaneBody>
    );
}

type SecurityMethodsFailedProps = {
    isRetrying: boolean;
    onRetry: () => void;
};

function SecurityMethodsFailed({ isRetrying, onRetry }: SecurityMethodsFailedProps) {
    const { t } = useTranslation('admin');
    const { t: tCommon } = useTranslation('common');

    return (
        <SettingsPaneBody>
            <div role="alert" className="grid justify-items-start gap-3 rounded-lg bg-muted px-4 py-4">
                <p className="text-sm text-pretty text-foreground/80">{t('security.methodsLoadFailed')}</p>

                <Button
                    type="button"
                    variant="outline"
                    onClick={onRetry}
                    disabled={isRetrying}
                    aria-busy={isRetrying}
                    className="h-11 rounded-full px-5 md:h-9"
                >
                    {isRetrying ? tCommon('actions.retrying') : tCommon('actions.tryAgain')}
                </Button>
            </div>
        </SettingsPaneBody>
    );
}

type SecurityMethodsProps = {
    security: SignInSecurity | undefined;
    loadFailed: boolean;
    isRetrying: boolean;
    onRetry: () => void;
    onCreatePassword: () => void;
    onTwoFactorSetupStarted: () => void;
    onOpenRecoveryCodes: () => void;
};

function SecurityMethods({
    security,
    loadFailed,
    isRetrying,
    onRetry,
    onCreatePassword,
    onTwoFactorSetupStarted,
    onOpenRecoveryCodes,
}: SecurityMethodsProps) {
    if (security === undefined && loadFailed) {
        return <SecurityMethodsFailed isRetrying={isRetrying} onRetry={onRetry} />;
    }

    if (security === undefined) {
        return <SecurityMethodsSkeleton />;
    }

    return (
        <SettingsPaneBody className="divide-y divide-border">
            <TwoFactorSection
                status={security.two_factor}
                hasPassword={security.has_password}
                onCreatePassword={onCreatePassword}
                onSetupStarted={onTwoFactorSetupStarted}
                onOpenRecoveryCodes={onOpenRecoveryCodes}
            />

            <PasskeysSection security={security} onCreatePassword={onCreatePassword} />
        </SettingsPaneBody>
    );
}

type Props = {
    email: string;
    hasPassword: boolean;
    roleLabel: string;
    onPasswordSaved: () => void;
};

export function SignInSecurityPane({ email, hasPassword: profileHasPassword, roleLabel, onPasswordSaved }: Props) {
    const { t } = useTranslation('admin');
    const security = useSignInSecurity();
    const [view, setView] = useState<SecurityView>('overview');
    const [passwordOrigin, setPasswordOrigin] = useState<PasswordOrigin>('overview');
    const hasPassword = security.data?.has_password ?? profileHasPassword;

    const openPassword = (origin: PasswordOrigin) => {
        setPasswordOrigin(origin);
        setView('password');
    };

    const backTo = (target: SecurityView) => ({ label: t('security.back'), onBack: () => setView(target) });

    if (view === 'password') {
        const back = backTo(passwordOrigin);

        return (
            <SettingsPane
                key={view}
                title={hasPassword ? t('security.passwordForm.updateTitle') : t('security.passwordForm.createTitle')}
                back={back}
            >
                <PasswordForm
                    hasPassword={hasPassword}
                    onCancel={back.onBack}
                    onSaved={() => {
                        onPasswordSaved();
                        back.onBack();
                    }}
                />
            </SettingsPane>
        );
    }

    if (view === 'twoFactorSetup') {
        const back = backTo('methods');

        return (
            <SettingsPane key={view} title={t('security.twoFactor.setup.title')} back={back}>
                <TwoFactorSetup onCancelled={back.onBack} onFinished={back.onBack} />
            </SettingsPane>
        );
    }

    if (view === 'recoveryCodes') {
        return (
            <SettingsPane key={view} title={t('security.twoFactor.recoveryCodes.title')} back={backTo('methods')}>
                <SettingsPaneBody>
                    <RecoveryCodesPanel />
                </SettingsPaneBody>
            </SettingsPane>
        );
    }

    if (view === 'methods') {
        return (
            <SettingsPane key={view} title={t('security.methods')} back={backTo('overview')}>
                <SecurityMethods
                    security={security.data}
                    loadFailed={security.isError}
                    isRetrying={security.isFetching}
                    onRetry={() => void security.refetch()}
                    onCreatePassword={() => openPassword('methods')}
                    onTwoFactorSetupStarted={() => setView('twoFactorSetup')}
                    onOpenRecoveryCodes={() => setView('recoveryCodes')}
                />
            </SettingsPane>
        );
    }

    return (
        <SettingsPane key={view} title={t('security.title')}>
            <SignInOverview
                email={email}
                hasPassword={hasPassword}
                roleLabel={roleLabel}
                twoFactorStatus={security.data?.two_factor}
                passkeyCount={security.data?.passkeys.length}
                isLoadingMethods={security.isPending}
                onOpenPassword={() => openPassword('overview')}
                onOpenMethods={() => setView('methods')}
            />
        </SettingsPane>
    );
}
