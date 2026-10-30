import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { formatDate, formatTime } from '@/lib/format';
import { cn } from '@/lib/cn';
import { flagLabel, range, type Flag, type LabItem, type LabTest } from '@/modules/clinical/lab';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, PageHeader, Spinner, Textarea } from '@/components/ui';

type WorkItem = LabItem & { order_id: string; priority: 'routine' | 'urgent'; indication: string; ordered_at: string; patient: { id: string; code: string; name: string } };
type Stage = 'recepcion' | 'resultados' | 'validacion' | 'pruebas';

/**
 * Laboratorio: una lista de trabajo por paso (módulos recepción de muestras, ingreso de resultados y validación por
 * bioquímico). Las órdenes se crean desde el expediente del paciente (módulo órdenes de laboratorio desde EMR).
 */
export function LabPage() {
    const { can } = useAuth();
    const stages = ([
        ['recepcion', 'Recepción de muestras', can('muestras.recibir')],
        ['resultados', 'Ingreso de resultados', can('resultados.registrar')],
        ['validacion', 'Validación', can('resultados.validar')],
        ['pruebas', 'Pruebas disponibles', true],
    ] as [Stage, string, boolean][]).filter(([, , ok]) => ok);
    const [params, setParams] = useSearchParams();
    const stage = (params.get('etapa') as Stage) ?? stages[0][0];

    return (
        <>
            <PageHeader title="Laboratorio" description="Del pedido a la firma: cada pestaña es un paso del proceso. Las órdenes se piden desde el expediente del paciente." />
            <div role="tablist" aria-label="Pasos del laboratorio" className="mb-4 inline-flex flex-wrap rounded-lg bg-white p-1 ring-1 ring-slate-200">
                {stages.map(([key, label]) => (
                    <button key={key} role="tab" aria-selected={stage === key} onClick={() => setParams({ etapa: key })}
                        className={cn('rounded-md px-4 py-1.5 text-sm font-semibold', stage === key ? 'bg-nido-700 text-white' : 'text-slate-600 hover:bg-slate-100')}>{label}</button>
                ))}
            </div>
            {stage === 'pruebas' ? <TestsList /> : <Worklist stage={stage} />}
        </>
    );
}

