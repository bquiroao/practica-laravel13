import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, Plus, XCircle } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { formatDate, formatTime } from '@/lib/format';
import { cn } from '@/lib/cn';
import { allEntries } from '@/modules/catalog';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, PageHeader, Select, Spinner, Textarea } from '@/components/ui';

interface Defect { defect_id: string; code: string; title: string; module: string; severity: 'critica' | 'alta' | 'media' | 'baja'; status: string; steps: string; version: string | null; resolution_note: string | null; created_at: string; next: string[] }
interface Run { run_id: string; version: string; suite: string; total: number; passed: number; failed: number; flaky: number; source: 'manual' | 'ci'; ran_at: string; commit_sha: string | null }
interface Gate { version: string; approved: boolean; reasons: string[] }

const sevTone = { critica: 'critical', alta: 'warn', media: 'nido', baja: 'neutral' } as const;
const statusLabel: Record<string, string> = { abierto: 'Abierto', en_correccion: 'En corrección', corregido: 'Corregido (por verificar)', cerrado: 'Cerrado', reabierto: 'Reabierto' };
const action: Record<string, string> = { en_correccion: 'Empezar corrección', corregido: 'Marcar corregido', cerrado: 'Cerrar (verificado)', reabierto: 'Reabrir' };
const modules = [...new Set(allEntries.flatMap((e) => e.modules))].sort();

/** Calidad: defectos (módulo QA regresión y defectos) y ejecuciones con quality gate (módulo QA E2E, CI y despliegue). */
export function QualityPage() {
    const [params, setParams] = useSearchParams();
    const tab = params.get('vista') === 'ejecuciones' ? 'ejecuciones' : 'defectos';
    return (
        <>
            <PageHeader title="Calidad" description="Defectos encontrados y resultados de las pruebas de cada versión. Una versión se despliega solo si pasa el quality gate." />
            <div role="tablist" aria-label="Secciones" className="mb-4 inline-flex rounded-lg bg-white p-1 ring-1 ring-slate-200">
                {[['defectos', 'Defectos'], ['ejecuciones', 'Ejecuciones y quality gate']].map(([k, l]) => (
                    <button key={k} role="tab" aria-selected={tab === k} onClick={() => setParams(k === 'defectos' ? {} : { vista: k })}
                        className={cn('rounded-md px-4 py-1.5 text-sm font-semibold', tab === k ? 'bg-nido-700 text-white' : 'text-slate-600 hover:bg-slate-100')}>{l}</button>
                ))}
            </div>
            {tab === 'defectos' ? <Defects /> : <Runs />}
        </>
    );
}

function Defects() {
    const { can, session } = useAuth();
    const queryClient = useQueryClient();
    const [creating, setCreating] = useState(false);
    const [moving, setMoving] = useState<{ d: Defect; to: string } | null>(null);
    const [onlyOpen, setOnlyOpen] = useState(true);
    const list = useQuery({ queryKey: ['calidad', 'defectos', onlyOpen], queryFn: async () => (await api.get<{ data: Defect[] }>('/calidad/defectos', { params: { estado: onlyOpen ? 'abiertos' : undefined } })).data.data });
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['calidad'] });

    return (
        <>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="accent-nido-700" checked={onlyOpen} onChange={(e) => setOnlyOpen(e.target.checked)} /> Solo los que no están cerrados</label>
                {can('calidad.gestionar') && <Button onClick={() => setCreating(true)}><Plus className="size-4" aria-hidden /> Reportar defecto</Button>}
            </div>
            {list.isLoading ? <Spinner /> : list.data!.length === 0 ? <Card><EmptyState title="Sin defectos pendientes." /></Card> : (
                <ul className="space-y-3">
                    {list.data!.map((d) => (
                        <li key={d.defect_id}><Card className="p-4">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="text-sm">
                                    <p className="font-bold text-slate-900"><span className="font-mono text-xs text-slate-600">{d.code}</span> {d.title}</p>
                                    <p className="text-slate-600">{d.module}{d.version ? ` · versión ${d.version}` : ''} · {formatDate(d.created_at, session?.hospital.timezone)}</p>
                                    <p className="mt-1 whitespace-pre-line text-slate-700">{d.steps}</p>
                                    {d.resolution_note && <p className="mt-1 text-xs text-nido-800">Nota: {d.resolution_note}</p>}
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge tone={sevTone[d.severity]}>{d.severity}</Badge>
                                    <Badge tone={d.status === 'cerrado' ? 'ok' : 'neutral'}>{statusLabel[d.status]}</Badge>
                                    {can('calidad.gestionar') && d.next.map((to) => <Button key={to} size="sm" variant={to === 'cerrado' ? 'primary' : 'secondary'} onClick={() => setMoving({ d, to })}>{action[to]}</Button>)}
                                </div>
                            </div>
                        </Card></li>
                    ))}
                </ul>
            )}
            {creating && <NewDefect onClose={() => setCreating(false)} onDone={() => { setCreating(false); refresh(); }} />}
            {moving && <MoveDefect d={moving.d} to={moving.to} onClose={() => setMoving(null)} onDone={() => { setMoving(null); refresh(); }} />}
        </>
    );
}

