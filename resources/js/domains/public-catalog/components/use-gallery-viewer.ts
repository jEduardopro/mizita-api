import { useState } from 'react';

export type GalleryViewerState = {
    open: boolean;
    startIndex: number;
    onOpenChange: (open: boolean) => void;
};

export function useGalleryViewer() {
    const [isOpen, setIsOpen] = useState(false);
    const [startIndex, setStartIndex] = useState(0);

    const openAt = (index: number) => {
        setStartIndex(index);
        setIsOpen(true);
    };

    const viewerState: GalleryViewerState = {
        open: isOpen,
        startIndex,
        onOpenChange: setIsOpen,
    };

    return { openAt, viewerState };
}
