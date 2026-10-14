import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

export function Container({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 ${className}`}
        >
            {children}
        </div>
    );
}

export function Eyebrow({
    children,
    inverse = false,
}: {
    children: ReactNode;
    inverse?: boolean;
}) {
    return (
        <p
            className={`text-xs font-semibold tracking-[0.14em] uppercase ${inverse ? 'text-sky-300' : 'text-blue-700'}`}
        >
            {children}
        </p>
    );
}

export function SectionHeading({
    eyebrow,
    title,
    description,
    inverse = false,
    align = 'left',
    as: Tag = 'h2',
}: {
    eyebrow?: string;
    title: string;
    description?: string;
    inverse?: boolean;
    align?: 'left' | 'center';
    as?: 'h1' | 'h2';
}) {
    return (
        <div
            className={`max-w-3xl ${align === 'center' ? 'mx-auto text-center' : ''}`}
        >
            {eyebrow && <Eyebrow inverse={inverse}>{eyebrow}</Eyebrow>}
            <Tag
                className={`mt-3 text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl ${inverse ? 'text-white' : 'text-slate-900'}`}
            >
                {title}
            </Tag>
            {description && (
                <p
                    className={`mt-4 text-base leading-7 sm:text-lg sm:leading-8 ${inverse ? 'text-slate-300' : 'text-slate-600'}`}
                >
                    {description}
                </p>
            )}
        </div>
    );
}

type ButtonVariant = 'primary' | 'secondary' | 'inverse' | 'ghostInverse';

const buttonClasses: Record<ButtonVariant, string> = {
    primary: 'bg-blue-700 text-white hover:bg-blue-800',
    secondary:
        'border border-slate-300 bg-white text-slate-900 hover:border-slate-400 hover:bg-slate-50',
    inverse: 'bg-white text-slate-900 hover:bg-slate-200',
    ghostInverse: 'border border-white/25 text-white hover:bg-white/10',
};

/** Botão-link. Links externos (http) abrem em nova aba. */
export function ButtonLink({
    href,
    children,
    variant = 'primary',
    arrow = false,
}: {
    href: string;
    children: ReactNode;
    variant?: ButtonVariant;
    arrow?: boolean;
}) {
    const className = `inline-flex h-11 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold transition ${buttonClasses[variant]}`;
    const content = (
        <>
            {children}
            {arrow && <ArrowRight className="size-4" />}
        </>
    );

    if (href.startsWith('http')) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                className={className}
            >
                {content}
            </a>
        );
    }

    if (href.startsWith('#')) {
        return (
            <a href={href} className={className}>
                {content}
            </a>
        );
    }

    return (
        <Link href={href} className={className}>
            {content}
        </Link>
    );
}

/** Hero escuro das páginas internas: texto à esquerda, visual explicativo à direita. */
export function PageHero({
    eyebrow,
    title,
    lead,
    actions,
    visual,
}: {
    eyebrow: string;
    title: string;
    lead: string;
    actions?: ReactNode;
    visual?: ReactNode;
}) {
    return (
        <section className="bg-[#0b1220] pt-28 pb-16 text-white sm:pt-32 sm:pb-20 lg:pb-24">
            <Container
                className={`grid items-center gap-12 ${visual ? 'lg:grid-cols-[1fr_1.05fr]' : ''}`}
            >
                <div className="max-w-2xl">
                    <Eyebrow inverse>{eyebrow}</Eyebrow>
                    <h1 className="mt-4 text-4xl leading-[1.1] font-semibold tracking-tight text-balance sm:text-5xl">
                        {title}
                    </h1>
                    <p className="mt-6 text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                        {lead}
                    </p>
                    {actions && (
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            {actions}
                        </div>
                    )}
                </div>
                {visual && <div className="min-w-0">{visual}</div>}
            </Container>
        </section>
    );
}

/** Etapas numeradas em linha (desktop) ou lista (celular). */
export function ProcessSteps({
    steps,
    inverse = false,
}: {
    steps: readonly { title: string; description: string }[];
    inverse?: boolean;
}) {
    const columns =
        steps.length > 6
            ? 'lg:grid-cols-4 xl:grid-cols-7'
            : 'lg:grid-cols-3 xl:grid-cols-6';

    return (
        <ol className={`grid gap-x-6 gap-y-8 sm:grid-cols-2 ${columns}`}>
            {steps.map((step, index) => (
                <li
                    key={step.title}
                    className={`border-t pt-5 ${inverse ? 'border-white/15' : 'border-slate-300'}`}
                >
                    <span
                        className={`text-sm font-semibold tabular-nums ${inverse ? 'text-sky-300' : 'text-blue-700'}`}
                    >
                        {String(index + 1).padStart(2, '0')}
                    </span>
                    <h3
                        className={`mt-2 text-base font-semibold ${inverse ? 'text-white' : 'text-slate-900'}`}
                    >
                        {step.title}
                    </h3>
                    <p
                        className={`mt-2 text-sm leading-6 ${inverse ? 'text-slate-400' : 'text-slate-600'}`}
                    >
                        {step.description}
                    </p>
                </li>
            ))}
        </ol>
    );
}

/** Fluxo de dados em cadeia (A → B → C), usado para explicar integrações e automações. */
export function FlowDiagram({
    title,
    steps,
}: {
    title: string;
    steps: string[];
}) {
    return (
        <figure className="rounded-lg border border-slate-200 bg-white p-5 sm:p-6">
            <figcaption className="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                {title}
            </figcaption>
            <ol className="mt-4 flex flex-col gap-2 md:flex-row md:flex-wrap md:items-center">
                {steps.map((step, index) => (
                    <li
                        key={step}
                        className="flex items-center gap-2 md:contents"
                    >
                        <span className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800">
                            {step}
                        </span>
                        {index < steps.length - 1 && (
                            <ArrowRight
                                className="size-4 shrink-0 rotate-90 text-blue-700 md:rotate-0"
                                aria-hidden="true"
                            />
                        )}
                    </li>
                ))}
            </ol>
        </figure>
    );
}

export function FaqList({
    items,
}: {
    items: { question: string; answer: string }[];
}) {
    const [openIndex, setOpenIndex] = useState<number | null>(0);

    return (
        <div className="divide-y divide-slate-200 border-y border-slate-200">
            {items.map((item, index) => {
                const isOpen = openIndex === index;

                return (
                    <Collapsible
                        key={item.question}
                        open={isOpen}
                        onOpenChange={(value) =>
                            setOpenIndex(value ? index : null)
                        }
                    >
                        <CollapsibleTrigger className="flex w-full items-center justify-between gap-4 py-5 text-left text-base font-semibold text-slate-900">
                            {item.question}
                            <ChevronDown
                                className={`size-4 shrink-0 text-slate-500 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="pb-5 text-[15px] leading-7 text-slate-600">
                            {item.answer}
                        </CollapsibleContent>
                    </Collapsible>
                );
            })}
        </div>
    );
}

/** Faixa final de chamada para contato. */
export function CtaBand({
    title,
    description,
    primary,
    secondary,
}: {
    title: string;
    description: string;
    primary: { label: string; href: string };
    secondary?: { label: string; href: string };
}) {
    return (
        <section className="bg-[#0b1220] py-20 text-white sm:py-24">
            <Container className="flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-center">
                <div className="max-w-2xl">
                    <h2 className="text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl">
                        {title}
                    </h2>
                    <p className="mt-4 text-base leading-7 text-slate-300 sm:text-lg">
                        {description}
                    </p>
                </div>
                <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                    <ButtonLink href={primary.href} variant="inverse" arrow>
                        {primary.label}
                    </ButtonLink>
                    {secondary && (
                        <ButtonLink
                            href={secondary.href}
                            variant="ghostInverse"
                        >
                            {secondary.label}
                        </ButtonLink>
                    )}
                </div>
            </Container>
        </section>
    );
}
