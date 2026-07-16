import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/users';
import type { User } from '@/types';

type Props = {
    managedUser: User;
    passwordRules: string;
};

export default function EditUser({ managedUser, passwordRules }: Props) {
    return (
        <>
            <Head title={`Editar ${managedUser.name}`} />

            <div className="max-w-2xl space-y-6 p-4">
                <Heading
                    title="Editar usuário"
                    description="Atualize os dados de acesso deste usuário."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{managedUser.name}</CardTitle>
                        <CardDescription>
                            Deixe a senha em branco para manter a senha atual.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...UserController.update.form({
                                user: managedUser.id,
                            })}
                            resetOnSuccess={[
                                'password',
                                'password_confirmation',
                            ]}
                            disableWhileProcessing
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Nome</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            autoFocus
                                            autoComplete="name"
                                            defaultValue={managedUser.name}
                                            placeholder="Nome completo"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">E-mail</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoComplete="email"
                                            defaultValue={managedUser.email}
                                            placeholder="email@exemplo.com"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            Nova senha
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            autoComplete="new-password"
                                            placeholder="Manter senha atual"
                                            passwordrules={passwordRules}
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">
                                            Confirmar nova senha
                                        </Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            autoComplete="new-password"
                                            placeholder="Confirmar nova senha"
                                            passwordrules={passwordRules}
                                        />
                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>

                                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                        <Button asChild variant="outline">
                                            <Link href={index()}>Cancelar</Link>
                                        </Button>
                                        <Button disabled={processing}>
                                            {processing && <Spinner />}
                                            Salvar alterações
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [
        {
            title: 'Usuários',
            href: index(),
        },
        {
            title: 'Editar usuário',
            href: index(),
        },
    ],
};
