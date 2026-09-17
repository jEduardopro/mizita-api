import { cn } from 'cn';
import type { BrandColorClasses } from '@/lib/booking-brand';

type Props = {
    bannerUrl: string | null;
    accent: BrandColorClasses;
};

export function BookingHero({ bannerUrl, accent }: Props) {
    return (
        <div className="mx-auto w-full max-w-5xl sm:px-8">
            <div
                className={cn(
                    'relative w-full overflow-hidden sm:rounded-2xl',
                    accent.surface,
                    bannerUrl === null
                        ? 'h-[clamp(100px,14svh,150px)] sm:h-[clamp(110px,18svh,190px)]'
                        : 'h-[clamp(150px,24svh,240px)] sm:h-[clamp(160px,34svh,340px)]',
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
        </div>
    );
}
