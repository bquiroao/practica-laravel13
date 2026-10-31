import { useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Activity, BedDouble, FilePlus2, Stethoscope } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, ruleError } from '@/lib/api';
import { formatDate, formatTime, sexLabels } from '@/lib/format';
import { cn } from '@/lib/cn';
import type { Patient } from '@/modules/clinical/types';
import { AllergyBanner } from '@/modules/allergies/AllergyBanner';
import { LabTab } from '@/pages/records/LabTab';
import { PrescriptionsTab } from '@/pages/records/PrescriptionsTab';
import { Alert, BackLink, Badge, Button, Card, Dialog, EmptyState, Field, Input, Select, Spinner, Textarea } from '@/components/ui';

type Soap = { s: string; o: string; a: string; p: string };
interface Dx { code: string; description: string; type: 'primary' | 'secondary' | 'differential'; status?: string }
interface Note { id: string; episode_id: string; status: 'draft' | 'signed'; version: number; sections: Soap; author: string | null; author_ref: string;
    created_at: string; signed_at: string | null; correction_reason: string | null; superseded: boolean; diagnoses: Dx[] }
interface Episode { id: string; type: 'outpatient' | 'emergency' | 'inpatient'; status: string; reason: string; started_at: string; ended_at: string | null; discharge_type: string | null; bed: string | null }
interface Finding { signo: string; etiqueta: string; valor: number; nivel: 'anormal' | 'critica' }
interface Vital { id: string; measured_at: string; recorded_by: string | null; values: Record<string, number | null>; findings: Finding[] }
interface Sign { key: string; label: string; unit: string; min: number | null; max: number | null; physical: [number, number] }
interface RecordData { patient: Patient; record_number: string | null; episodes: Episode[]; notes: Note[]; diagnoses: (Dx & { since: string })[]; vitals: Vital[]; signs: Sign[] }

const SECTIONS: { key: keyof Soap; label: string; hint: string }[] = [
    { key: 's', label: 'S · Subjetivo', hint: 'Lo que cuenta el paciente: motivo, síntomas, desde cuándo.' },
    { key: 'o', label: 'O · Objetivo', hint: 'Lo que usted observa y mide: examen físico, signos, resultados.' },
    { key: 'a', label: 'A · Análisis', hint: 'Su impresión diagnóstica.' },
    { key: 'p', label: 'P · Plan', hint: 'Tratamiento, indicaciones, exámenes y seguimiento.' },
];
const dxType = { primary: 'Principal', secondary: 'Secundario', differential: 'Diferencial' };
const episodeType = { outpatient: 'Consulta externa', emergency: 'Emergencia', inpatient: 'Internamiento' };
const TABS = [['notas', 'Notas SOAP'], ['signos', 'Signos vitales'], ['laboratorio', 'Laboratorio'], ['recetas', 'Recetas'], ['atenciones', 'Atenciones']] as const;

