import { ChevronDown } from "lucide-react";
import { useTranslation } from "react-i18next";
import type { LegalSection } from "@/components/public/legal/legal-content";

type ListProps = {
    sections: LegalSection[];
};

/**
 * The clause list itself.
 *
 * Numbered because the documents number themselves: a clause is cited by its
 * number, so the number is content and it is set in its own column, aligned on
 * tabular figures, rather than folded into the label.
 *
 * The rows are thumb-sized below `lg` and compact above it. One set of classes
 * covers both, because each shape is only ever visible at one width.
 */
function ClauseList({ sections }: ListProps) {
    return (
        <ol className="border-l border-border">
            {sections.map((section) => (
                <li key={section.id}>
                    <a
                        href={`#${section.id}`}
                        className="-ml-px flex gap-2.5 border-l border-transparent py-3 pl-3.5 text-sm leading-snug text-muted-foreground transition-colors outline-none hover:border-primary hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 lg:py-1.5 lg:text-[0.8125rem]"
                    >
                        {section.ordinal === null ? null : (
                            <span
                                aria-hidden="true"
                                className="w-4 shrink-0 text-right tabular-nums"
                            >
                                {section.ordinal}
                            </span>
                        )}
                        <span>{section.label}</span>
                    </a>
                </li>
            ))}
        </ol>
    );
}

type Props = {
    sections: LegalSection[];
};

/**
 * The index of a legal document, in the two shapes it needs.
 *
 * On a laptop it is a rail beside the text, sticky so the clause you are looking
 * for stays one click away however far down the document you have read. On a
 * phone there is no room beside anything, so it collapses above the text into a
 * native `details` — no state, no JavaScript, keyboard operable by construction,
 * and nothing that can hold focus once it is closed.
 *
 * Both shapes render the same list, and only one of them is ever displayed.
 */
export function LegalToc({ sections }: Props) {
    const { t } = useTranslation("common");

    if (sections.length === 0) {
        return null;
    }

    const label = t("legal.tableOfContents");

    return (
        <div className="lg:sticky lg:top-20 lg:max-h-[calc(100svh-7rem)] lg:overflow-y-auto lg:overscroll-contain">
            <nav aria-label={label} className="lg:hidden">
                <details className="group rounded-xl border border-border bg-surface-muted">
                    <summary className="flex list-none items-center justify-between gap-3 px-4 py-3 text-sm font-medium outline-none select-none focus-visible:ring-3 focus-visible:ring-ring/50 [&::-webkit-details-marker]:hidden">
                        {label}
                        <ChevronDown
                            aria-hidden="true"
                            className="size-4 text-muted-foreground transition-transform group-open:rotate-180 motion-reduce:transition-none"
                        />
                    </summary>

                    <div className="border-t border-border px-4 py-3">
                        <ClauseList sections={sections} />
                    </div>
                </details>
            </nav>

            <nav aria-label={label} className="hidden lg:block">
                {/*
                 * An eyebrow rather than a heading: the nav is already named by
                 * its label, and a heading here would sit in the document
                 * outline between the title and the first clause.
                 */}
                <p className="mb-3 pl-3.5 text-[0.6875rem] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                    {label}
                </p>

                <ClauseList sections={sections} />
            </nav>
        </div>
    );
}
