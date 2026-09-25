import { useTranslation } from 'react-i18next';
import { useInitialFocus } from '@/hooks/use-initial-focus';
import type { TeamMember } from '../types';
import { InvitedMemberRow } from './InvitedMemberRow';

type Props = {
    members: readonly TeamMember[];
};

export function InvitedMembersSummary({ members }: Props) {
    const { t } = useTranslation('admin');
    const introRef = useInitialFocus<HTMLParagraphElement>(true);

    return (
        <div className="grid gap-4">
            <p ref={introRef} tabIndex={-1} className="text-sm text-pretty text-muted-foreground outline-none">
                {t('team.invited.body')}
            </p>

            <ul className="grid gap-2">
                {members.map((member) => (
                    <InvitedMemberRow
                        key={member.id}
                        memberId={member.id}
                        name={member.name}
                        email={member.email}
                        photoUrl={member.photo_url}
                        level={member.level}
                        temporaryPasswordAvailable={member.temporary_password_available}
                    />
                ))}
            </ul>
        </div>
    );
}
