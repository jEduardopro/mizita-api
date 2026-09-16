import type { PublicLocation } from '../types';

export function addressLinesFrom(location: PublicLocation): string[] {
    const locality = [location.postal_code, location.city]
        .map((part) => part.trim())
        .filter((part) => part !== '')
        .join(' ');

    return [location.street.trim(), locality, (location.state ?? '').trim()].filter(
        (line) => line !== '',
    );
}

export function mapUrlFor(location: PublicLocation): string | null {
    if (location.latitude === null || location.longitude === null) {
        return null;
    }

    const coordinates = `${location.latitude},${location.longitude}`;

    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(coordinates)}`;
}
