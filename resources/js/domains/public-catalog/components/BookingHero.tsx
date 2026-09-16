import { cn } from 'cn';
import { Store } from 'lucide-react';
import type { BrandColorClasses } from '@/lib/booking-brand';
import type { BookingOpenState } from './booking-schedule';
import { BookingOpenBadge } from './BookingOpenBadge';

type Props = {
    name: string;
    logoUrl: string | null;
    bannerUrl: string | null;
    accent: BrandColorClasses;
    openState: BookingOpenState;
};

export function BookingHero({ name, logoUrl, bannerUrl, accent, openState }: Props) {
    return (
        <div>
            <div
                className={cn(
                    'relative w-full overflow-hidden',
                    accent.surface,
                    bannerUrl === null
                        ? 'aspect-[5/2] sm:aspect-[8/2]'
                        : 'aspect-[3/2] sm:aspect-[5/2] lg:aspect-[21/8]',
                )}
            >
                {bannerUrl === null ? null : (
                    <img src={bannerUrl} alt="" className="size-full object-cover" />
                )}

                <span
                    aria-hidden="true"
                    className="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-background to-transparent"
                />
            </div>

            <div className="mx-auto w-full max-w-5xl px-5 sm:px-8">
                <span
                    className={cn(
                        'relative -mt-10 grid size-20 place-content-center overflow-hidden rounded-full ring-4 ring-background sm:-mt-12 sm:size-24',
                        accent.surface,
                    )}
                >
                    {logoUrl === null ? (
                        <Store aria-hidden="true" className="size-7 text-muted-foreground" />
                    ) : (
                        <img src={logoUrl} alt="" className="size-full object-cover" />
                    )}
                </span>

                <h1 className="mt-4 font-heading text-[clamp(1.75rem,6vw,2.75rem)] leading-[1.05] font-medium tracking-[-0.035em] text-balance">
                    {name}
                </h1>

                <BookingOpenBadge state={openState} accent={accent} className="mt-4" />
            </div>
        </div>
    );
}
