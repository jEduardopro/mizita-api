import type { ContactFieldLevel } from '../../types';

export type ShownContactFieldLevel = Exclude<ContactFieldLevel, 'hidden'>;

export function isContactFieldShown(level: ContactFieldLevel): level is ShownContactFieldLevel {
    return level !== 'hidden';
}

export function isContactFieldRequired(level: ContactFieldLevel): boolean {
    return level === 'required';
}

export function isContactFieldOptional(level: ContactFieldLevel): boolean {
    return level === 'optional';
}
