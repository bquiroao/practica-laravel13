import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BellRing } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/cn';
import { Alert, Badge, Button, Card, EmptyState, Field, Input, PageHeader, Spinner } from '@/components/ui';
import { AlertDetailDialog } from '@/modules/alerts/AlertDetailDialog';
import { statusTone, summaryKey, type AlertPolicy, type CriticalAlert } from '@/modules/alerts/types';

type View = 'mias' | 'abiertas' | 'recientes' | 'plazos';

/**
 * Módulo alertas críticas y notificaciones internas. «Para mí» son las que esperan mi acuse; «Abiertas», las de todo
 * el hospital que nadie ha acusado; «Plazos», el tiempo de acuse por prioridad (CU-08).
 */
export function AlertsPage() {
    const { can } = useAuth();
    const views = ([
        ['mias', 'Para mí', can('alertas.ver')],
        ['abiertas', 'Abiertas', can('alertas.ver')],
        ['recientes', 'Recientes', can('alertas.ver')],
        ['plazos', 'Plazos de acuse', can('alertas.configurar')],
    ] as [View, string, boolean][]).filter(([, , ok]) => ok);
    const [params, setParams] = useSearchParams();
    const view = (views.find(([k]) => k === params.get('vista'))?.[0] ?? views[0]?.[0]) as View;

    return (
        <>
            <PageHeader title="Alertas críticas" description="Cuando un resultado o un signo vital no puede esperar. Si nadie la acusa a tiempo, sube al nivel siguiente." />
            <div role="tablist" aria-label="Vistas de alertas" className="mb-4 inline-flex flex-wrap rounded-lg bg-white p-1 ring-1 ring-slate-200">
                {views.map(([key, label]) => (
                    <button key={key} role="tab" aria-selected={view === key} onClick={() => setParams({ vista: key })}
                        className={cn('rounded-md px-4 py-1.5 text-sm font-semibold', view === key ? 'bg-nido-700 text-white' : 'text-slate-600 hover:bg-slate-100')}>{label}</button>
                ))}
            </div>
            {view === 'plazos' ? <Policies /> : <AlertList view={view} />}
        </>
    );
}

function AlertList({ view }: { view: Exclude<View, 'plazos'> }) {
    const { session } = useAuth();
    const queryClient = useQueryClient();
    const [open, setOpen] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const list = useQuery({
        queryKey: ['alertas', 'lista', view],
        queryFn: async () => (await api.get<{ data: CriticalAlert[] }>('/alertas', { params: { vista: view } })).data.data,
        refetchInterval: 30_000,
    });
    useEffect(() => setNotice(null), [view]);
    const changed = (_: CriticalAlert, message: string) => {
        setNotice(message);
        queryClient.invalidateQueries({ queryKey: ['alertas', 'lista'] });
        queryClient.invalidateQueries({ queryKey: summaryKey });
    };
    const empty = { mias: 'No tiene alertas pendientes de acuse.', abiertas: 'No hay alertas sin acusar en el hospital.', recientes: 'Todavía no hay alertas.' }[view];

    return (
        <>
            {notice && <div className="mb-4"><Alert tone="success">{notice}</Alert></div>}
            {list.isLoading ? <Spinner /> : list.isError ? <Alert tone="error">{errorMessage(list.error)}</Alert> : list.data!.length === 0 ? (
                <Card><EmptyState title={empty} /></Card>
            ) : (
                <ul className="space-y-3">
                    {list.data!.map((a) => (
                        <li key={a.id}>
                            <button type="button" onClick={() => setOpen(a.id)}
                                className={cn('w-full rounded-[var(--radius-nido)] bg-white p-4 text-left ring-1 hover:bg-slate-50 focus-visible:bg-slate-50',
                                    a.priority === 'CRITICA' && ['ENVIADA', 'ESCALADA'].includes(a.status) ? 'ring-2 ring-red-300' : 'ring-slate-200')}>
                                <span className="flex flex-wrap items-start justify-between gap-2">
                                    <span className="flex items-center gap-2 font-bold text-slate-900">
                                        <BellRing className={cn('size-5', a.priority === 'CRITICA' ? 'text-red-700' : 'text-amber-700')} aria-hidden />{a.title}
                                    </span>
                                    <span className="flex flex-wrap gap-2">
                                        <Badge tone={a.priority === 'CRITICA' ? 'critical' : 'warn'}>{a.priority_label}</Badge>
                                        <Badge tone={statusTone[a.status]}>{a.status_label}{a.level > 0 ? ` · nivel ${a.level}` : ''}</Badge>
                                    </span>
                                </span>
                                <span className="mt-1 block text-sm text-slate-700">{a.description}</span>
                                <span className="mt-1 block text-xs text-slate-500">
                                    Paciente <span className="font-mono">{a.patient.code}</span> · {a.source_label} · {formatTime(a.created_at, session?.hospital.timezone)}
                                    {a.deadline_at && ` · acusar antes de las ${formatTime(a.deadline_at, session?.hospital.timezone)}`}
                                    {a.acknowledged_by && ` · acusada por ${a.acknowledged_by}`}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {open && <AlertDetailDialog id={open} onClose={() => setOpen(null)} onChanged={changed} />}
        </>
    );
}

/** CU-08 y RN-11: plazos de 5 a 60 minutos. Cambiarlos aplica a los plazos que empiecen desde ahora. */
function Policies() {
    const { can } = useAuth();
    const policies = useQuery({ queryKey: ['alertas', 'politicas'], queryFn: async () => (await api.get<{ data: AlertPolicy[] }>('/alertas/politicas')).data.data });
    const [form, setForm] = useState<Record<string, string> | null>(null);
    const values = form ?? Object.fromEntries((policies.data ?? []).map((p) => [p.priority, String(p.ack_minutes)]));
    const save = useMutation({
        mutationFn: async () => (await api.put<{ data: AlertPolicy[] }>('/alertas/politicas', { CRITICA: Number(values.CRITICA), ALTA: Number(values.ALTA) })).data.data,
        onSuccess: () => { setForm(null); policies.refetch(); },
    });
    const errors = fieldErrors(save.error);

    if (policies.isLoading) return <Spinner />;
    return (
        <Card className="max-w-xl p-5">
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                <p className="text-sm text-slate-600">Cuánto se espera el acuse en cada nivel antes de escalar. Aplica a los plazos que empiecen desde ahora.</p>
                {save.isSuccess && <Alert tone="success">Plazos guardados.</Alert>}
                {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <div className="grid gap-4 sm:grid-cols-2">
                    {policies.data!.map((p) => (
                        <Field key={p.priority} label={`Prioridad ${p.label.toLowerCase()} (minutos)`} htmlFor={`pol-${p.priority}`} error={errors[p.priority]} hint="De 5 a 60.">
                            <Input id={`pol-${p.priority}`} type="number" min={5} max={60} value={values[p.priority] ?? ''} readOnly={!can('alertas.configurar')}
                                onChange={(e) => setForm({ ...values, [p.priority]: e.target.value })} aria-invalid={!!errors[p.priority]} />
                        </Field>
                    ))}
                </div>
                {can('alertas.configurar') && <Button type="submit" loading={save.isPending}>Guardar plazos</Button>}
            </form>
        </Card>
    );
}
