import { Link } from '@inertiajs/react';
import { KeyRound, Link2, LoaderCircle, MailPlus, MoreHorizontal, Pencil, UserMinus } from 'lucide-react';
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
import { useIsTeamMemberPaused } from '@/hooks/use-is-team-member-paused';
import type { TeamMember } from '../types';
import { RemoveTeamMemberDialog } from './RemoveTeamMemberDialog';
import { ResendInvitationBlockedMenuItem } from './ResendInvitationBlockedMenuItem';
import { TeamMemberRemovalBlockedDialog } from './TeamMemberRemovalBlockedDialog';
import { teamMemberEditUrl } from './team-urls';
import { useCopyBookingLink } from './use-copy-booking-link';
import { useCopyTemporaryPassword } from './use-copy-temporary-password';
import { useResendInvitation } from './use-resend-invitation';
import { canEditStaffProfile, useStaffProfileAccess } from './use-staff-profile-access';
import { useTeamInvitationAccess } from './use-team-invitation-access';
import { useTeamMemberRemoval } from './use-team-member-removal';

const MENU_ITEM_SIZE = 'min-h-11 md:min-h-8';

type Props = {
    member: TeamMember;
};

export function TeamMemberRowActions({ member }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const removal = useTeamMemberRemoval(member.id);
    const invitation = useResendInvitation(member.id);
    const access = useStaffProfileAccess(member.id);
    const invitationAccess = useTeamInvitationAccess();
    const temporaryPassword = useCopyTemporaryPassword(member.id);
    const copyBookingLink = useCopyBookingLink();
    const isPaused = useIsTeamMemberPaused();
    const bookingUrl = isPaused(member.level) ? null : member.booking_url;

    const canEdit = canEditStaffProfile(access);
    const canResend = invitationAccess === 'allowed' && member.invitation_pending;
    const isResendBlocked = invitationAccess === 'requires_upgrade' && member.invitation_pending;
    const canCopyPassword = can('reveal_temporary_password') && member.temporary_password_available;
    const canRemove = can('delete_staff_member') && member.level !== 'owner';
    const hasLeadingItems = canEdit || bookingUrl !== null || canResend || isResendBlocked || canCopyPassword;

    if (! hasLeadingItems && ! canRemove) {
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
                        aria-busy={removal.isChecking}
                        className="size-11 md:size-9"
                    >
                        {removal.isChecking ? (
                            <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
                        ) : (
                            <MoreHorizontal aria-hidden="true" />
                        )}
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

                    {bookingUrl === null ? null : (
                        <DropdownMenuItem onSelect={() => copyBookingLink(bookingUrl)} className={MENU_ITEM_SIZE}>
                            <Link2 aria-hidden="true" />
                            {t('team.actions.copyLink')}
                        </DropdownMenuItem>
                    )}

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

                    {isResendBlocked ? <ResendInvitationBlockedMenuItem /> : null}

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

                    {hasLeadingItems && canRemove ? <DropdownMenuSeparator /> : null}

                    {canRemove ? (
                        <DropdownMenuItem
                            variant="destructive"
                            disabled={removal.isChecking}
                            onSelect={removal.start}
                            className={MENU_ITEM_SIZE}
                        >
                            <UserMinus aria-hidden="true" />
                            {t('team.actions.remove')}
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            {canRemove ? (
                <>
                    <RemoveTeamMemberDialog
                        memberId={member.id}
                        name={member.name}
                        open={removal.dialog === 'confirm'}
                        onOpenChange={removal.handleOpenChange}
                        onBlocked={removal.showBlocked}
                    />

                    <TeamMemberRemovalBlockedDialog
                        open={removal.dialog === 'blocked'}
                        onOpenChange={removal.handleOpenChange}
                    />
                </>
            ) : null}
        </div>
    );
}
