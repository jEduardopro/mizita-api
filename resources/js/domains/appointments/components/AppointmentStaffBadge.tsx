import { Avatar, AvatarFallback } from '@/components/ui/avatar';

const MAX_INITIALS = 2;

type Props = {
    name: string;
};

function initialsFrom(name: string): string {
    return name
        .split(/\s+/)
        .filter((word) => word !== '')
        .slice(0, MAX_INITIALS)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');
}

export function AppointmentStaffBadge({ name }: Props) {
    return (
        <span className="flex min-w-0 items-center gap-2 sm:max-w-44">
            <Avatar size="sm">
                <AvatarFallback>{initialsFrom(name)}</AvatarFallback>
            </Avatar>

            <span className="min-w-0 truncate text-xs text-muted-foreground">{name}</span>
        </span>
    );
}
