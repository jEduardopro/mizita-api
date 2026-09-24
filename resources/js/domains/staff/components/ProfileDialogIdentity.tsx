import { Camera, LoaderCircle } from 'lucide-react';
import { useId, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { ProfileAvatar } from './ProfileAvatar';
import { useProfilePhoto } from './use-profile-photo';

type Props = {
    name: string;
    jobTitle: string | null;
    photoUrl: string | null;
    onUploadPhoto: (photo: File) => Promise<unknown>;
    onRemovePhoto: () => Promise<unknown>;
};

export function ProfileDialogIdentity({ name, jobTitle, photoUrl, onUploadPhoto, onRemovePhoto }: Props) {
    const { t } = useTranslation('admin');
    const inputId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const photo = useProfilePhoto({ onUpload: onUploadPhoto, onRemove: onRemovePhoto });

    function choose(file: File | undefined) {
        if (inputRef.current !== null) {
            inputRef.current.value = '';
        }

        if (file !== undefined) {
            photo.select(file);
        }
    }

    return (
        <div className="flex min-w-0 items-center gap-3 md:flex-col md:gap-2 md:text-center">
            <input
                ref={inputRef}
                id={inputId}
                type="file"
                accept={photo.accept}
                disabled={photo.isBusy}
                aria-label={photoUrl === null ? t('profile.photo.add') : t('profile.photo.change')}
                onChange={(event) => choose(event.target.files?.[0])}
                className="peer sr-only"
            />

            <label
                htmlFor={inputId}
                aria-busy={photo.isBusy}
                className="relative shrink-0 cursor-pointer rounded-full outline-none peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50 peer-disabled:cursor-progress"
            >
                <ProfileAvatar name={name} photoUrl={photoUrl} className="size-12 md:size-16" />

                <span
                    aria-hidden="true"
                    className="absolute -right-0.5 -bottom-0.5 flex size-6 items-center justify-center rounded-full bg-background text-foreground ring-1 ring-border"
                >
                    {photo.isBusy ? (
                        <LoaderCircle className="size-3.5 motion-safe:animate-spin" />
                    ) : (
                        <Camera className="size-3.5" />
                    )}
                </span>
            </label>

            <div className="grid min-w-0 flex-1 justify-items-start md:w-full md:justify-items-center">
                <p className="w-full truncate text-base font-semibold">{name}</p>

                {jobTitle === null ? null : (
                    <p className="w-full truncate text-sm text-muted-foreground">{jobTitle}</p>
                )}

                {photoUrl === null ? null : (
                    <Button
                        type="button"
                        variant="link"
                        onClick={photo.remove}
                        disabled={photo.isBusy}
                        className="relative h-auto p-0 text-xs text-muted-foreground after:absolute after:-inset-x-2 after:-inset-y-3 hover:text-foreground"
                    >
                        {t('profile.photo.remove')}
                    </Button>
                )}
            </div>
        </div>
    );
}
