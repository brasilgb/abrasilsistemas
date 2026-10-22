import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    FieldError,
    Notice,
    fieldClass,
    formatDateTime,
} from '@/components/seo/indicators';
import { SeoPage } from '@/components/seo/seo-page';
import { Button } from '@/components/ui/button';

type Redirect = {
    id: number;
    source_path: string;
    target_url: string | null;
    status_code: number;
    is_active: boolean;
    notes: string | null;
    hits: number;
    last_hit_at: string | null;
};

type HistoryEntry = {
    id: number;
    action: 'created' | 'updated' | 'deleted';
    changes: Record<string, unknown> | null;
    created_at: string;
    user: string | null;
};

type Props = {
    redirect: Redirect | null;
    initialSource: string | null;
    statusCodes: Record<string, string>;
    history: HistoryEntry[];
};

const actionLabels: Record<HistoryEntry['action'], string> = {
    created: 'Criado',
    updated: 'Alterado',
    deleted: 'Excluído',
};

const describe = (value: unknown) =>
    value === null || value === undefined || value === ''
        ? '—'
        : typeof value === 'boolean'
          ? value
              ? 'sim'
              : 'não'
          : String(value);

export default function SeoRedirectForm({
    redirect,
    initialSource,
    statusCodes,
    history,
}: Props) {
    const form = useForm({
        source_path: redirect?.source_path ?? initialSource ?? '',
        target_url: redirect?.target_url ?? '',
        status_code: redirect?.status_code ?? 301,
        is_active: redirect?.is_active ?? true,
        notes: redirect?.notes ?? '',
    });
    const gone = Number(form.data.status_code) === 410;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            target_url: gone ? '' : data.target_url,
        }));

        if (redirect) {
            form.put(`/admin/seo/redirecionamentos/${redirect.id}`);
        } else {
            form.post('/admin/seo/redirecionamentos');
        }
    };

    return (
        <SeoPage
            title={
                redirect ? 'Editar redirecionamento' : 'Novo redirecionamento'
            }
            description="A origem é um caminho do próprio site. O destino pode ser outro caminho (/nova-pagina) ou uma URL http(s) completa."
        >
            {redirect && (
                <Notice>
                    {redirect.hits} {redirect.hits === 1 ? 'acesso' : 'acessos'}{' '}
                    registrados
                    {redirect.last_hit_at &&
                        ` · último em ${formatDateTime(redirect.last_hit_at)}`}
                    . Se a URL antiga ainda recebe acessos, prefira desativar a
                    excluir.
                </Notice>
            )}
            <form onSubmit={submit} className="grid max-w-3xl gap-5">
                <label className="text-sm font-medium">
                    Origem
                    <input
                        className={`${fieldClass} font-mono`}
                        value={form.data.source_path}
                        placeholder="/caminho-antigo"
                        maxLength={512}
                        required
                        onChange={(e) =>
                            form.setData('source_path', e.target.value)
                        }
                    />
                    <span className="mt-1 block text-xs font-normal text-muted-foreground">
                        Sem domínio e sem query string. Áreas privadas (/admin,
                        /login, /api…) não podem ser redirecionadas.
                    </span>
                    <FieldError text={form.errors.source_path} />
                </label>
                <label className="text-sm font-medium">
                    Tipo
                    <select
                        className={fieldClass}
                        value={form.data.status_code}
                        onChange={(e) =>
                            form.setData('status_code', Number(e.target.value))
                        }
                    >
                        {Object.entries(statusCodes).map(([code, label]) => (
                            <option key={code} value={code}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <FieldError text={form.errors.status_code} />
                </label>
                {!gone && (
                    <label className="text-sm font-medium">
                        Destino
                        <input
                            className={`${fieldClass} font-mono`}
                            value={form.data.target_url}
                            placeholder="/nova-pagina ou https://…"
                            maxLength={2048}
                            required
                            onChange={(e) =>
                                form.setData('target_url', e.target.value)
                            }
                        />
                        <FieldError text={form.errors.target_url} />
                    </label>
                )}
                {gone && (
                    <Notice tone="warn">
                        A origem passa a responder 410 (removida de propósito).
                        Use quando não houver conteúdo substituto; havendo,
                        prefira um 301.
                    </Notice>
                )}
                <label className="text-sm font-medium">
                    Observação
                    <textarea
                        className={fieldClass}
                        rows={2}
                        maxLength={500}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                    <FieldError text={form.errors.notes} />
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(e) =>
                            form.setData('is_active', e.target.checked)
                        }
                    />
                    Ativo
                </label>
                <div className="flex justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/admin/seo/redirecionamentos">Voltar</Link>
                    </Button>
                    <Button disabled={form.processing}>Salvar</Button>
                </div>
            </form>

            {history.length > 0 && (
                <section className="max-w-3xl space-y-2">
                    <h2 className="text-sm font-semibold">
                        Registro de alterações
                    </h2>
                    <ul className="divide-y rounded-md border text-sm">
                        {history.map((entry) => (
                            <li key={entry.id} className="space-y-1 px-4 py-2">
                                <span className="block text-xs text-muted-foreground">
                                    {actionLabels[entry.action] ?? entry.action}{' '}
                                    em {formatDateTime(entry.created_at)}
                                    {entry.user && ` por ${entry.user}`}
                                </span>
                                {entry.changes &&
                                    Object.entries(entry.changes).map(
                                        ([field, change]) => {
                                            const diff =
                                                change &&
                                                typeof change === 'object' &&
                                                'to' in change
                                                    ? (change as {
                                                          from: unknown;
                                                          to: unknown;
                                                      })
                                                    : null;

                                            return (
                                                <span
                                                    key={field}
                                                    className="block font-mono text-xs break-all"
                                                >
                                                    {field}:{' '}
                                                    {diff
                                                        ? `${describe(diff.from)} → ${describe(diff.to)}`
                                                        : describe(change)}
                                                </span>
                                            );
                                        },
                                    )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </SeoPage>
    );
}
