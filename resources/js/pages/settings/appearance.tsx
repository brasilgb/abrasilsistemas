import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Configurações de aparência" />

            <h1 className="sr-only">Configurações de aparência</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Aparência"
                    description="Escolha o tema visual usado na sua conta"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Configurações de aparência',
            href: editAppearance(),
        },
    ],
};
