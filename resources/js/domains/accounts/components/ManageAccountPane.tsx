import { useTranslation } from 'react-i18next';
import { SettingsPane, SettingsPaneBody } from '@/components/admin/settings/SettingsPane';
import { UnavailableFeature } from './UnavailableFeature';

export function ManageAccountPane() {
    const { t } = useTranslation('admin');

    return (
        <SettingsPane title={t('security.account.title')}>
            <SettingsPaneBody>
                <UnavailableFeature
                    title={t('security.account.delete.title')}
                    body={t('security.account.delete.body')}
                    actionLabel={t('security.account.delete.action')}
                />
            </SettingsPaneBody>
        </SettingsPane>
    );
}
