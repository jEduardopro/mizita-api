import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SettingsPane, SettingsPaneBody } from '@/components/admin/settings/SettingsPane';
import { Button } from '@/components/ui/button';
import { DeleteAccountDialog } from './DeleteAccountDialog';

export function ManageAccountPane() {
    const { t } = useTranslation('admin');
    const [isDeleteDialogOpen, setDeleteDialogOpen] = useState(false);

    return (
        <SettingsPane title={t('security.account.title')}>
            <SettingsPaneBody>
                <section className="grid justify-items-start gap-2 py-5 first:pt-1">
                    <h4 className="text-sm font-semibold">{t('security.account.delete.title')}</h4>

                    <p className="text-sm text-muted-foreground">{t('security.account.delete.body')}</p>

                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() => setDeleteDialogOpen(true)}
                        className="mt-1 h-11 rounded-full px-5 md:h-9"
                    >
                        {t('security.account.delete.action')}
                    </Button>
                </section>
            </SettingsPaneBody>

            <DeleteAccountDialog open={isDeleteDialogOpen} onOpenChange={setDeleteDialogOpen} />
        </SettingsPane>
    );
}
