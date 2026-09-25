import { useTranslation } from 'react-i18next';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ASSIGNABLE_STAFF_ROLES, type AssignableStaffRole } from '../types';
import { ROLE_DESCRIPTION_KEYS, ROLE_LABEL_KEYS } from './profile-role';

type Props = {
    id: string;
    value: AssignableStaffRole;
    onChange: (level: AssignableStaffRole) => void;
    invalid?: boolean;
    describedBy?: string;
};

export function TeamLevelSelect({ id, value, onChange, invalid, describedBy }: Props) {
    const { t } = useTranslation('admin');

    function select(next: string) {
        const level = ASSIGNABLE_STAFF_ROLES.find((assignable) => assignable === next);

        if (level !== undefined) {
            onChange(level);
        }
    }

    return (
        <Select value={value} onValueChange={select}>
            <SelectTrigger
                id={id}
                aria-invalid={invalid}
                aria-describedby={describedBy}
                className="w-full text-base data-[size=default]:h-11 md:text-sm md:data-[size=default]:h-9"
            >
                <SelectValue>{t(ROLE_LABEL_KEYS[value])}</SelectValue>
            </SelectTrigger>

            <SelectContent
                position="popper"
                align="end"
                className="w-(--radix-select-trigger-width) max-w-[calc(100vw-2rem)] min-w-72"
            >
                {ASSIGNABLE_STAFF_ROLES.map((level) => (
                    <SelectItem key={level} value={level} className="min-h-11 py-2">
                        <span className="grid gap-0.5 text-left">
                            <span className="font-medium">{t(ROLE_LABEL_KEYS[level])}</span>

                            <span className="text-xs text-pretty text-muted-foreground">
                                {t(ROLE_DESCRIPTION_KEYS[level])}
                            </span>
                        </span>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