/** Expediente del paciente (módulos expediente médico, notas SOAP y signos vitales). */
export function RecordPage() {
    const { id } = useParams();
    const { session } = useAuth();
    const tz = session?.hospital.timezone;
    const [params, setParams] = useSearchParams();
    const tab = (params.get('vista') ?? 'notas') as (typeof TABS)[number][0];
    const record = useQuery({ queryKey: ['expediente', id], queryFn: async () => (await api.get<RecordData>(`/expediente/${id}`)).data });

    if (record.isLoading) return <Spinner />;
    if (record.isError) return <><BackLink to="/expediente">Expediente</BackLink><Alert tone="error">{errorMessage(record.error)}</Alert></>;
    const data = record.data!;
    const p = data.patient;
    const current = data.episodes.find((e) => e.status === 'open' && e.type === 'inpatient') ?? data.episodes.find((e) => e.status === 'open');

    return (
        <>
            <BackLink to="/expediente">Expediente</BackLink>
            <Card className="p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-nido-900">{p.full_name}</h1>
                        <p className="text-sm text-slate-600">{p.code} · {p.age} años · {sexLabels[p.sex]}{data.record_number ? ` · Expediente ${data.record_number}` : ''}</p>
                    </div>
                    <Link to={`/pacientes/${p.id}`} className="text-sm font-semibold text-nido-700 underline">Datos y citas del paciente</Link>
                </div>
                {/* Banner de alergias del módulo alergias-clinicas-alerta-visual-expediente (README, sección 7.5). */}
                <AllergyBanner patientId={p.id} className="mt-4" />
                <div className="mt-4 flex flex-wrap items-center gap-2 rounded-lg bg-nido-50 px-3 py-2 text-sm text-nido-900 ring-1 ring-nido-200" aria-live="polite">
                    {current ? (
                        <>{current.type === 'inpatient' ? <BedDouble className="size-4" aria-hidden /> : <Stethoscope className="size-4" aria-hidden />}
                            <span><strong>Atención actual:</strong> {episodeType[current.type]}{current.bed ? ` en ${current.bed}` : ''} desde el {formatDate(current.started_at, tz)} · {current.reason}</span></>
                    ) : <span>Sin atención abierta. Al escribir una nota o registrar signos vitales se abrirá una consulta externa.</span>}
                </div>
                {data.diagnoses.length > 0 && (
                    <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                        <span className="font-semibold text-slate-700">Diagnósticos activos:</span>
                        {data.diagnoses.map((d, i) => <Badge key={i} tone={d.type === 'primary' ? 'nido' : 'neutral'}>{d.code} · {d.description}</Badge>)}
                    </div>
                )}
            </Card>

            <div role="tablist" aria-label="Secciones del expediente" className="my-4 inline-flex flex-wrap rounded-lg bg-white p-1 ring-1 ring-slate-200">
                {TABS.map(([key, label]) => (
                    <button key={key} role="tab" aria-selected={tab === key} onClick={() => setParams(key === 'notas' ? {} : { vista: key })}
                        className={cn('rounded-md px-4 py-1.5 text-sm font-semibold', tab === key ? 'bg-nido-700 text-white' : 'text-slate-600 hover:bg-slate-100')}>
                        {label}{key === 'notas' ? ` (${data.notes.filter((n) => !n.superseded).length})` : key === 'signos' ? ` (${data.vitals.length})` : ''}
                    </button>
                ))}
            </div>

            {tab === 'notas' && <NotesTab data={data} currentEpisode={current} />}
            {tab === 'signos' && <VitalsTab data={data} />}
            {tab === 'atenciones' && <EpisodesTab data={data} />}
            {tab === 'laboratorio' && <LabTab patientId={data.patient.id} />}
            {tab === 'recetas' && <PrescriptionsTab patientId={data.patient.id} />}
        </>
    );
}

function NotesTab({ data, currentEpisode }: { data: RecordData; currentEpisode?: Episode }) {
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const [editor, setEditor] = useState<{ mode: 'new' | 'draft' | 'correct'; note?: Note } | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const visible = data.notes.filter((n) => !n.superseded);
    const history = data.notes.filter((n) => n.superseded);

    return (
        <>
            {notice && <div className="mb-4"><Alert tone="success">{notice}</Alert></div>}
            {can('notas.registrar') && !editor && (
                <Button className="mb-4" onClick={() => { setNotice(null); setEditor({ mode: 'new' }); }}><FilePlus2 className="size-4" aria-hidden /> Nueva nota</Button>
            )}
            {editor && <NoteEditor patientId={data.patient.id} episodeId={editor.note?.episode_id ?? currentEpisode?.id} editor={editor}
                onClose={() => setEditor(null)} onSaved={(t) => { setEditor(null); setNotice(t); }} />}

            {visible.length === 0 ? <Card><EmptyState title="Todavía no hay notas en este expediente." /></Card> : (
                <ol className="space-y-4">
                    {visible.map((n) => (
                        <li key={n.id}>
                            <Card className="p-5">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold text-slate-900">{formatDate(n.created_at, tz)}, {formatTime(n.created_at, tz)} · {n.author}</p>
                                        {n.version > 1 && <p className="text-xs text-amber-800">Corrección (versión {n.version}): {n.correction_reason}</p>}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {n.status === 'signed' ? <Badge tone="ok">Firmada</Badge> : <Badge tone="warn">Borrador</Badge>}
                                        {n.status === 'draft' && can('notas.registrar') && <Button size="sm" variant="secondary" onClick={() => setEditor({ mode: 'draft', note: n })}>Continuar borrador</Button>}
                                        {n.status === 'signed' && can('notas.firmar') && <Button size="sm" variant="ghost" onClick={() => setEditor({ mode: 'correct', note: n })}>Corregir</Button>}
                                    </div>
                                </div>
                                <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                    {SECTIONS.map((s) => (
                                        <div key={s.key}><dt className="font-bold text-nido-800">{s.label}</dt><dd className="mt-0.5 whitespace-pre-line text-slate-700">{n.sections[s.key]}</dd></div>
                                    ))}
                                </dl>
                                {n.diagnoses.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {n.diagnoses.filter((d) => d.status !== 'entered_in_error').map((d, i) => <Badge key={i} tone={d.type === 'primary' ? 'nido' : 'neutral'}>{dxType[d.type]}: {d.code} {d.description}</Badge>)}
                                    </div>
                                )}
                            </Card>
                        </li>
                    ))}
                </ol>
            )}
            {history.length > 0 && (
                <details className="mt-4 text-sm">
                    <summary className="cursor-pointer font-semibold text-nido-700">Ver {history.length} versión{history.length === 1 ? '' : 'es'} anterior{history.length === 1 ? '' : 'es'} (corregida{history.length === 1 ? '' : 's'})</summary>
                    <ul className="mt-2 space-y-2">
                        {history.map((n) => (
                            <li key={n.id} className="rounded-lg bg-slate-50 p-3 ring-1 ring-slate-200">
                                <p className="text-xs text-slate-600">Versión {n.version} · {formatDate(n.created_at, tz)} · {n.author}</p>
                                <p className="mt-1 text-slate-700"><strong>A:</strong> {n.sections.a} · <strong>P:</strong> {n.sections.p}</p>
                            </li>
                        ))}
                    </ul>
                </details>
            )}
        </>
    );
}

