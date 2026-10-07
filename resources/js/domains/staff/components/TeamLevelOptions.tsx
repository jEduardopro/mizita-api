import { useTranslation } from 'react-i18next';
import { SelectContent, SelectItem } from '@/components/ui/select';
import { ASSIGNABLE_STAFF_ROLES } from '../types';
import { ROLE_DESCRIPTION_KEYS, ROLE_LABEL_KEYS } from './profile-role';

type Props = {
    align: 'start' | 'end';
};

export function TeamLevelOptions({ align }: Props) {
    const { t } = useTranslation('admin');

    return (
        <SelectContent
            position="popper"
            align={align}
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
    );
}