function TestsList() {
    const tests = useQuery({ queryKey: ['lab', 'pruebas'], queryFn: async () => (await api.get<{ data: LabTest[] }>('/laboratorio/pruebas')).data.data });
    if (tests.isLoading) return <Spinner />;
    return (
        <Card>
            <p className="border-b border-slate-200 px-5 py-3 text-sm text-slate-600">Catálogo provisional. Lo administrará el módulo de catálogo de pruebas de laboratorio.</p>
            <ul className="divide-y divide-slate-100">
                {tests.data?.map((t) => (
                    <li key={t.code} className="flex flex-wrap justify-between gap-2 px-5 py-3 text-sm">
                        <span><strong>{t.name}</strong> <span className="font-mono text-xs text-slate-500">{t.code}</span><span className="block text-xs text-slate-600">{t.specimen_type}</span></span>
                        <span className="text-slate-700">Referencia: {range(t)}</span>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function Worklist({ stage }: { stage: Exclude<Stage, 'pruebas'> }) {
    const { session, can } = useAuth();
    const tz = session?.hospital.timezone;
    const queryClient = useQueryClient();
    const [notice, setNotice] = useState<string | null>(null);
    const [rejecting, setRejecting] = useState<WorkItem | null>(null);
    const [reviewing, setReviewing] = useState<WorkItem | null>(null);
    const [values, setValues] = useState<Record<string, string>>({});
    const list = useQuery({
        queryKey: ['lab', 'trabajo', stage],
        queryFn: async () => (await api.get<{ data: WorkItem[] }>('/laboratorio/trabajo', { params: { etapa: stage } })).data.data,
        refetchInterval: 30_000,
    });
    const done = (text: string) => { setNotice(text); setRejecting(null); setReviewing(null); queryClient.invalidateQueries({ queryKey: ['lab'] }); };
    const receive = useMutation({
        mutationFn: async (i: WorkItem) => (await api.post<{ data: LabItem }>(`/laboratorio/items/${i.id}/recibir`)).data.data,
        onSuccess: (r, i) => done(`Muestra de ${i.test_name} de ${i.patient.name} recibida. Rotule el tubo con el código ${r.specimen_code}.`),
    });
    const result = useMutation({
        mutationFn: async (i: WorkItem) => (await api.post<{ data: { flag: Flag } }>(`/laboratorio/items/${i.id}/resultado`, { value: values[i.id], unit: i.unit })).data.data,
        onSuccess: (r, i) => { setValues((v) => ({ ...v, [i.id]: '' })); done(`${i.test_name}: resultado guardado (${flagLabel[r.flag].label}). Queda pendiente de validación.`); },
    });

    if (list.isLoading) return <Spinner />;
    if (list.isError) return <Alert tone="error">{errorMessage(list.error)}</Alert>;
    const items = list.data!;
    const mutationError = receive.error ?? result.error;

    return (
        <>
            {notice && <div className="mb-4"><Alert tone="success">{notice}</Alert></div>}
            {mutationError && <div className="mb-4"><Alert tone="error">{errorMessage(mutationError)}</Alert></div>}
            <Card>
                <p className="border-b border-slate-200 px-5 py-3 text-sm text-slate-600">
                    {items.length === 0 ? 'Nada pendiente en este paso.' : `${items.length} pendiente${items.length === 1 ? '' : 's'}. Los urgentes aparecen primero.`}
                </p>
                {items.length === 0 ? <EmptyState title="¡Al día!">Se actualiza solo cada 30 segundos.</EmptyState> : (
                    <ul className="divide-y divide-slate-100">
                        {items.map((i) => (
                            <li key={i.id} className="grid gap-3 px-5 py-4 lg:grid-cols-[1fr_auto] lg:items-center">
                                <div>
                                    <p className="flex flex-wrap items-center gap-2">
                                        <span className="font-bold text-slate-900">{i.test_name}</span>
                                        {i.priority === 'urgent' && <Badge tone="critical">Urgente</Badge>}
                                        {i.specimen_code && <span className="font-mono text-xs text-slate-600">{i.specimen_code}</span>}
                                    </p>
                                    <p className="text-sm text-slate-700">{can('expediente.leer') ? <Link to={`/expediente/${i.patient.id}`} className="font-semibold hover:underline">{i.patient.name}</Link> : <span className="font-semibold">{i.patient.name}</span>} · {i.patient.code}</p>
                                    <p className="text-xs text-slate-600">{i.specimen_type} · Pedido el {formatDate(i.ordered_at, tz, { dateStyle: 'short' })} {formatTime(i.ordered_at, tz)} · {i.indication}</p>
                                    {stage === 'validacion' && i.result && (
                                        <p className="mt-1 text-sm">Resultado: <span className={flagLabel[i.result.flag].className}>{i.result.value} {i.unit} ({flagLabel[i.result.flag].label})</span>
                                            <span className="text-slate-600"> · Referencia {range(i)} · Ingresó {i.result.entered_by}</span></p>
                                    )}
                                </div>
                                <div className="flex flex-wrap items-end gap-2">
                                    {stage === 'recepcion' && <>
                                        <Button size="sm" loading={receive.isPending && receive.variables?.id === i.id} onClick={() => receive.mutate(i)}>Recibir muestra</Button>
                                        <Button size="sm" variant="ghost" className="text-red-700" onClick={() => setRejecting(i)}>Rechazar</Button>
                                    </>}
                                    {stage === 'resultados' && (
                                        <form className="flex items-end gap-2" onSubmit={(e) => { e.preventDefault(); result.mutate(i); }}>
                                            <Field label={`Valor (${i.unit})`} htmlFor={`val-${i.id}`} hint={`Ref. ${range(i)}`}>
                                                <Input id={`val-${i.id}`} className="w-32" type="number" step="any" inputMode="decimal" value={values[i.id] ?? ''}
                                                    onChange={(e) => { const v = e.target.value; setValues((x) => ({ ...x, [i.id]: v })); }} />
                                            </Field>
                                            <Button size="sm" type="submit" disabled={!values[i.id]} loading={result.isPending && result.variables?.id === i.id}>
                                                {i.status === 'resulted' ? 'Corregir' : 'Guardar'}
                                            </Button>
                                        </form>
                                    )}
                                    {stage === 'validacion' && <Button size="sm" onClick={() => setReviewing(i)}>Revisar</Button>}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
            {rejecting && <ReasonDialog title={`Rechazar muestra de ${rejecting.test_name}`} label="Motivo del rechazo" placeholder="Ej.: Muestra hemolizada, volumen insuficiente"
                confirm="Rechazar muestra" onClose={() => setRejecting(null)} onSubmit={(reason) => api.post(`/laboratorio/items/${rejecting.id}/rechazar`, { reason })}
                onDone={() => done(`Se rechazó la muestra de ${rejecting.test_name}. Avise para tomar una nueva.`)} danger />}
            {reviewing && <ReviewDialog item={reviewing} onClose={() => setReviewing(null)} onDone={done} />}
        </>
    );
}

function ReviewDialog({ item, onClose, onDone }: { item: WorkItem; onClose: () => void; onDone: (t: string) => void }) {
    const [note, setNote] = useState('');
    const review = useMutation({
        mutationFn: (approve: boolean) => api.post(`/laboratorio/resultados/${item.result!.id}/revision`, { approve, note }),
        onSuccess: (_, approve) => onDone(approve ? `${item.test_name} de ${item.patient.name}: resultado validado y visible en el expediente.` : `${item.test_name}: resultado rechazado; vuelve a ingreso de resultados.`),
    });
    const r = item.result!;
    return (
        <Dialog open onClose={onClose} title={`Validar ${item.test_name}`}>
            <div className="space-y-4">
                <div className="rounded-lg bg-slate-50 p-3 text-sm ring-1 ring-slate-200">
                    <p>{item.patient.name} · {item.specimen_code}</p>
                    <p className="mt-1 text-lg"><span className={flagLabel[r.flag].className}>{r.value} {item.unit}</span> <span className="text-sm text-slate-600">({flagLabel[r.flag].label}; referencia {range(item)})</span></p>
                </div>
                {r.flag.startsWith('critical') && <Alert tone="error">Valor crítico: al validarlo se notificará para seguimiento inmediato.</Alert>}
                {review.isError && <Alert tone="error">{errorMessage(review.error)}</Alert>}
                <Field label="Nota (obligatoria si rechaza)" htmlFor="rv-note"><Textarea id="rv-note" value={note} onChange={(e) => setNote(e.target.value)} /></Field>
                <div className="flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>Volver</Button>
                    <Button variant="danger" loading={review.isPending && review.variables === false} onClick={() => review.mutate(false)}>Rechazar resultado</Button>
                    <Button loading={review.isPending && review.variables === true} onClick={() => review.mutate(true)}>Validar y firmar</Button>
                </div>
            </div>
        </Dialog>
    );
}

export function ReasonDialog({ title, label, placeholder, confirm, onClose, onSubmit, onDone, danger }: {
    title: string; label: string; placeholder?: string; confirm: string; onClose: () => void; onSubmit: (reason: string) => Promise<unknown>; onDone: () => void; danger?: boolean;
}) {
    const [reason, setReason] = useState('');
    const run = useMutation({ mutationFn: () => onSubmit(reason), onSuccess: onDone });
    return (
        <Dialog open onClose={onClose} title={title}>
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); run.mutate(); }}>
                {run.isError && <Alert tone="error">{errorMessage(run.error)}</Alert>}
                <Field label={label} htmlFor="reason-dlg"><Textarea id="reason-dlg" value={reason} onChange={(e) => setReason(e.target.value)} placeholder={placeholder} /></Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Volver</Button>
                    <Button type="submit" variant={danger ? 'danger' : 'primary'} loading={run.isPending}>{confirm}</Button>
                </div>
            </form>
        </Dialog>
    );
}
