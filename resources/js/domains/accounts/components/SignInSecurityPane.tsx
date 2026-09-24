import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPane, SettingsPaneBody } from '@/components/admin/settings/SettingsPane';
import { PasswordForm } from './PasswordForm';
import { SignInOverview } from './SignInOverview';
import { UnavailableFeature } from './UnavailableFeature';

type SecurityView = 'overview' | 'password' | 'methods';

type Props = {
    email: string;
    hasPassword: boolean;
    roleLabel: string;
    onPasswordSaved: () => void;
};

export function SignInSecurityPane({ email, hasPassword, roleLabel, onPasswordSaved }: Props) {
    const { t } = useTranslation('admin');
    const [view, setView] = useState<SecurityView>('overview');

    const back = { label: t('security.back'), onBack: () => setView('overview') };

    if (view === 'password') {
        return (
            <SettingsPane
                title={hasPassword ? t('security.passwordForm.updateTitle') : t('security.passwordForm.createTitle')}
                back={back}
            >
                <PasswordForm
                    hasPassword={hasPassword}
                    onCancel={back.onBack}
                    onSaved={() => {
                        onPasswordSaved();
                        setView('overview');
                    }}
                />
            </SettingsPane>
        );
    }

    if (view === 'methods') {
        return (
            <SettingsPane title={t('security.methods')} back={back}>
                <SettingsPaneBody className="divide-y divide-border">
                    <UnavailableFeature
                        title={t('security.twoFactor.title')}
                        body={t('security.twoFactor.body')}
                        actionLabel={t('security.twoFactor.action')}
                    />

                    <UnavailableFeature
                        title={t('security.passkey.title')}
                        body={t('security.passkey.body')}
                        actionLabel={t('security.passkey.action')}
                    />
                </SettingsPaneBody>
            </SettingsPane>
        );
    }

    return (
        <SettingsPane title={t('security.title')}>
            <SignInOverview
                email={email}
                hasPassword={hasPassword}
                roleLabel={roleLabel}
                onOpenPassword={() => setView('password')}
                onOpenMethods={() => setView('methods')}
            />
        </SettingsPane>
    );
}
