import { CheckCircle2, CircleAlert, CircleX, LoaderCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type AnalysisCheck = {
    id: string;
    label: string;
    status: 'good' | 'improve' | 'problem';
    message: string;
};

export type Analysis = {
    score: number;
    checks: AnalysisCheck[];
    stats: {
        words: number;
        paragraphs: number;
        readingMinutes: number;
        readability: number | null;
    };
    disclaimer: string;
};

export type AnalysisInput = {
    title: string;
    meta_title: string;
    meta_description: string;
    excerpt: string;
    slug: string;
    body: string;
    focus_keyword: string;
    post_id: number | null;
};

/** Analisa o artigo no servidor (App\Services\Seo\ContentAnalyzer), com debounce. */
export function useEditorialAnalysis(input: AnalysisInput) {
    const [analysis, setAnalysis] = useState<Analysis | null>(null);
    const [loading, setLoading] = useState(false);
    const controller = useRef<AbortController | null>(null);
    const payload = JSON.stringify(input);

    useEffect(() => {
        const timer = window.setTimeout(async () => {
            controller.current?.abort();
            controller.current = new AbortController();
            setLoading(true);

            try {
                const csrfToken = document
                    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                    ?.getAttribute('content');
                const response = await fetch('/admin/seo/analisar', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    },
                    body: payload,
                    signal: controller.current.signal,
                });

                if (response.ok) {
                    setAnalysis((await response.json()) as Analysis);
                }
            } catch {
                // Requisição cancelada por uma digitação mais nova, ou rede indisponível: mantém a última análise.
            } finally {
                setLoading(false);
            }
        }, 800);

        return () => window.clearTimeout(timer);
    }, [payload]);

    return { analysis, loading };
}

const icons = {
    good: CheckCircle2,
    improve: CircleAlert,
    problem: CircleX,
};

const iconClasses = {
    good: 'text-emerald-600 dark:text-emerald-400',
    improve: 'text-amber-600 dark:text-amber-400',
    problem: 'text-red-600 dark:text-red-400',
};

export function EditorialAnalysis({
    analysis,
    loading,
}: {
    analysis: Analysis | null;
    loading: boolean;
}) {
    if (!analysis) {
        return (
            <div className="flex items-center gap-2 rounded-lg border p-4 text-sm text-muted-foreground">
                <LoaderCircle className="size-4 animate-spin" />
                Analisando o texto…
            </div>
        );
    }

    const order = { problem: 0, improve: 1, good: 2 };
    const checks = [...analysis.checks].sort(
        (a, b) => order[a.status] - order[b.status],
    );
    const tone =
        analysis.score >= 80
            ? 'text-emerald-700 dark:text-emerald-400'
            : analysis.score >= 50
              ? 'text-amber-700 dark:text-amber-400'
              : 'text-red-700 dark:text-red-400';

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="flex items-center justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold">Análise editorial</p>
                    <p className="text-xs text-muted-foreground">
                        {analysis.stats.words} palavras ·{' '}
                        {analysis.stats.readingMinutes} min de leitura
                        {analysis.stats.readability !== null &&
                            ` · legibilidade ${analysis.stats.readability}`}
                    </p>
                </div>
                <div className="text-right">
                    <span
                        className={cn(
                            'text-3xl font-semibold tabular-nums',
                            tone,
                        )}
                    >
                        {analysis.score}
                    </span>
                    <span className="text-sm text-muted-foreground">/100</span>
                    {loading && (
                        <LoaderCircle className="ml-2 inline size-3 animate-spin text-muted-foreground" />
                    )}
                </div>
            </div>
            <ul className="space-y-2">
                {checks.map((check) => {
                    const Icon = icons[check.status];

                    return (
                        <li key={check.id} className="flex gap-2 text-sm">
                            <Icon
                                className={cn(
                                    'mt-0.5 size-4 shrink-0',
                                    iconClasses[check.status],
                                )}
                            />
                            <span>
                                <span className="font-medium">
                                    {check.label}:
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    {check.message}
                                </span>
                            </span>
                        </li>
                    );
                })}
            </ul>
            <p className="text-xs text-muted-foreground">
                {analysis.disclaimer}
            </p>
        </div>
    );
}
