import { router } from '@inertiajs/react';
import { LoaderCircle, MoreHorizontal, UserMinus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { RemoveTeamMemberDialog } from './RemoveTeamMemberDialog';
import { TeamMemberRemovalBlockedDialog } from './TeamMemberRemovalBlockedDialog';
import { TEAM_SETTINGS_URL } from './team-urls';
import { useTeamMemberRemoval } from './use-team-member-removal';

type Props = {
    memberId: string;
    name: string;
};

export function TeamMemberProfileMenu({ memberId, name }: Props) {
    const { t } = useTranslation('admin');
    const removal = useTeamMemberRemoval(memberId);

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
                    <DropdownMenuItem
                        variant="destructive"
                        disabled={removal.isChecking}
                        onSelect={removal.start}
                        className="min-h-11 md:min-h-8"
                    >
                        <UserMinus aria-hidden="true" />
                        {t('team.actions.removeMember')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

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
    );
}
