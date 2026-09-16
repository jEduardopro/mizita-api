import { Phone } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { SectionNav, type Section } from '@/components/public/shell/SectionNav';
import { Button } from '@/components/ui/button';

type Props = {
    name: string;
    phone: string | null;
    sections: Section[];
};

export function BookingHeader({ name, phone, sections }: Props) {
    const { t } = useTranslation('public');

    return (
        <header className="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur-md supports-[backdrop-filter]:bg-background/70">
            <div className="mx-auto flex h-14 w-full max-w-5xl items-center gap-3 px-5 sm:px-8">
                <p className="min-w-0 flex-1 truncate font-heading text-sm font-medium tracking-[-0.01em]">
                    {name}
                </p>

                {sections.length > 0 ? <SectionNav sections={sections} /> : null}

                {phone === null ? null : (
                    <Button asChild variant="outline" className="h-11 shrink-0 gap-2 px-3">
                        <a href={`tel:${phone}`}>
                            <Phone aria-hidden="true" />
                            <span className="sr-only sm:not-sr-only">{t('booking.header.call')}</span>
                        </a>
                    </Button>
                )}
            </div>
        </header>
    );
}
