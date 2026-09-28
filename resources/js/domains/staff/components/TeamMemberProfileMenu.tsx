import { router } from '@inertiajs/react';
import { Link2, LoaderCircle, MoreHorizontal, UserMinus } from 'lucide-react';
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
import type { StaffRole } from '../types';
import { RemoveTeamMemberDialog } from './RemoveTeamMemberDialog';
import { TeamMemberRemovalBlockedDialog } from './TeamMemberRemovalBlockedDialog';
import { TEAM_SETTINGS_URL } from './team-urls';
import { useCopyBookingLink } from './use-copy-booking-link';
import { useTeamMemberRemoval } from './use-team-member-removal';

const MENU_ITEM_SIZE = 'min-h-11 md:min-h-8';

type Props = {
    memberId: string;
    name: string;
    level: StaffRole;
    bookingUrl: string | null;
};

export function TeamMemberProfileMenu({ memberId, name, level, bookingUrl: issuedBookingUrl }: Props) {
    const { t } = useTranslation('admin');
    const { can } = useAuthorization();
    const removal = useTeamMemberRemoval(memberId);
    const copyBookingLink = useCopyBookingLink();
    const isPaused = useIsTeamMemberPaused();

    const bookingUrl = isPaused(level) ? null : issuedBookingUrl;
    const canRemove = can('delete_staff_member') && level !== 'owner';

    if (bookingUrl === null && ! canRemove) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={t('team.actions.more', { name })}
                        aria-busy={removal.isChecking}
                        className="size-11 text-muted-foreground hover:text-foreground"
                    >
                        {removal.isChecking ? (
                            <LoaderCircle aria-hidden="true" className="motion-safe:animate-spin" />
                        ) : (
                            <MoreHorizontal aria-hidden="true" />
                        )}
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" className="w-60">
                    {bookingUrl === null ? null : (
                        <DropdownMenuItem onSelect={() => copyBookingLink(bookingUrl)} className={MENU_ITEM_SIZE}>
                            <Link2 aria-hidden="true" />
                            {t('team.actions.copyLink')}
                        </DropdownMenuItem>
                    )}

                    {bookingUrl !== null && canRemove ? <DropdownMenuSeparator /> : null}

                    {canRemove ? (
                        <DropdownMenuItem
                            variant="destructive"
                            disabled={removal.isChecking}
                            onSelect={removal.start}
                            className={MENU_ITEM_SIZE}
                        >
                            <UserMinus aria-hidden="true" />
                            {t('team.actions.removeMember')}
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            {canRemove ? (
                <>
                    <RemoveTeamMemberDialog
                        memberId={memberId}
                        name={name}
                        open={removal.dialog === 'confirm'}
                        onOpenChange={removal.handleOpenChange}
                        onBlocked={removal.showBlocked}
                        onRemoved={() => router.visit(TEAM_SETTINGS_URL)}
                    />

                    <TeamMemberRemovalBlockedDialog
                        open={removal.dialog === 'blocked'}
                        onOpenChange={removal.handleOpenChange}
                    />
                </>
            ) : null}
        </>
    );
}
