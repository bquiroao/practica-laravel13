import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { api, errorMessage } from '@/lib/api';
import { formatTime } from '@/lib/format';
import { useAuth } from '@/auth/AuthContext';
import { Alert, Button, Card, PageHeader, Spinner } from '@/components/ui';

interface Row {
    id?: string; code?: string; name?: string; location?: string;
    available: number; occupied: number; maintenance: number; inactive: number;
    total: number; operativas: number; ocupacion: number | null; inconsistentes: number;
}

const level = (pct: number | null) => (pct === null ? 'bg-slate-300' : pct >= 90 ? 'bg-critico' : pct >= 75 ? 'bg-aviso' : 'bg-nido-600');

/** Módulo dashboard de ocupación: cuántas camas hay libres y qué tan llena está cada sala, de un vistazo. */
export function OccupancyPage() {
    const { session, can } = useAuth();
    const data = useQuery({
        queryKey: ['ocupacion'],
        queryFn: async () => (await api.get<{ totales: Row; salas: Row[]; generado_en: string }>('/ocupacion')).data,
        refetchInterval: 60_000,
    });

    if (data.isLoading) return <Spinner />;
    if (data.isError) return <Alert tone="error">{errorMessage(data.error)}</Alert>;
    const { totales: t, salas, generado_en } = data.data!;

    return (
        <>
            <PageHeader title="Ocupación" description={`Se actualiza cada minuto. Último cálculo: ${formatTime(generado_en, session?.hospital.timezone)}.`}
                actions={<Button variant="secondary" onClick={() => data.refetch()} loading={data.isFetching}><RefreshCw className="size-4" aria-hidden /> Actualizar</Button>} />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Kpi label="Ocupación" value={t.ocupacion === null ? '—' : `${t.ocupacion}%`} hint={`${t.occupied} de ${t.operativas} camas operativas`} />
                <Kpi label="Camas disponibles" value={String(t.available)} hint="Listas para un ingreso" tone="text-green-800" />
                <Kpi label="En mantenimiento" value={String(t.maintenance)} hint="Limpieza o reparación" tone="text-amber-800" />
                <Kpi label="Total de camas" value={String(t.total)} hint={`${t.inactive} inactivas`} />
            </div>

            {t.inconsistentes > 0 && (
                <div className="mt-4"><Alert tone="error">
                    <AlertTriangle className="mr-1 inline size-4" aria-hidden />
                    {t.inconsistentes} cama{t.inconsistentes === 1 ? '' : 's'} con estado que no coincide con su ocupación real. Revíselas en el <Link to="/camas" className="font-semibold underline">mapa de camas</Link>.
                </Alert></div>
            )}

            <Card className="mt-6">
                <h2 className="border-b border-slate-200 px-5 py-3 font-bold text-nido-900">Por sala</h2>
                <ul className="divide-y divide-slate-100">
                    {salas.map((s) => (
                        <li key={s.id} className="grid gap-2 px-5 py-4 sm:grid-cols-[14rem_1fr_auto] sm:items-center">
                            <div>
                                <p className="font-semibold text-slate-900">{s.name}</p>
                                <p className="text-xs text-slate-500">{s.location}</p>
                            </div>
                            <div>
                                <div className="h-3 overflow-hidden rounded-full bg-slate-200" role="img" aria-label={`Ocupación ${s.ocupacion ?? 0}%`}>
                                    <div className={`h-full rounded-full ${level(s.ocupacion)}`} style={{ width: `${s.ocupacion ?? 0}%` }} />
                                </div>
                                <p className="mt-1 text-xs text-slate-600">
                                    {s.occupied} ocupadas · {s.available} disponibles{s.maintenance ? ` · ${s.maintenance} en mantenimiento` : ''}{s.inactive ? ` · ${s.inactive} inactivas` : ''}
                                    {s.inconsistentes > 0 && <span className="font-semibold text-red-700"> · {s.inconsistentes} por revisar</span>}
                                </p>
                            </div>
                            <p className="text-right text-2xl font-bold tabular-nums text-nido-900">{s.ocupacion === null ? '—' : `${s.ocupacion}%`}</p>
                        </li>
                    ))}
                </ul>
            </Card>
            {can('camas.ver') && <p className="mt-4 text-sm text-slate-600">Para ver quién ocupa cada cama, abra el <Link to="/camas" className="font-semibold text-nido-700 underline">mapa de camas</Link>.</p>}
        </>
    );
}

function Kpi({ label, value, hint, tone = 'text-nido-900' }: { label: string; value: string; hint: string; tone?: string }) {
    return (
        <Card className="p-5">
            <p className="text-sm font-semibold text-slate-600">{label}</p>
            <p className={`mt-1 text-3xl font-extrabold tabular-nums ${tone}`}>{value}</p>
            <p className="mt-1 text-xs text-slate-600">{hint}</p>
        </Card>
    );
}
