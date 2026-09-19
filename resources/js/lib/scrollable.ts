// Radix Dialog's react-remove-scroll lock cancels wheel and touchmove for any target outside it, and a portalled popover list is outside it.
export function keepScrollableWhileModalOpen(node: HTMLElement | null): (() => void) | undefined {
    if (node === null) {
        return;
    }

    const keepEventOnNode = (event: Event) => event.stopPropagation();

    node.addEventListener('wheel', keepEventOnNode, { passive: true });
    node.addEventListener('touchmove', keepEventOnNode, { passive: true });

    return () => {
        node.removeEventListener('wheel', keepEventOnNode);
        node.removeEventListener('touchmove', keepEventOnNode);
    };
}
