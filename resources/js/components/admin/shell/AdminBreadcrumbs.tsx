import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb as BreadcrumbRoot,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';

export type Breadcrumb = {
    label: string;
    href?: string;
};

type Props = {
    title: string;
    breadcrumbs: Breadcrumb[];
};

export function AdminBreadcrumbs({ title, breadcrumbs }: Props) {
    return (
        <>
            <h1 className="sr-only">{title}</h1>

            <BreadcrumbRoot className="min-w-0">
                <BreadcrumbList className="flex-nowrap gap-1">
                    {breadcrumbs.map((crumb, index) => (
                        <Fragment key={crumb.href ?? crumb.label}>
                            {index > 0 ? (
                                <BreadcrumbSeparator className="shrink-0 text-muted-foreground" />
                            ) : null}

                            <BreadcrumbItem
                                className={
                                    crumb.href === undefined
                                        ? 'min-w-0 shrink'
                                        : 'min-w-0 shrink-[999]'
                                }
                            >
                                {crumb.href === undefined ? (
                                    <BreadcrumbPage className="truncate font-medium">
                                        {crumb.label}
                                    </BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink
                                        asChild
                                        className="truncate rounded-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                                    >
                                        <Link href={crumb.href}>{crumb.label}</Link>
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                        </Fragment>
                    ))}
                </BreadcrumbList>
            </BreadcrumbRoot>
        </>
    );
}
