import { cn } from 'cn';
import { Info, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { TEAM_INVITATION_MAXIMUM_MEMBERS } from '../types';
import { INVITEE_GRID_COLUMNS } from './invitee-grid';
import { InviteeRow } from './InviteeRow';
import type { InviteTeamFormController } from './use-invite-team-form';

type Props = {
    id: string;
    form: InviteTeamFormController;
};

export function InviteTeamMembersForm({ id, form }: Props) {
    const { t } = useTranslation('admin');

    return (
        <form id={id} onSubmit={form.submit} noValidate className="grid gap-4">
            <div
                aria-hidden="true"
                className={cn('hidden gap-x-3 text-sm leading-none font-medium md:grid', INVITEE_GRID_COLUMNS)}
            >
                <span>{t('team.invite.name.label')}</span>
                <span>{t('team.invite.email.label')}</span>
                <span>{t('team.invite.level.label')}</span>
            </div>

            <div className="grid gap-3">
                {form.invitees.map((invitee, index) => (
                    <InviteeRow
                        key={invitee.key}
                        number={index + 1}
                        invitee={invitee}
                        errors={form.errorsFor(invitee, index)}
                        onChange={(field, value) => form.update(invitee.key, field, value)}
                        onBlur={(field) => form.touch(invitee.key, field)}
                        onRemove={index === 0 ? undefined : () => form.removeInvitee(invitee.key)}
                    />
                ))}
            </div>

            {form.canAddInvitee ? (
                <Button
                    type="button"
                    variant="link"
                    onClick={form.addInvitee}
                    className="h-11 justify-self-start px-0 md:h-9"
                >
                    <Plus aria-hidden="true" />
                    {t('team.invite.addMore')}
                </Button>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {t('team.invite.limit', { maximum: TEAM_INVITATION_MAXIMUM_MEMBERS })}
                </p>
            )}

            <p className="flex items-start gap-2.5 rounded-lg bg-muted px-3 py-2.5 text-sm text-pretty text-muted-foreground">
                <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                {t('team.invite.note')}
            </p>
        </form>
    );
}
