import {
    DEFAULT_ASSIGNABLE_STAFF_ROLE,
    type AssignableStaffRole,
    type InviteTeamMembersPayload,
} from '../types';

export type InviteeField = 'name' | 'email' | 'level';

export type RequiredInviteeField = Exclude<InviteeField, 'level'>;

export type InviteeValues = {
    name: string;
    email: string;
    level: AssignableStaffRole;
};

export type InviteeDraft = InviteeValues & {
    key: string;
};

export function blankInvitee(key: string): InviteeDraft {
    return { key, name: '', email: '', level: DEFAULT_ASSIGNABLE_STAFF_ROLE };
}

export function missesField(invitee: InviteeValues, field: RequiredInviteeField): boolean {
    return invitee[field].trim() === '';
}

export function isBlankInvitee(invitee: InviteeValues): boolean {
    return missesField(invitee, 'name') && missesField(invitee, 'email');
}

export function isCompleteInvitee(invitee: InviteeValues): boolean {
    return ! missesField(invitee, 'name') && ! missesField(invitee, 'email');
}

export function inviteeServerField(index: number, field: InviteeField): string {
    return `members.${index}.${field}`;
}

export function invitationPayloadFrom(invitees: readonly InviteeValues[]): InviteTeamMembersPayload {
    return {
        members: invitees.map((invitee) => ({
            name: invitee.name.trim(),
            email: invitee.email.trim(),
            level: invitee.level,
        })),
    };
}
