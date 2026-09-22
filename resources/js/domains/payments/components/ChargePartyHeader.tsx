import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { initialsFrom } from '@/lib/initials';

type Props = {
    name: string;
};

export function ChargePartyHeader({ name }: Props) {
    return (
        <div className="flex items-center gap-3">
            <Avatar aria-hidden="true">
                <AvatarFallback>{initialsFrom(name)}</AvatarFallback>
            </Avatar>

            <span className="min-w-0 truncate text-sm font-medium">{name}</span>
        </div>
    );
}
