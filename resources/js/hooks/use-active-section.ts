import { useEffect, useState } from 'react';

/**
 * A narrow strip below the sticky header rather than the whole viewport: a
 * section becomes current as its heading clears the header. Observing the full
 * viewport would keep two long sections current at once.
 */
const OBSERVED_BAND = '-20% 0px -70% 0px';

export function useActiveSection(ids: string[]): string | undefined {
    // Callers build the list while rendering, so it is a new array every time.
    // Keying the effect on the joined ids keeps one observer alive instead.
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

                // Resolved in document order, so while two sections share the
                // band the one higher up the page wins — the same one whose
                // heading the reader has just passed.
                setActiveId(sectionIds.find((id) => inBand.has(id)));
            },
            { rootMargin: OBSERVED_BAND },
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
    }, [key]);

    return activeId;
}
