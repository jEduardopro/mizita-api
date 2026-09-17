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

export function addressLineFrom(location: PublicLocation): string {
    return addressLinesFrom(location).join(', ');
}

function mapQueryFor(location: PublicLocation): string {
    if (location.latitude === null || location.longitude === null) {
        return addressLineFrom(location);
    }

    return `${location.latitude},${location.longitude}`;
}

export function mapUrlFor(location: PublicLocation): string {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(mapQueryFor(location))}`;
}

export function mapEmbedUrlFor(location: PublicLocation): string {
    return `https://www.google.com/maps?q=${encodeURIComponent(mapQueryFor(location))}&z=15&output=embed`;
}
