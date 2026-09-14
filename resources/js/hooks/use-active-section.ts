import { useEffect, useState } from 'react';

const OBSERVED_BAND = '-20% 0px -70% 0px';

export function useActiveSection(ids: string[]): string | undefined {
    const key = ids.join(',');
    const [activeId, setActiveId] = useState<string>();

    useEffect(() => {
        const sectionIds = key.split(',').filter((id) => id !== '');
        const sections = sectionIds
            .map((id) => document.getElementById(id))
            .filter((section) => section !== null);

        if (sections.length === 0) {
            return;
        }

        const inBand = new Set<string>();

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        inBand.add(entry.target.id);
                    } else {
                        inBand.delete(entry.target.id);
                    }
                }

                setActiveId(sectionIds.find((id) => inBand.has(id)));
            },
            { rootMargin: OBSERVED_BAND },
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
    }, [key]);

    return activeId;
}
