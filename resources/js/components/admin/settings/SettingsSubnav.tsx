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
            className="sticky top-28 z-10 -mx-5 min-w-0 border-b border-border bg-background/90 backdrop-blur-sm sm:-mx-8 md:top-32 md:mx-0 md:border-0 md:bg-transparent md:backdrop-blur-none"
        >
            <ul className="-mb-px flex gap-2 overflow-x-auto px-5 [scrollbar-width:none] sm:px-8 md:mb-0 md:flex-col md:gap-1 md:overflow-visible md:px-0 [&::-webkit-scrollbar]:hidden">
                {sections.map((section) => {
                    const isActive = section.id === activeId;

                    return (
                        <li key={section.id} className="shrink-0 md:shrink">
                            <a
                                href={`#${section.id}`}
                                aria-current={isActive ? 'true' : undefined}
                                onClick={(event) => scrollToSection(event, section.id)}
                                className={cn(
                                    'flex items-center border-b-2 px-1.5 py-3.5 text-xs font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                                    'md:h-10 md:rounded-lg md:border md:px-3 md:py-0 md:text-sm',
                                    isActive
                                        ? 'border-primary text-foreground md:border-border md:bg-secondary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground md:hover:bg-muted',
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
