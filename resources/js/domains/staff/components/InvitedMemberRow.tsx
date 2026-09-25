import { cn } from 'cn';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import type { StaffRole } from '../types';
import { TeamMemberAvatar } from './TeamMemberAvatar';
import { TemporaryPasswordCopyButton } from './TemporaryPasswordCopyButton';

type Props = {
    memberId: string;
    name: string;
    email: string;
    photoUrl: string | null;
    level: StaffRole;
    temporaryPasswordAvailable: boolean;
};

export function InvitedMemberRow({ memberId, name, email, photoUrl, level, temporaryPasswordAvailable }: Props) {
    const { t } = useTranslation('admin');
    const identityId = useId();

    const noPasswordReason =
        level === 'no_access' ? t('team.invited.noPassword.noAccess') : t('team.invited.noPassword.ownPassword');

    return (
        <li
            className={cn(
                'grid gap-3 rounded-xl border p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center',
                temporaryPasswordAvailable ? 'border-dashed border-border bg-muted/40' : 'border-transparent',
            )}
        >
            <div className="flex min-w-0 items-center gap-3">
                <TeamMemberAvatar name={name} photoUrl={photoUrl} />

                <div id={identityId} className="grid min-w-0 gap-0.5">
                    <p className="truncate text-sm font-medium">{name}</p>
                    <p className="truncate text-xs text-muted-foreground">{email}</p>
                </div>
            </div>

            {temporaryPasswordAvailable ? (
                <TemporaryPasswordCopyButton memberId={memberId} describedBy={identityId} />
            ) : (
                <p className="text-xs text-pretty text-muted-foreground sm:max-w-56 sm:text-right">
                    {noPasswordReason}
                </p>
            )}
        </li>
    );
}
