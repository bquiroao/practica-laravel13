import { Link } from 'react-router-dom';
import { Card } from '@/components/ui';

function Status({ code, title, text }: { code: string; title: string; text: string }) {
    return (
        <Card className="mx-auto mt-10 max-w-md p-8 text-center">
            <p className="text-sm font-bold text-nido-500">{code}</p>
            <h1 className="mt-2 text-xl font-bold text-nido-900">{title}</h1>
            <p className="mt-2 text-sm text-slate-600">{text}</p>
            <Link to="/inicio" className="mt-6 inline-block text-sm font-semibold text-nido-700 underline">Volver al inicio</Link>
        </Card>
    );
}

export const ForbiddenPage = () => <Status code="403" title="Sin permiso" text="Su rol en este hospital no permite ver esta pantalla." />;
export const NotFoundPage = () => <Status code="404" title="Página no encontrada" text="La dirección no existe." />;
