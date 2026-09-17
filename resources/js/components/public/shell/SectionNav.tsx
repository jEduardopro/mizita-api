import { cn } from 'cn';
import type { MouseEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useActiveSection } from '@/hooks/use-active-section';

export type Section = {
    id: string;
    label: string;
};

const LAYOUT_CLASSES = {
    collapse: 'hidden md:flex',
    scroll: 'flex overflow-x-auto [-webkit-overflow-scrolling:touch] [scrollbar-width:none] [&>a]:shrink-0 [&::-webkit-scrollbar]:hidden',
} as const;

export type SectionNavLayout = keyof typeof LAYOUT_CLASSES;

type Props = {
    sections: Section[];
    layout?: SectionNavLayout;
    className?: string;
};

export function SectionNav({ sections, layout = 'collapse', className }: Props) {
    const { t } = useTranslation('common');
    const activeId = useActiveSection(sections.map((section) => section.id));

    function handleClick(event: MouseEvent<HTMLAnchorElement>, id: string): void {
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
            className={cn('items-center', LAYOUT_CLASSES[layout], className)}
        >
            {sections.map((section) => {
                const isActive = section.id === activeId;

                return (
                    <a
                        key={section.id}
                        href={`#${section.id}`}
                        aria-current={isActive ? 'true' : undefined}
                        onClick={(event) => handleClick(event, section.id)}
                        className={cn(
                            'relative flex h-14 items-center rounded-md px-2.5 text-[0.625rem] font-medium tracking-[0.1em] uppercase transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50 sm:text-[0.6875rem] sm:tracking-[0.14em]',
                            isActive ? 'text-foreground' : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {section.label}

                        <span
                            aria-hidden="true"
                            className={cn(
                                'absolute inset-x-2.5 -bottom-px h-px transition-colors',
                                isActive ? 'bg-primary' : 'bg-transparent',
                            )}
                        />
                    </a>
                );
            })}
        </nav>
    );
}
