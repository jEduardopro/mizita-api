export function bookingPageHost(): string {
    return window.location.host;
}

export function bookingPageUrl(slug: string): string {
    return `${window.location.origin}/${slug}`;
}
