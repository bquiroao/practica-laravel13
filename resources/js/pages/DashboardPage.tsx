import { Link } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { sections } from '@/modules/catalog';
import { roleName } from '@/modules/roles';
import { Badge, Card, PageHeader } from '@/components/ui';

/** Módulo dashboard-base: inicio según el rol. Los indicadores reales llegan con cada fase. */
export function DashboardPage() {
    const { session, can } = useAuth();
    if (!session) return null;

    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';
    const shortcuts = sections.flatMap((s) => s.items).filter((i) => i.path !== '/inicio' && i.anyOf.some(can));

    return (
        <>
            <PageHeader title={`${greeting}, ${session.user.name}`} description={`${session.hospital.name} · ${session.roles.map(roleName).join(', ')}`} />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {shortcuts.map(({ path, label, icon: Icon, summary, ready, phase }) => (
                    <Link key={path} to={path} className="group">
                        <Card className="h-full p-5 transition group-hover:ring-nido-300">
                            <div className="flex items-start justify-between gap-3">
                                <span className="grid size-10 place-items-center rounded-full bg-nido-100 text-nido-700"><Icon className="size-5" aria-hidden /></span>
                                {ready ? <Badge tone="ok">Disponible</Badge> : <Badge tone="neutral">Fase {phase}</Badge>}
                            </div>
                            <h2 className="mt-4 font-bold text-nido-900">{label}</h2>
                            <p className="mt-1 text-sm text-slate-600">{summary}</p>
                            <span className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-nido-700">
                                Abrir <ArrowRight className="size-4 transition group-hover:translate-x-0.5" aria-hidden />
                            </span>
                        </Card>
                    </Link>
                ))}
            </div>
        </>
    );
}
