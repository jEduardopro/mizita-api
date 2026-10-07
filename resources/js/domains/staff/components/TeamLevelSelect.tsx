import { useTranslation } from 'react-i18next';
import { Select, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { AssignableStaffRole } from '../types';
import { assignableStaffRoleFrom, ROLE_LABEL_KEYS } from './profile-role';
import { TeamLevelOptions } from './TeamLevelOptions';

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
        const level = assignableStaffRoleFrom(next);

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

            <TeamLevelOptions align="end" />
        </Select>
    );
}
