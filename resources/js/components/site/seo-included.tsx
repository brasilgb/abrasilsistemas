import { Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import { Container, SectionHeading } from '@/components/site/blocks';
import { includedSeo } from '@/data/site';

/**
 * Bloco "SEO técnico incluído": o que todo projeto web público já entrega, separado do
 * SEO profissional (conteúdo, monitoramento e consultoria), que é contratado à parte.
 */
export function SeoIncluded({ className = '' }: { className?: string }) {
    return (
        <section className={`py-20 sm:py-24 ${className}`}>
            <Container className="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20">
                <div>
                    <SectionHeading
                        eyebrow="Incluído no projeto"
                        title={includedSeo.title}
                        description={includedSeo.description}
                    />
                    <p className="mt-6 border-l-2 border-blue-700 pl-4 text-[15px] leading-7 text-slate-600">
                        {includedSeo.privacy}
                    </p>
                </div>
                <div>
                    <ul className="grid gap-x-8 sm:grid-cols-2">
                        {includedSeo.items.map((item) => (
                            <li
                                key={item}
                                className="flex gap-3 border-t border-slate-200 py-3.5 text-[15px] text-slate-800"
                            >
                                <Check
                                    className="mt-0.5 size-4 shrink-0 text-blue-700"
                                    aria-hidden="true"
                                />
                                {item}
                            </li>
                        ))}
                    </ul>
                    <p className="mt-6 text-sm leading-6 text-slate-500">
                        {includedSeo.notIncluded}{' '}
                        <Link
                            href="/seo-tecnico-e-otimizacao-de-sites"
                            className="inline-flex items-center gap-1 font-semibold text-blue-700 hover:text-blue-800"
                        >
                            Conhecer o SEO profissional
                            <ArrowRight className="size-3.5" />
                        </Link>
                    </p>
                </div>
            </Container>
        </section>
    );
}
