import { cn } from 'cn';
import { Phone, Store } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { SectionNav, type Section } from '@/components/public/shell/SectionNav';
import { Button } from '@/components/ui/button';
import type { BrandColorClasses } from '@/lib/booking-brand';

type Props = {
    name: string;
    logoUrl: string | null;
    phone: string | null;
    accent: BrandColorClasses;
    sections: Section[];
};

export function BookingHeader({ name, logoUrl, phone, accent, sections }: Props) {
    const { t } = useTranslation('public');

    return (
        <header className="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur-md supports-[backdrop-filter]:bg-background/70">
            <div className="mx-auto flex h-14 w-full max-w-5xl items-center gap-3 px-5 sm:px-8">
                <span className="flex min-w-0 items-center gap-2">
                    <span
                        className={cn(
                            'grid size-8 shrink-0 place-content-center overflow-hidden rounded-full',
                            accent.surface,
                        )}
                    >
                        {logoUrl === null ? (
                            <Store aria-hidden="true" className="size-4 text-muted-foreground" />
                        ) : (
                            <img src={logoUrl} alt="" className="size-full object-cover" />
                        )}
                    </span>

                    <span className="sr-only font-heading text-sm font-medium tracking-[-0.01em] sm:not-sr-only sm:truncate">
                        {name}
                    </span>
                </span>

                {sections.length === 0 ? null : (
                    <SectionNav sections={sections} layout="scroll" className="min-w-0 flex-1" />
                )}

                {phone === null ? null : (
                    <Button
                        asChild
                        variant="outline"
                        className="ml-auto hidden h-11 shrink-0 gap-2 px-3 sm:inline-flex"
                    >
                        <a href={`tel:${phone}`}>
                            <Phone aria-hidden="true" />
                            {t('booking.header.call')}
                        </a>
                    </Button>
                )}
            </div>
        </header>
    );
}
