import type { ComponentProps } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initialsFrom } from '@/lib/initials';

type Props = {
    name: string;
    photoUrl?: string | null;
    size?: ComponentProps<typeof Avatar>['size'];
};

export function CustomerAvatar({ name, photoUrl, size }: Props) {
    return (
        <Avatar aria-hidden="true" size={size} className="shrink-0">
            {photoUrl ? <AvatarImage src={photoUrl} alt="" /> : null}

            <AvatarFallback>{initialsFrom(name)}</AvatarFallback>
        </Avatar>
    );
}
