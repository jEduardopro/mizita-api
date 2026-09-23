import { cn } from 'cn';
import { useTranslation } from 'react-i18next';
import { FieldMessage, fieldMessage } from '@/components/form/FieldMessage';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { ContactFieldLevel } from '@/domains/businesses/types';
import {
    isContactFieldRequired,
    isContactFieldShown,
    levelForRequiredSwitch,
    levelForShownSwitch,
} from './contact-field-levels';

const ROW_CLASSES = 'grid gap-1 py-1.5';

const ROW_CONTROLS_CLASSES = 'flex flex-wrap items-center gap-x-4';

const TOGGLE_CLASSES = 'flex min-h-11 items-center gap-3 md:min-h-9';

type AlwaysCollectedProps = {
    id: string;
    label: string;
};

export function AlwaysCollectedFieldRow({ id, label }: AlwaysCollectedProps) {
    const { t } = useTranslation('admin');

    const noteId = `${id}-note`;

    return (
        <li className={ROW_CLASSES}>
            <div className={ROW_CONTROLS_CLASSES}>
                <div className={TOGGLE_CLASSES}>
                    <Switch id={id} checked disabled aria-describedby={noteId} />

                    <Label
                        htmlFor={id}
                        className="peer-disabled:cursor-default peer-disabled:opacity-100"
                    >
                        {label}
                    </Label>
                </div>

                <span id={noteId} className="ml-auto text-xs text-muted-foreground">
                    {t('bookingPreferences.contactFields.alwaysRequired')}
                </span>
            </div>
        </li>
    );
}

type ContactFieldRowProps = {
    id: string;
    label: string;
    level: ContactFieldLevel;
    error?: string;
    onLevelChange: (level: ContactFieldLevel) => void;
};

export function ContactFieldRow({ id, label, level, error, onLevelChange }: ContactFieldRowProps) {
    const { t } = useTranslation('admin');

    const requiredId = `${id}-required`;
    const isShown = isContactFieldShown(level);
    const message = fieldMessage({ id, error });

    return (
        <li className={ROW_CLASSES}>
            <div className={ROW_CONTROLS_CLASSES}>
                <div className={TOGGLE_CLASSES}>
                    <Switch
                        id={id}
                        checked={isShown}
                        onCheckedChange={(checked) => onLevelChange(levelForShownSwitch(checked))}
                        aria-invalid={error !== undefined}
                        aria-describedby={message?.id}
                    />

                    <Label htmlFor={id}>{label}</Label>
                </div>

                <div data-disabled={! isShown} className={cn('group ml-auto', TOGGLE_CLASSES)}>
                    <Label htmlFor={requiredId} className="font-normal text-muted-foreground">
                        {t('bookingPreferences.contactFields.required')}
                    </Label>

                    <Switch
                        id={requiredId}
                        checked={isContactFieldRequired(level)}
                        disabled={! isShown}
                        onCheckedChange={(checked) =>
                            onLevelChange(levelForRequiredSwitch(checked))
                        }
                        aria-label={t('bookingPreferences.contactFields.requiredFor', {
                            field: label,
                        })}
                    />
                </div>
            </div>

            <FieldMessage message={message} />
        </li>
    );
}
