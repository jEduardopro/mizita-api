import { useTranslation } from 'react-i18next';
import { fieldMessage, FieldMessage } from '@/components/form/FieldMessage';
import { Label } from '@/components/ui/label';
import type { AssignableStaffRole } from '../types';
import { PROFILE_SECTION_DIVIDED, PROFILE_SECTION_HEADING } from './profile-section';
import { TeamLevelSelect } from './TeamLevelSelect';

const LEVEL_FIELD_ID = 'profile-level';

type Props = {
    value: AssignableStaffRole;
    onChange: (level: AssignableStaffRole) => void;
    error: string | undefined;
};

export function ProfileLevelSection({ value, onChange, error }: Props) {
    const { t } = useTranslation('admin');

    const levelMessage = fieldMessage({ id: LEVEL_FIELD_ID, error });

    return (
        <section className={PROFILE_SECTION_DIVIDED}>
            <Label htmlFor={LEVEL_FIELD_ID} className={PROFILE_SECTION_HEADING}>
                {t('profile.about.role')}
            </Label>

            <TeamLevelSelect
                id={LEVEL_FIELD_ID}
                value={value}
                onChange={onChange}
                invalid={error !== undefined}
                describedBy={levelMessage?.id}
            />

            <FieldMessage message={levelMessage} />
        </section>
    );
}
