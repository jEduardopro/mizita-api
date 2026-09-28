import { cn } from 'cn';
import { useId } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { BrandColorClasses } from '@/lib/booking-brand';
import { initialsFrom } from '@/lib/initials';
import { BookingStaffAbout } from './BookingStaffAbout';

type Props = {
    name: string;
    photoUrl: string | null;
    jobTitle: string | null;
    about: string | null;
    accent: BrandColorClasses;
};

export function BookingStaffHeader({ name, photoUrl, jobTitle, about, accent }: Props) {
    const nameId = useId();

    return (
        <section
            aria-labelledby={nameId}
            className="grid gap-4 rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
        >
            <div className="flex min-w-0 items-center gap-4">
                <span className={cn('shrink-0 rounded-full p-0.5', accent.accent)}>
                    <Avatar className="size-16 border-2 border-card sm:size-20">
                        {photoUrl === null ? null : <AvatarImage src={photoUrl} alt="" />}

                        <AvatarFallback
                            className={cn(
                                'font-heading text-lg font-semibold text-foreground sm:text-xl',
                                accent.surface,
                            )}
                        >
                            {initialsFrom(name)}
                        </AvatarFallback>
                    </Avatar>
                </span>

                <div className="grid min-w-0 gap-1">
                    <p
                        id={nameId}
                        className="font-heading text-lg leading-tight font-semibold tracking-[-0.01em] text-balance break-words sm:text-xl"
                    >
                        {name}
                    </p>

                    {jobTitle === null ? null : (
                        <p className="text-sm text-pretty break-words text-muted-foreground">
                            {jobTitle}
                        </p>
                    )}
                </div>
            </div>

            {about === null ? null : <BookingStaffAbout text={about} />}
        </section>
    );
}
