import { useEffect } from 'react';

const ICON_LINK_SELECTOR = 'link[rel~="icon"]';

type IconLinkSnapshot = {
    link: HTMLLinkElement;
    href: string | null;
    type: string | null;
};

function snapshotOf(link: HTMLLinkElement): IconLinkSnapshot {
    return {
        link,
        href: link.getAttribute('href'),
        type: link.getAttribute('type'),
    };
}

function restoreAttribute(link: HTMLLinkElement, name: string, value: string | null): void {
    if (value === null) {
        link.removeAttribute(name);

        return;
    }

    link.setAttribute(name, value);
}

function restore({ link, href, type }: IconLinkSnapshot): void {
    restoreAttribute(link, 'href', href);
    restoreAttribute(link, 'type', type);
}

function pointAt(link: HTMLLinkElement, logoUrl: string): void {
    link.removeAttribute('type');
    link.setAttribute('href', logoUrl);
}

export function useBusinessFavicon(logoUrl: string | null): void {
    useEffect(() => {
        if (logoUrl === null) {
            return;
        }

        const links = Array.from(document.head.querySelectorAll<HTMLLinkElement>(ICON_LINK_SELECTOR));
        const snapshots = links.map(snapshotOf);

        links.forEach((link) => pointAt(link, logoUrl));

        return () => snapshots.forEach(restore);
    }, [logoUrl]);
}
