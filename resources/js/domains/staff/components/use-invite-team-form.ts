import { useRef, useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useServerErrors } from '@/hooks/use-server-errors';
import { raiseSuccessToast } from '@/lib/toast';
import { useInviteTeamMembers } from '../queries';
import { TEAM_INVITATION_MAXIMUM_MEMBERS } from '../types';
import {
    blankInvitee,
    invitationPayloadFrom,
    inviteeServerField,
    isBlankInvitee,
    isCompleteInvitee,
    missesField,
    type InviteeDraft,
    type InviteeField,
    type InviteeValues,
    type RequiredInviteeField,
} from './team-invite-values';

const FIRST_INVITEE_KEY = 'invitee-0';

const REQUIRED_MESSAGE_KEYS = {
    name: 'team.invite.name.required',
    email: 'team.invite.email.required',
} as const satisfies Record<RequiredInviteeField, string>;

export type InviteeErrors = Partial<Record<InviteeField, string>>;

export type InviteTeamFormController = {
    invitees: readonly InviteeDraft[];
    canAddInvitee: boolean;
    canSubmit: boolean;
    isSubmitting: boolean;
    addInvitee: () => void;
    removeInvitee: (key: string) => void;
    update: <TField extends InviteeField>(key: string, field: TField, value: InviteeValues[TField]) => void;
    touch: (key: string, field: RequiredInviteeField) => void;
    errorsFor: (invitee: InviteeDraft, index: number) => InviteeErrors;
    submit: (event: FormEvent<HTMLFormElement>) => void;
};

type Params = {
    onInvited: () => void;
};

function touchedId(key: string, field: RequiredInviteeField): string {
    return `${key}.${field}`;
}

export function useInviteTeamForm({ onInvited }: Params): InviteTeamFormController {
    const { t } = useTranslation('admin');
    const inviteTeamMembers = useInviteTeamMembers();
    const { fieldErrors, capture, clearField, reset } = useServerErrors();

    const nextKey = useRef(1);
    const [invitees, setInvitees] = useState<InviteeDraft[]>(() => [blankInvitee(FIRST_INVITEE_KEY)]);
    const [touched, setTouched] = useState<ReadonlySet<string>>(() => new Set());
    const [hasAttempted, setHasAttempted] = useState(false);

    const canAddInvitee = invitees.length < TEAM_INVITATION_MAXIMUM_MEMBERS;

    function addInvitee() {
        if (! canAddInvitee) {
            return;
        }

        const key = `invitee-${nextKey.current}`;

        nextKey.current += 1;
        setInvitees((current) => [...current, blankInvitee(key)]);
    }

    function removeInvitee(key: string) {
        setInvitees((current) => current.filter((invitee) => invitee.key !== key));
        reset();
    }

    function update<TField extends InviteeField>(key: string, field: TField, value: InviteeValues[TField]) {
        const index = invitees.findIndex((invitee) => invitee.key === key);

        setInvitees((current) =>
            current.map((invitee) => (invitee.key === key ? { ...invitee, [field]: value } : invitee)),
        );
        clearField(inviteeServerField(index, field));
    }

    function touch(key: string, field: RequiredInviteeField) {
        const id = touchedId(key, field);

        setTouched((current) => (current.has(id) ? current : new Set(current).add(id)));
    }

    function requiredError(invitee: InviteeDraft, field: RequiredInviteeField): string | undefined {
        const isReported = touched.has(touchedId(invitee.key, field)) || (hasAttempted && ! isBlankInvitee(invitee));

        return isReported && missesField(invitee, field) ? t(REQUIRED_MESSAGE_KEYS[field]) : undefined;
    }

    function errorsFor(invitee: InviteeDraft, index: number): InviteeErrors {
        return {
            name: fieldErrors[inviteeServerField(index, 'name')] ?? requiredError(invitee, 'name'),
            email: fieldErrors[inviteeServerField(index, 'email')] ?? requiredError(invitee, 'email'),
            level: fieldErrors[inviteeServerField(index, 'level')],
        };
    }

    async function save() {
        const filled = invitees.filter((invitee) => ! isBlankInvitee(invitee));

        setHasAttempted(true);

        if (filled.length === 0) {
            return;
        }

        setInvitees(filled);

        if (! filled.every(isCompleteInvitee)) {
            return;
        }

        reset();

        try {
            const members = await inviteTeamMembers.mutateAsync(invitationPayloadFrom(filled));

            raiseSuccessToast(t('team.toasts.invited', { count: members.length }));
            onInvited();
        } catch (error) {
            capture(error, t('team.invite.unexpected'));
        }
    }

    return {
        invitees,
        canAddInvitee,
        canSubmit: invitees.some((invitee) => ! missesField(invitee, 'name')),
        isSubmitting: inviteTeamMembers.isPending,
        addInvitee,
        removeInvitee,
        update,
        touch,
        errorsFor,
        submit: (event) => {
            event.preventDefault();
            void save();
        },
    };
}
