import { Link } from '@inertiajs/react';
import { KeyRound, MailPlus, MoreHorizontal, Pencil, UserMinus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuthorization } from '@/hooks/use-authorization';
import type { TeamMember } from '../types';
import { RemoveTeamMemberDialog } from './RemoveTeamMemberDialog';
import { teamMemberEditUrl } from './team-urls';
import { useCopyTemporaryPassword } from './use-copy-temporary-password';
import { useResendInvitation } from './use-resend-invitation';
import { canEditStaffProfile, useStaffProfileAccess } from './use-staff-profile-access';

const MENU_ITEM_SIZE = 'min-h-11 md:min-h-8';

type Props = {
    member: TeamMember;
};

export function TeamMemberRowActions({ member }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const [confirmingRemoval, setConfirmingRemoval] = useState(false);
    const invitation = useResendInvitation(member.id);
    const access = useStaffProfileAccess(member.id);
    const temporaryPassword = useCopyTemporaryPassword(member.id);

    const canEdit = canEditStaffProfile(access);
    const canResend = can('create_staff_member') && member.invitation_pending;
    const canCopyPassword = can('reveal_temporary_password') && member.temporary_password_available;
    const canRemove = can('delete_staff_member') && member.level !== 'owner';

    if (! canEdit && ! canResend && ! canCopyPassword && ! canRemove) {
        return null;
    }

    return (
        <div className="flex shrink-0 items-center gap-1">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={t('team.actions.more', { name: member.name })}
                        className="size-11 md:size-9"
                    >
                        <MoreHorizontal aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" className="w-60">
                    {canEdit ? (
                        <DropdownMenuItem asChild className={MENU_ITEM_SIZE}>
                            <Link href={teamMemberEditUrl(member.id, 'profile')}>
                                <Pencil aria-hidden="true" />
                                {t('team.actions.edit')}
                            </Link>
                        </DropdownMenuItem>
                    ) : null}

                    {canResend ? (
                        <DropdownMenuItem
                            disabled={invitation.isPending}
                            onSelect={invitation.resend}
                            className={MENU_ITEM_SIZE}
                        >
                            <MailPlus aria-hidden="true" />
                            {t('team.actions.resend')}
                        </DropdownMenuItem>
                    ) : null}

                    {canCopyPassword ? (
                        <DropdownMenuItem
                            disabled={temporaryPassword.isCopying}
                            onSelect={temporaryPassword.copy}
                            className={MENU_ITEM_SIZE}
                        >
                            <KeyRound aria-hidden="true" />
                            {t('team.actions.copyTemporaryPassword')}
                        </DropdownMenuItem>
                    ) : null}

                    {(canEdit || canResend || canCopyPassword) && canRemove ? <DropdownMenuSeparator /> : null}

                    {canRemove ? (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setConfirmingRemoval(true)}
                            className={MENU_ITEM_SIZE}
                        >
                            <UserMinus aria-hidden="true" />
                            {t('team.actions.remove')}
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            {canRemove ? (
                <RemoveTeamMemberDialog
                    memberId={member.id}
                    name={member.name}
                    open={confirmingRemoval}
                    onOpenChange={setConfirmingRemoval}
                />
            ) : null}
        </div>
    );
}
