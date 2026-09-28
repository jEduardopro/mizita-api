import { useCallback, useRef, useState } from 'react';

type ExpandableText = {
    ref(node: HTMLElement | null): (() => void) | undefined;
    isExpanded: boolean;
    canExpand: boolean;
    toggle(): void;
};

export function useExpandableText(): ExpandableText {
    const [isExpanded, setIsExpanded] = useState(false);
    const [overflows, setOverflows] = useState(false);
    const isExpandedRef = useRef(false);

    const ref = useCallback((node: HTMLElement | null) => {
        if (node === null) {
            return undefined;
        }

        const measure = () => {
            if (! isExpandedRef.current) {
                setOverflows(node.scrollHeight > node.clientHeight);
            }
        };

        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    const toggle = useCallback(() => {
        isExpandedRef.current = ! isExpandedRef.current;
        setIsExpanded(isExpandedRef.current);
    }, []);

    return { ref, isExpanded, canExpand: overflows, toggle };
}
