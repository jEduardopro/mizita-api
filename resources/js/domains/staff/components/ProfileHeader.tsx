import { Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { ProfileAvatar } from './ProfileAvatar';

type Props = {
    name: string;
    jobTitle: string | null;
    photoUrl: string | null;
    onEdit?: () => void;
    actions?: ReactNode;
};

export function ProfileHeader({ name, jobTitle, photoUrl, onEdit, actions }: Props) {
    const { t } = useTranslation('admin');

    const hasControls = onEdit !== undefined || actions !== undefined;

    return (
        <header className="flex items-start gap-4">
            <ProfileAvatar name={name} photoUrl={photoUrl} className="size-14 sm:size-16" />

            <div className="grid min-w-0 flex-1 gap-1 pt-1.5 sm:pt-2.5">
                <h2 className="text-xl font-semibold tracking-tight text-balance break-words">{name}</h2>

                {jobTitle === null ? null : (
                    <p className="text-sm text-pretty break-words text-muted-foreground">{jobTitle}</p>
                )}
            </div>

            {hasControls ? (
                <div className="-mr-2 flex shrink-0 items-center">
                    {onEdit === undefined ? null : (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onEdit}
                            aria-label={t('profile.edit')}
                            className="size-11 shrink-0 p-0 text-muted-foreground hover:text-foreground"
                        >
                            <Pencil aria-hidden="true" />
                        </Button>
                    )}

                    {actions}
                </div>
            ) : null}
        </header>
    );
}
