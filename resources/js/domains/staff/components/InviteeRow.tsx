import { cn } from 'cn';
import { X } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitialFocus } from '@/hooks/use-initial-focus';
import { PROFILE_NAME_MAX_LENGTH, TEAM_MEMBER_EMAIL_MAX_LENGTH } from '../types';
import { INVITEE_GRID_COLUMNS } from './invitee-grid';
import type { InviteeField, InviteeValues, RequiredInviteeField } from './team-invite-values';
import { TeamLevelSelect } from './TeamLevelSelect';
import type { InviteeErrors } from './use-invite-team-form';

const CONTROL_SIZE = 'h-11 text-base md:h-9 md:text-sm';

const FIELD_CELL = 'col-span-2 grid content-start gap-2 md:col-span-1 md:row-start-1';

type Props = {
    number: number;
    invitee: InviteeValues;
    errors: InviteeErrors;
    onChange: <TField extends InviteeField>(field: TField, value: InviteeValues[TField]) => void;
    onBlur: (field: RequiredInviteeField) => void;
    onRemove?: () => void;
};

export function InviteeRow({ number, invitee, errors, onChange, onBlur, onRemove }: Props) {
    const { t } = useTranslation('admin');
    const rowId = useId();
    const nameRef = useInitialFocus<HTMLInputElement>(true);

    const titleId = `${rowId}-title`;
    const nameMessage = fieldMessage({ id: `${rowId}-name`, error: errors.name });
    const emailMessage = fieldMessage({ id: `${rowId}-email`, error: errors.email });
    const levelMessage = fieldMessage({ id: `${rowId}-level`, error: errors.level });

    return (
        <div
            role="group"
            aria-labelledby={titleId}
            className={cn(
                'grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-2 gap-y-3 rounded-xl border border-border p-3',
                'md:gap-x-3 md:gap-y-0 md:rounded-none md:border-0 md:p-0',
                INVITEE_GRID_COLUMNS,
            )}
        >
            <p id={titleId} className="flex min-h-11 items-center text-sm font-semibold md:sr-only">
                {t('team.invite.member', { number })}
            </p>

            {onRemove === undefined ? null : (
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onRemove}
                    aria-label={t('team.invite.removeMember', { number })}
                    className="size-11 p-0 text-muted-foreground hover:text-foreground md:col-start-4 md:row-start-1 md:size-9"
                >
                    <X aria-hidden="true" />
                </Button>
            )}

            <div className={cn(FIELD_CELL, 'md:col-start-1')}>
                <Label htmlFor={`${rowId}-name`} className="md:sr-only">
                    {t('team.invite.name.label')}
                </Label>

                <Input
                    ref={nameRef}
                    id={`${rowId}-name`}
                    autoComplete="off"
                    required
                    maxLength={PROFILE_NAME_MAX_LENGTH}
                    placeholder={t('team.invite.name.placeholder')}
                    aria-invalid={!! errors.name}
                    aria-describedby={nameMessage?.id}
                    value={invitee.name}
                    onChange={(event) => onChange('name', event.target.value)}
                    onBlur={() => onBlur('name')}
                    className={CONTROL_SIZE}
                />

                <FieldMessage message={nameMessage} />
            </div>

            <div className={cn(FIELD_CELL, 'md:col-start-2')}>
                <Label htmlFor={`${rowId}-email`} className="md:sr-only">
                    {t('team.invite.email.label')}
                </Label>

                <Input
                    id={`${rowId}-email`}
                    type="email"
                    inputMode="email"
                    autoComplete="off"
                    spellCheck={false}
                    required
                    maxLength={TEAM_MEMBER_EMAIL_MAX_LENGTH}
                    placeholder={t('team.invite.email.placeholder')}
                    aria-invalid={!! errors.email}
                    aria-describedby={emailMessage?.id}
                    value={invitee.email}
                    onChange={(event) => onChange('email', event.target.value)}
                    onBlur={() => onBlur('email')}
                    className={CONTROL_SIZE}
                />

                <FieldMessage message={emailMessage} />
            </div>

            <div className={cn(FIELD_CELL, 'md:col-start-3')}>
                <Label htmlFor={`${rowId}-level`} className="md:sr-only">
                    {t('team.invite.level.label')}
                </Label>

                <TeamLevelSelect
                    id={`${rowId}-level`}
                    value={invitee.level}
                    onChange={(level) => onChange('level', level)}
                    invalid={!! errors.level}
                    describedBy={levelMessage?.id}
                />

                <FieldMessage message={levelMessage} />
            </div>
        </div>
    );
}
