import { useCallback, useRef } from 'react';

const EDITABLE_TAG_NAMES = ['INPUT', 'TEXTAREA', 'SELECT'];

function isEditing(element: Element | null): boolean {
    if (! (element instanceof HTMLElement)) {
        return false;
    }

    return EDITABLE_TAG_NAMES.includes(element.tagName) || element.isContentEditable;
}

export function useInitialFocus<TElement extends HTMLElement>(enabled: boolean) {
    const hasFocused = useRef(false);

    return useCallback(
        (element: TElement | null) => {
            if (! enabled || element === null || hasFocused.current) {
                return;
            }

            if (isEditing(document.activeElement)) {
                return;
            }

            hasFocused.current = true;
            element.focus({ preventScroll: true });
        },
        [enabled],
    );
}
