import { cn } from 'cn';
import type { MouseEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useActiveSection } from '@/hooks/use-active-section';

export type SettingsSection = {
    id: string;
    label: string;
};

type Props = {
    sections: SettingsSection[];
};

export function SettingsSubnav({ sections }: Props) {
    const { t } = useTranslation('common');
    const activeId = useActiveSection(sections.map((section) => section.id));

    function scrollToSection(event: MouseEvent<HTMLAnchorElement>, id: string): void {
        const section = document.getElementById(id);

        if (section === null) {
            return;
        }

        event.preventDefault();

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        section.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
        section.focus({ preventScroll: true });

        window.history.replaceState(null, '', `#${id}`);
    }

    return (
        <nav
            aria-label={t('nav.sections')}
            className="sticky top-28 z-10 -mx-5 border-b border-border bg-background/90 py-2 backdrop-blur-sm sm:-mx-8 md:top-32 md:mx-0 md:border-0 md:bg-transparent md:py-0 md:backdrop-blur-none"
        >
            <ul className="flex gap-2 overflow-x-auto px-5 [scrollbar-width:none] sm:px-8 md:flex-col md:gap-1 md:overflow-visible md:px-0 [&::-webkit-scrollbar]:hidden">
                {sections.map((section) => {
                    const isActive = section.id === activeId;

                    return (
                        <li key={section.id} className="shrink-0 md:shrink">
                            <a
                                href={`#${section.id}`}
                                aria-current={isActive ? 'true' : undefined}
                                onClick={(event) => scrollToSection(event, section.id)}
                                className={cn(
                                    'flex h-11 items-center rounded-full border px-4 text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                                    'md:h-10 md:rounded-lg md:px-3',
                                    isActive
                                        ? 'border-border bg-secondary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {section.label}
                            </a>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
