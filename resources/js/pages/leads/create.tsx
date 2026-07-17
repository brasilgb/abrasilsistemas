import { Form, Head, Link } from '@inertiajs/react';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create, index } from '@/routes/leads';
import LeadForm from '@/pages/leads/form';

type Props = {
    products: Record<string, string>;
    statuses: Record<string, string>;
};

export default function CreateLead({ products, statuses }: Props) {
    return (
        <>
            <Head title="Novo lead" />

            <div className="max-w-4xl space-y-6 p-4">
                <Heading
                    title="Novo lead"
                    description="Cadastre uma empresa para acompanhar no funil comercial."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Dados do lead</CardTitle>
                        <CardDescription>
                            Preencha o que tiver agora; o histórico pode ser
                            enriquecido depois.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...LeadController.store.form()}
                            disableWhileProcessing
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <LeadForm
                                        products={products}
                                        statuses={statuses}
                                        processing={processing}
                                        errors={errors}
                                    />
                                    <div className="flex justify-end">
                                        <Button asChild variant="outline">
                                            <Link href={index()}>Cancelar</Link>
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

CreateLead.layout = {
    breadcrumbs: [
        {
            title: 'Prospecção',
            href: index(),
        },
        {
            title: 'Novo lead',
            href: create(),
        },
    ],
};