function NewDefect({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
    const [form, setForm] = useState({ title: '', module: '', severity: 'media', steps: '', version: '' });
    const save = useMutation({ mutationFn: () => api.post('/calidad/defectos', form), onSuccess: onDone });
    const errors = fieldErrors(save.error);
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };
    return (
        <Dialog open onClose={onClose} title="Reportar defecto">
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                {save.isError && !Object.keys(errors).length && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <Field label="Qué falla" htmlFor="df-title" error={errors.title}><Input id="df-title" value={form.title} onChange={set('title')} placeholder="Ej.: La agenda no muestra las citas canceladas" /></Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Módulo" htmlFor="df-mod" error={errors.module}><Select id="df-mod" value={form.module} onChange={set('module')}><option value="">Seleccione…</option>{modules.map((m) => <option key={m}>{m}</option>)}</Select></Field>
                    <Field label="Severidad" htmlFor="df-sev"><Select id="df-sev" value={form.severity} onChange={set('severity')}><option value="critica">Crítica</option><option value="alta">Alta</option><option value="media">Media</option><option value="baja">Baja</option></Select></Field>
                </div>
                <Field label="Pasos para reproducirlo" htmlFor="df-steps" error={errors.steps} hint="Numerados, con lo que se esperaba y lo que pasó."><Textarea id="df-steps" value={form.steps} onChange={set('steps')} /></Field>
                <Field label="Versión (opcional)" htmlFor="df-ver"><Input id="df-ver" value={form.version} onChange={set('version')} placeholder="Ej.: 1.0.0" /></Field>
                <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button><Button type="submit" loading={save.isPending}>Reportar</Button></div>
            </form>
        </Dialog>
    );
}

function MoveDefect({ d, to, onClose, onDone }: { d: Defect; to: string; onClose: () => void; onDone: () => void }) {
    const [note, setNote] = useState('');
    const save = useMutation({ mutationFn: () => api.post(`/calidad/defectos/${d.defect_id}/estado`, { status: to, note }), onSuccess: onDone });
    return (
        <Dialog open onClose={onClose} title={`${action[to]}: ${d.code}`}>
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                {save.isError && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <Field label={to === 'cerrado' ? 'Cómo se verificó (re-ejecución)' : 'Nota'} htmlFor="df-note" hint={to === 'en_correccion' ? 'Opcional.' : 'Obligatoria, al menos 10 caracteres.'}>
                    <Textarea id="df-note" value={note} onChange={(e) => setNote(e.target.value)} />
                </Field>
                <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={onClose}>Volver</Button><Button type="submit" loading={save.isPending}>{action[to]}</Button></div>
            </form>
        </Dialog>
    );
}

function Runs() {
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const queryClient = useQueryClient();
    const [form, setForm] = useState({ version: '', suite: 'php artisan test', total: '', passed: '', failed: '0', flaky: '0' });
    const data = useQuery({ queryKey: ['calidad', 'ejecuciones'], queryFn: async () => (await api.get<{ data: Run[]; gates: Gate[] }>('/calidad/ejecuciones')).data });
    const save = useMutation({
        mutationFn: () => api.post('/calidad/ejecuciones', { ...form, total: Number(form.total), passed: Number(form.passed), failed: Number(form.failed), flaky: Number(form.flaky) }),
        onSuccess: () => { setForm((f) => ({ ...f, total: '', passed: '' })); queryClient.invalidateQueries({ queryKey: ['calidad'] }); },
    });
    const errors = fieldErrors(save.error);
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };

    if (data.isLoading) return <Spinner />;
    return (
        <div className="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
            {can('versiones.gestionar') && (
                <Card className="p-5">
                    <h2 className="font-bold text-nido-900">Registrar ejecución</h2>
                    <p className="mt-1 text-sm text-slate-600">El CI de GitHub las registra solo; aquí se registran las manuales.</p>
                    <form className="mt-4 grid grid-cols-2 gap-3" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                        <div className="col-span-2"><Field label="Versión" htmlFor="rn-ver" error={errors.version}><Input id="rn-ver" value={form.version} onChange={set('version')} placeholder="1.0.0" /></Field></div>
                        <div className="col-span-2"><Field label="Suite" htmlFor="rn-suite" error={errors.suite}><Input id="rn-suite" value={form.suite} onChange={set('suite')} /></Field></div>
                        <Field label="Total" htmlFor="rn-total" error={errors.total}><Input id="rn-total" type="number" min={1} value={form.total} onChange={set('total')} /></Field>
                        <Field label="Aprobadas" htmlFor="rn-pass" error={errors.passed}><Input id="rn-pass" type="number" min={0} value={form.passed} onChange={set('passed')} /></Field>
                        <Field label="Fallidas" htmlFor="rn-fail" error={errors.failed}><Input id="rn-fail" type="number" min={0} value={form.failed} onChange={set('failed')} /></Field>
                        <Field label="Inestables" htmlFor="rn-flaky" hint="Pasaron al reintentar."><Input id="rn-flaky" type="number" min={0} value={form.flaky} onChange={set('flaky')} /></Field>
                        {save.isError && !Object.keys(errors).length && <div className="col-span-2"><Alert tone="error">{errorMessage(save.error)}</Alert></div>}
                        <Button type="submit" className="col-span-2" loading={save.isPending}>Registrar</Button>
                    </form>
                </Card>
            )}
            <div className="min-w-0 space-y-4">
                {data.data!.gates.map((g) => (
                    <Card key={g.version} className={cn('p-4 ring-2', g.approved ? 'ring-green-300' : 'ring-red-300')}>
                        <p className="flex items-center gap-2 font-bold">{g.approved ? <CheckCircle2 className="size-5 text-green-700" aria-hidden /> : <XCircle className="size-5 text-red-700" aria-hidden />}
                            Versión {g.version}: {g.approved ? 'aprobada para desplegar' : 'bloqueada'}</p>
                        {g.reasons.length > 0 && <ul className="mt-1 list-disc pl-6 text-sm text-slate-700">{g.reasons.map((r, i) => <li key={i}>{r}</li>)}</ul>}
                    </Card>
                ))}
                <Card>
                    <h2 className="border-b border-slate-200 px-5 py-3 font-bold text-nido-900">Últimas ejecuciones</h2>
                    {data.data!.data.length === 0 ? <EmptyState title="Sin ejecuciones registradas." /> : (
                        <ul className="divide-y divide-slate-100">
                            {data.data!.data.map((r) => (
                                <li key={r.run_id} className="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                                    <span><strong>{r.version}</strong> · {r.suite} <span className="text-slate-600">· {formatDate(r.ran_at, tz, { dateStyle: 'short' })} {formatTime(r.ran_at, tz)}</span></span>
                                    <span className="flex items-center gap-2"><Badge tone={r.failed ? 'critical' : 'ok'}>{r.passed}/{r.total} aprobadas</Badge>{r.flaky > 0 && <Badge tone="warn">{r.flaky} inestables</Badge>}<Badge>{r.source === 'ci' ? 'CI' : 'Manual'}</Badge></span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </div>
    );
}
