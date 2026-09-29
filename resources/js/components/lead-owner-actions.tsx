import { router, usePage } from '@inertiajs/react';
import { UserCheck } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { assignToMe, bulk } from '@/routes/leads';
import type { User } from '@/types';

type Owner = { id: number; name: string } | null | undefined;

const selectClass =
    'flex h-8 rounded-md border border-input bg-background px-2 py-1 text-xs shadow-xs ring-offset-background transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none';

/** Responsável como a equipe lê: "Você", o nome de outro usuário ou "Não definido". */
export function useOwnerLabel() {
    const { auth } = usePage<{ auth: { user: User } }>().props;

    return (owner: Owner) => {
        if (!owner) {
            return 'Não definido';
        }

        return owner.id === auth.user.id ? 'Você' : owner.name;
    };
}

/**
 * "Assumir atendimento" sem modal: o backend só atribui se o lead ainda estiver sem responsável.
 * Na fila o estado é preservado (filtros, página, rolagem); na tela do lead, preserveState=false
 * remonta o formulário para o campo Responsável refletir a atribuição.
 */
export function AssignToMeButton({
    leadId,
    preserveState = true,
}: {
    leadId: number;
    preserveState?: boolean;
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <Button
            type="button"
            size="sm"
            disabled={processing}
            onClick={() =>
                router.patch(
                    assignToMe.url({ lead: leadId }),
                    {},
                    {
                        preserveScroll: true,
                        preserveState,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                    },
                )
            }
        >
            <UserCheck className="size-4" />
            Assumir atendimento
        </Button>
    );
}

/** Transferência explícita: reaproveita a reatribuição em lote (leads.bulk) com um único lead. */
export function TransferOwnerSelect({
    leadId,
    owner,
    users,
}: {
    leadId: number;
    owner: Owner;
    users: { id: number; name: string }[];
}) {
    const [processing, setProcessing] = useState(false);
    const candidates = users.filter((user) => user.id !== owner?.id);

    if (candidates.length === 0) {
        return null;
    }

    return (
        <select
            value=""
            disabled={processing}
            aria-label={
                owner ? 'Transferir atendimento' : 'Atribuir atendimento'
            }
            className={selectClass}
            onChange={(event) => {
                const target = candidates.find(
                    (user) => String(user.id) === event.target.value,
                );

                if (
                    !target ||
                    !window.confirm(
                        `Transferir o atendimento para ${target.name}?`,
                    )
                ) {
                    return;
                }

                router.patch(
                    bulk.url(),
                    { ids: [leadId], user_id: target.id },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                    },
                );
            }}
        >
            <option value="">
                {owner ? 'Transferir para…' : 'Atribuir a…'}
            </option>
            {candidates.map((user) => (
                <option key={user.id} value={user.id}>
                    {user.name}
                </option>
            ))}
        </select>
    );
}
