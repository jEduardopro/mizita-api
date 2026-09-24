import { cn } from 'cn';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initialsFrom } from '@/lib/initials';

type Props = {
    name: string;
    photoUrl: string | null;
    className?: string;
};

export function ProfileAvatar({ name, photoUrl, className }: Props) {
    return (
        <Avatar aria-hidden="true" className={cn('size-16 shrink-0', className)}>
            {photoUrl === null ? null : <AvatarImage src={photoUrl} alt="" />}

            <AvatarFallback className="text-base font-semibold">{initialsFrom(name)}</AvatarFallback>
        </Avatar>
    );
}
