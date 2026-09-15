const MAX_INITIALS = 2;

export function initialsFrom(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .filter((word) => word.length > 0)
        .slice(0, MAX_INITIALS)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');
}
