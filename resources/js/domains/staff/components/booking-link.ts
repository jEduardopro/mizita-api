import type { BookingLinkBlocker } from '../types';

const PROTOCOL_PATTERN = /^https?:\/\//;

export function bookingLinkPrefixOf(url: string, slug: string): string {
    if (url.endsWith(slug)) {
        return url.slice(0, url.length - slug.length);
    }

    return url.slice(0, url.lastIndexOf('/') + 1);
}

export function withoutProtocol(url: string): string {
    return url.replace(PROTOCOL_PATTERN, '');
}

export function bookingLinkBlockerKey(blockers: readonly BookingLinkBlocker[]) {
    const missesServices = blockers.includes('no_services');
    const missesWorkingHours = blockers.includes('no_working_hours');

    if (missesServices && missesWorkingHours) {
        return 'profile.bookingLink.blockers.both' as const;
    }

    return missesServices
        ? ('profile.bookingLink.blockers.noServices' as const)
        : ('profile.bookingLink.blockers.noWorkingHours' as const);
}