function NoteEditor({ patientId, episodeId, editor, onClose, onSaved }: {
    patientId: string; episodeId?: string; editor: { mode: 'new' | 'draft' | 'correct'; note?: Note }; onClose: () => void; onSaved: (t: string) => void;
}) {
    const { can } = useAuth();
    const queryClient = useQueryClient();
    const [soap, setSoap] = useState<Soap>(editor.note?.sections ?? { s: '', o: '', a: '', p: '' });
    const [dx, setDx] = useState<Dx[]>(editor.note?.diagnoses.filter((d) => d.status !== 'entered_in_error') ?? []);
    const [reason, setReason] = useState('');
    const [confirmSign, setConfirmSign] = useState(false);
    const save = useMutation({
        mutationFn: async (sign: boolean) => {
            const body = { soap, diagnoses: dx.filter((d) => d.code || d.description), sign, episode_id: episodeId, reason };
            if (editor.mode === 'correct') return api.post(`/notas/${editor.note!.id}/correccion`, body);
            if (editor.mode === 'draft') return api.put(`/notas/${editor.note!.id}`, body);
            return api.post(`/expediente/${patientId}/notas`, body);
        },
        onSuccess: (_, sign) => {
            setConfirmSign(false);
            queryClient.invalidateQueries({ queryKey: ['expediente', patientId] });
            onSaved(editor.mode === 'correct' ? 'Corrección firmada. La versión anterior queda en el historial.' : sign ? 'Nota firmada.' : 'Borrador guardado. Puede continuarlo después.');
        },
        onError: () => setConfirmSign(false),
    });
    const rule = ruleError(save.error);
    const missing = rule?.code === 'NOTA_INCOMPLETA' ? (rule.details.campo as string) : null;

    return (
        <Card className="mb-6 p-5 ring-2 ring-nido-300">
            <h2 className="font-bold text-nido-900">{editor.mode === 'correct' ? `Corregir nota del ${formatDate(editor.note!.created_at)}` : editor.mode === 'draft' ? 'Continuar borrador' : 'Nueva nota SOAP'}</h2>
            <p className="mt-1 text-sm text-slate-600">Las cuatro secciones son obligatorias. Puede guardar un borrador y firmarlo después; una nota firmada ya no se modifica, solo se corrige.</p>
            <form className="mt-4 space-y-4" onSubmit={(e) => { e.preventDefault(); setConfirmSign(true); }}>
                <div className="grid gap-4 md:grid-cols-2">
                    {SECTIONS.map((s) => (
                        <Field key={s.key} label={s.label} htmlFor={`soap-${s.key}`} hint={s.hint} error={missing === s.key ? rule?.message : undefined}>
                            <Textarea id={`soap-${s.key}`} rows={4} value={soap[s.key]} aria-invalid={missing === s.key}
                                onChange={(e) => { const v = e.target.value; setSoap((x) => ({ ...x, [s.key]: v })); }} />
                        </Field>
                    ))}
                </div>
                <fieldset className="space-y-2">
                    <legend className="text-sm font-semibold text-slate-700">Diagnósticos (código CIE-10)</legend>
                    {dx.map((d, i) => (
                        <div key={i} className="grid gap-2 sm:grid-cols-[8rem_1fr_10rem_auto] sm:items-center">
                            <label className="sr-only" htmlFor={`dx-c${i}`}>Código</label>
                            <Input id={`dx-c${i}`} className="uppercase" placeholder="J18.9" value={d.code} onChange={(e) => { const v = e.target.value; setDx((x) => x.map((y, j) => (j === i ? { ...y, code: v } : y))); }} />
                            <label className="sr-only" htmlFor={`dx-d${i}`}>Descripción</label>
                            <Input id={`dx-d${i}`} placeholder="Neumonía, no especificada" value={d.description} onChange={(e) => { const v = e.target.value; setDx((x) => x.map((y, j) => (j === i ? { ...y, description: v } : y))); }} />
                            <label className="sr-only" htmlFor={`dx-t${i}`}>Tipo</label>
                            <Select id={`dx-t${i}`} value={d.type} onChange={(e) => { const v = e.target.value as Dx['type']; setDx((x) => x.map((y, j) => (j === i ? { ...y, type: v } : y))); }}>
                                <option value="primary">Principal</option><option value="secondary">Secundario</option><option value="differential">Diferencial</option>
                            </Select>
                            <Button type="button" size="sm" variant="ghost" onClick={() => setDx((x) => x.filter((_, j) => j !== i))}>Quitar</Button>
                        </div>
                    ))}
                    <button type="button" className="text-sm font-semibold text-nido-700 hover:underline"
                        onClick={() => setDx((x) => [...x, { code: '', description: '', type: x.some((y) => y.type === 'primary') ? 'secondary' : 'primary' }])}>+ Agregar diagnóstico</button>
                </fieldset>
                {editor.mode === 'correct' && (
                    <Field label="Motivo de la corrección" htmlFor="note-reason" hint="Queda registrado junto a la nota. Al menos 10 caracteres."
                        error={rule?.code === 'MOTIVO_CORRECCION_INSUFICIENTE' ? rule.message : undefined}>
                        <Input id="note-reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: Se descartó neumonía con la radiografía" />
                    </Field>
                )}
                {save.isError && !missing && rule?.code !== 'MOTIVO_CORRECCION_INSUFICIENTE' && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <div className="flex flex-wrap justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    {editor.mode !== 'correct' && <Button type="button" variant="secondary" loading={save.isPending && save.variables === false} onClick={() => save.mutate(false)}>Guardar borrador</Button>}
                    {can('notas.firmar') && <Button type="submit">{editor.mode === 'correct' ? 'Firmar corrección' : 'Firmar nota'}</Button>}
                </div>
            </form>
            <Dialog open={confirmSign} onClose={() => setConfirmSign(false)} title="¿Firmar la nota?">
                <p className="text-sm text-slate-600">Después de firmarla no se podrá modificar; cualquier cambio se hará como una corrección con motivo.</p>
                <div className="mt-4 flex justify-end gap-2">
                    <Button variant="secondary" onClick={() => setConfirmSign(false)}>Revisar otra vez</Button>
                    <Button loading={save.isPending} onClick={() => save.mutate(true)}>Sí, firmar</Button>
                </div>
            </Dialog>
        </Card>
    );
}

const levelStyle = { anormal: 'bg-amber-100 text-amber-900 font-bold', critica: 'bg-red-100 text-red-800 font-bold' };

function VitalsTab({ data }: { data: RecordData }) {
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const queryClient = useQueryClient();
    const [values, setValues] = useState<Record<string, string>>({});
    const [result, setResult] = useState<Finding[] | null>(null);
    const save = useMutation({
        mutationFn: async () => (await api.post<{ data: { findings: Finding[] } }>(`/expediente/${data.patient.id}/signos`, { values })).data.data,
        onSuccess: (r) => { setResult(r.findings); setValues({}); queryClient.invalidateQueries({ queryKey: ['expediente', data.patient.id] }); },
    });
    const rule = ruleError(save.error);
    const fieldErr = (rule?.details.campos ?? {}) as Record<string, string>;

    return (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            {can('signos_vitales.registrar') && (
                <Card className="p-5">
                    <h2 className="flex items-center gap-2 font-bold text-nido-900"><Activity className="size-5" aria-hidden /> Nueva toma</h2>
                    <p className="mt-1 text-sm text-slate-600">Llene solo lo que midió. Debajo de cada campo está el rango normal en adultos.</p>
                    <form className="mt-4 grid grid-cols-2 gap-3" onSubmit={(e) => { e.preventDefault(); setResult(null); save.mutate(); }}>
                        {data.signs.map((s) => (
                            <Field key={s.key} label={`${s.label} (${s.unit})`} htmlFor={`vs-${s.key}`} error={fieldErr[s.key]} hint={s.min !== null ? `Normal: ${s.min}–${s.max}` : undefined}>
                                <Input id={`vs-${s.key}`} type="number" step="any" inputMode="decimal" value={values[s.key] ?? ''} aria-invalid={Boolean(fieldErr[s.key])}
                                    onChange={(e) => { const v = e.target.value; setValues((x) => ({ ...x, [s.key]: v })); }} />
                            </Field>
                        ))}
                        <div className="col-span-2 space-y-3">
                            {save.isError && !Object.keys(fieldErr).length && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                            {result && (result.length === 0 ? <Alert tone="success">Toma guardada. Todos los valores están en rango normal.</Alert> : (
                                <Alert tone="error">Toma guardada con valores fuera de rango: {result.map((f) => `${f.etiqueta} ${f.valor} (${f.nivel === 'critica' ? 'crítico' : 'anormal'})`).join(', ')}. Se notificó para su seguimiento.</Alert>
                            ))}
                            <Button type="submit" className="w-full" loading={save.isPending}>Guardar toma</Button>
                        </div>
                    </form>
                </Card>
            )}
            <Card className="min-w-0">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                    <h2 className="font-bold text-nido-900">Historial</h2>
                    <p className="flex gap-3 text-xs"><span className={cn('rounded px-1.5', levelStyle.anormal)}>Anormal</span><span className={cn('rounded px-1.5', levelStyle.critica)}>Crítico</span></p>
                </div>
                {data.vitals.length === 0 ? <EmptyState title="Sin signos vitales registrados." /> : (
                    <div className="overflow-x-auto" tabIndex={0} role="region" aria-label="Historial de signos vitales">
                        <table className="min-w-full text-sm">
                            <caption className="sr-only">Historial de signos vitales</caption>
                            <thead><tr className="text-left text-xs uppercase text-slate-500">
                                <th scope="col" className="px-4 py-2">Fecha</th>
                                {data.signs.map((s) => <th key={s.key} scope="col" className="px-3 py-2 whitespace-nowrap" title={s.label}>{s.label.split(' ')[0]} <span className="normal-case">({s.unit})</span></th>)}
                            </tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {data.vitals.map((v) => (
                                    <tr key={v.id}>
                                        <td className="whitespace-nowrap px-4 py-2 text-slate-700">{formatDate(v.measured_at, tz, { dateStyle: 'short' })} {formatTime(v.measured_at, tz)}<span className="block text-xs text-slate-500">{v.recorded_by}</span></td>
                                        {data.signs.map((s) => {
                                            const f = v.findings.find((x) => x.signo === s.key);
                                            return <td key={s.key} className="px-3 py-2 tabular-nums"><span className={cn('rounded px-1.5 py-0.5', f && levelStyle[f.nivel])}>{v.values[s.key] ?? '—'}</span></td>;
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </div>
    );
}

function EpisodesTab({ data }: { data: RecordData }) {
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const queryClient = useQueryClient();
    const end = useMutation({
        mutationFn: (id: string) => api.post(`/episodios/${id}/terminar`),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['expediente', data.patient.id] }),
    });
    return (
        <Card>
            {end.isError && <div className="p-4"><Alert tone="error">{errorMessage(end.error)}</Alert></div>}
            {data.episodes.length === 0 ? <EmptyState title="Sin atenciones registradas." /> : (
                <ul className="divide-y divide-slate-100">
                    {data.episodes.map((e) => (
                        <li key={e.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div>
                                <p className="font-semibold text-slate-900">{episodeType[e.type]}{e.bed ? ` · ${e.bed}` : ''}</p>
                                <p className="text-slate-600">{formatDate(e.started_at, tz)}{e.ended_at ? ` al ${formatDate(e.ended_at, tz)}` : ''} · {e.reason}</p>
                            </div>
                            <div className="flex items-center gap-2">
                                {e.status === 'open' ? <Badge tone="ok">Abierta</Badge> : <Badge>Cerrada{e.discharge_type ? ` (${e.discharge_type})` : ''}</Badge>}
                                {e.status === 'open' && e.type === 'outpatient' && can('notas.registrar') && (
                                    <Button size="sm" variant="secondary" loading={end.isPending && end.variables === e.id} onClick={() => end.mutate(e.id)}>Terminar consulta</Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
