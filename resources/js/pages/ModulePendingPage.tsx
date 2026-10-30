import { Construction } from 'lucide-react';
import type { ModuleEntry } from '@/modules/catalog';
import { Badge, Card, PageHeader } from '@/components/ui';

/** Pantalla de un módulo que todavía no se integra: dice qué hará y de qué módulos de la documentación sale. */
export function ModulePendingPage({ entry }: { entry: ModuleEntry }) {
    return (
        <>
            <PageHeader title={entry.label} description={entry.summary} />
            <Card className="p-8 text-center">
                <Construction className="mx-auto size-10 text-nido-500" aria-hidden />
                <p className="mt-4 text-lg font-bold text-nido-900">Se integra en la fase {entry.phase}</p>
                <p className="mx-auto mt-2 max-w-lg text-sm text-slate-600">
                    Esta pantalla se construye a partir de las reglas, pruebas y diseños de estos módulos:
                </p>
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    {entry.modules.map((m) => <Badge key={m} tone="nido">{m}</Badge>)}
                </div>
            </Card>
        </>
    );
}
