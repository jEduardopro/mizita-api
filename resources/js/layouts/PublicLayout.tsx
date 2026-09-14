import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { BENEFITS_ID } from '@/components/public/landing/BenefitGrid';
import { HOW_IT_WORKS_ID } from '@/components/public/landing/HowItWorks';
import { PRICING_ID } from '@/components/public/landing/PricingPlans';
import { SectionNav, type Section } from '@/components/public/shell/SectionNav';
import {
    FacebookIcon,
    InstagramIcon,
    LinkedinIcon,
    TwitterIcon,
    YoutubeIcon,
    type SocialIconProps,
} from '@/components/public/shell/SocialIcons';
import { Wordmark } from '@/components/shared/Wordmark';
import { Button } from '@/components/ui/button';
import { legalDocuments } from '@/content/legal/entity';

/** PLACEHOLDER: every `href` is `#` until the accounts exist. */
const socialProfiles: { network: string; href: string; Icon: (props: SocialIconProps) => ReactNode }[] = [
    { network: 'Facebook', href: '#', Icon: FacebookIcon },
    { network: 'Instagram', href: '#', Icon: InstagramIcon },
    { network: 'X', href: '#', Icon: TwitterIcon },
    { network: 'LinkedIn', href: '#', Icon: LinkedinIcon },
    { network: 'YouTube', href: '#', Icon: YoutubeIcon },
];

/**
 * The product anchors are absolute paths so the footer works from every public
 * page: on the landing page the browser treats `/#pricing` as a fragment jump,
 * and from anywhere else it goes home and lands on the section.
 *
 * PLACEHOLDER: the company destinations are `#` until those routes exist.
 */
const footerMenu = [
    {
        id: 'product',
        title: 'footer.product.title',
        links: [
            { label: 'footer.product.howItWorks', href: `/#${HOW_IT_WORKS_ID}` },
            { label: 'footer.product.benefits', href: `/#${BENEFITS_ID}` },
            { label: 'footer.product.pricing', href: `/#${PRICING_ID}` },
        ],
    },
    {
        id: 'company',
        title: 'footer.company.title',
        links: [
            { label: 'footer.company.about', href: '#' },
            { label: 'footer.company.contact', href: '#' },
            { label: 'footer.company.help', href: '#' },
        ],
    },
    {
        id: 'legal',
        title: 'footer.legal.title',
        links: [
            { label: 'footer.legal.privacy', href: legalDocuments.privacy.path },
            { label: 'footer.legal.terms', href: legalDocuments.terms.path },
            { label: 'footer.legal.cookies', href: legalDocuments.cookies.path },
        ],
    },
] as const;

type Props = {
    /** The tab title. The app name is appended by the title callback in app.tsx. */
    title?: string;
    /** An in-page menu, for a page long enough to need one. */
    sections?: Section[];
    children: ReactNode;
};

export function PublicLayout({ title, sections, children }: Props) {
    const { name } = usePage().props;
    const { t } = useTranslation('common');

    const year = new Date().getFullYear();

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title={title} />

            <header className="sticky top-0 z-50 border-b border-border bg-background/90 backdrop-blur-md supports-[backdrop-filter]:bg-background/70">
                <div className="mx-auto flex h-14 w-full max-w-5xl items-center justify-between gap-3 px-5 sm:px-8">
                    <Wordmark name={name} size="lg" />

                    {sections === undefined ? null : <SectionNav sections={sections} />}

                    {/* Unconditional: the public surface makes the same offer to
                        everyone, session or not. */}
                    <nav aria-label={t('nav.account')} className="flex items-center gap-1.5">
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/login">{t('nav.logIn')}</Link>
                        </Button>
                        <Button asChild variant="brand" size="sm">
                            <Link href="/register">{t('nav.createAccount')}</Link>
                        </Button>
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="bg-surface-muted">
                <div className="mx-auto w-full max-w-5xl px-5 py-12 sm:px-8 sm:py-14">
                    <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr] lg:gap-8">
                        <div>
                            <Wordmark name={name} size="xl" />

                            <p className="mt-4 max-w-xs text-sm leading-relaxed text-muted-foreground">
                                {t('footer.tagline', { name })}
                            </p>
                        </div>

                        {footerMenu.map((column) => (
                            <nav key={column.id} aria-labelledby={`footer-${column.id}`}>
                                <h2
                                    id={`footer-${column.id}`}
                                    className="text-[0.6875rem] font-medium tracking-[0.14em] text-foreground uppercase"
                                >
                                    {t(column.title)}
                                </h2>

                                <ul className="mt-4 space-y-2.5">
                                    {column.links.map((link) => (
                                        <li key={link.label}>
                                            <a
                                                href={link.href}
                                                className="rounded-sm text-sm text-muted-foreground transition-colors outline-none hover:text-primary focus-visible:ring-3 focus-visible:ring-ring/50"
                                            >
                                                {t(link.label)}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                        ))}
                    </div>

                    <div className="mt-12 flex flex-col gap-5 border-t border-border pt-6 sm:flex-row-reverse sm:items-center sm:justify-between">
                        {/* Icon links carry no text, so each is named by its
                            `aria-label` and reads as a whole sentence. */}
                        <ul aria-label={t('footer.social.title')} className="flex items-center gap-1">
                            {socialProfiles.map(({ network, href, Icon }) => (
                                <li key={network}>
                                    <a
                                        href={href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={t('footer.social.followOn', { name, network })}
                                        className="flex size-9 items-center justify-center rounded-lg text-muted-foreground transition-colors outline-none hover:bg-brand-50 hover:text-primary focus-visible:ring-3 focus-visible:ring-ring/50 dark:hover:bg-brand-950/60"
                                    >
                                        <Icon className="size-[1.125rem]" />
                                    </a>
                                </li>
                            ))}
                        </ul>

                        <p className="text-xs text-muted-foreground">
                            {t('footer.copyright', { year, name })}
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    );
}
