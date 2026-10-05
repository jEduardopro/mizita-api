import { ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { BrandColorClasses } from '@/lib/booking-brand';
import { initialsFrom } from '@/lib/initials';
import type { PublicTeamMember } from '../types';

type Props = {
    team: PublicTeamMember[];
    accent: BrandColorClasses;
};

type MemberIdentityProps = {
    name: string;
    photoUrl: string | null;
    accent: BrandColorClasses;
};

function MemberIdentity({ name, photoUrl, accent }: MemberIdentityProps) {
    return (
        <>
            <Avatar size="lg">
                {photoUrl === null ? null : (
                    <AvatarImage src={photoUrl} alt="" loading="lazy" decoding="async" />
                )}

                <AvatarFallback className={accent.surface}>{initialsFrom(name)}</AvatarFallback>
            </Avatar>

            <span className="truncate text-sm font-medium">{name}</span>
        </>
    );
}

export function BookingTeam({ team, accent }: Props) {
    const { t } = useTranslation('public');

    return (
        <ul className="flex flex-wrap gap-x-6 gap-y-4">
            {team.map((member) => (
                <li key={member.id} className="flex min-w-0">
                    {member.booking_url === null ? (
                        <span className="flex min-w-0 items-center gap-3">
                            <MemberIdentity
                                name={member.name}
                                photoUrl={member.photo_url}
                                accent={accent}
                            />
                        </span>
                    ) : (
                        <a
                            href={member.booking_url}
                            aria-label={t('booking.team.bookWith', { name: member.name })}
                            className="-mx-2 -my-1 flex min-h-11 min-w-0 items-center gap-3 rounded-full px-2 py-1 outline-none motion-safe:transition-colors hover:bg-muted/60 focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            <MemberIdentity
                                name={member.name}
                                photoUrl={member.photo_url}
                                accent={accent}
                            />

                            <ChevronRight
                                aria-hidden="true"
                                className="size-4 shrink-0 text-muted-foreground"
                            />
                        </a>
                    )}
                </li>
            ))}
        </ul>
    );
}
