import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create, edit, index } from '@/routes/users';
import type { User } from '@/types';

type Props = {
    users: User[];
};

function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

export default function UsersIndex({ users }: Props) {
    return (
        <>
            <Head title="Usuários" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Usuários"
                        description="Gerencie quem pode acessar a área restrita do sistema."
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Novo usuário
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="size-5" />
                            Acessos cadastrados
                        </CardTitle>
                        <CardDescription>
                            Usuários criados pelo painel já ficam com e-mail
                            verificado.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-hidden rounded-md border">
                            <div className="hidden grid-cols-[1.2fr_1.4fr_0.7fr_0.7fr_auto] gap-4 border-b bg-muted/50 px-4 py-3 text-xs font-medium text-muted-foreground md:grid">
                                <span>Nome</span>
                                <span>E-mail</span>
                                <span>Status</span>
                                <span>Criado em</span>
                                <span className="text-right">Ações</span>
                            </div>

                            {users.length === 0 ? (
                                <div className="px-4 py-10 text-center text-sm text-muted-foreground">
                                    Nenhum usuário cadastrado.
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {users.map((user) => (
                                        <div
                                            key={user.id}
                                            className="grid gap-3 px-4 py-4 md:grid-cols-[1.2fr_1.4fr_0.7fr_0.7fr_auto] md:items-center md:gap-4"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {user.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground md:hidden">
                                                    {user.email}
                                                </p>
                                            </div>

                                            <p className="hidden truncate text-sm text-muted-foreground md:block">
                                                {user.email}
                                            </p>

                                            <div>
                                                <Badge
                                                    variant={
                                                        user.email_verified_at
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {user.email_verified_at
                                                        ? 'Verificado'
                                                        : 'Pendente'}
                                                </Badge>
                                            </div>

                                            <p className="text-sm text-muted-foreground">
                                                {formatDate(user.created_at)}
                                            </p>

                                            <div className="flex justify-start md:justify-end">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={edit({
                                                            user: user.id,
                                                        })}
                                                    >
                                                        <Pencil className="size-4" />
                                                        Editar
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Usuários',
            href: index(),
        },
    ],
};
