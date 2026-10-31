import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pill, ShieldAlert } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors, ruleError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, Select, Spinner, Textarea } from '@/components/ui';
import { ReasonDialog } from '@/pages/lab/LabPage';

export interface Medication { code: string; name: string; presentation: string; route: string }
export interface PrescriptionItem {
    id: string; medication_name: string; dose: string; route: string; frequency: string; duration_days: number | null; instructions: string | null;
    status: 'issued' | 'cancelled'; allergy_override_reason: string | null; cancel_reason: string | null; issued_at: string; prescriber: string | null;
    patient: { id: string; code: string; name: string };
}
interface Finding { allergen: string; severity: string; match: 'directa' | 'familia' }
const severity: Record<string, string> = { mild: 'leve', moderate: 'moderada', severe: 'grave', life_threatening: 'riesgo vital', unknown: 'desconocida' };

/** Pestaña «Recetas» del expediente (módulo prescripciones con validación de alergias). */
export function PrescriptionsTab({ patientId }: { patientId: string }) {
    const { can, session } = useAuth();
    const queryClient = useQueryClient();
    const [creating, setCreating] = useState(false);
    const [cancelling, setCancelling] = useState<PrescriptionItem | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const list = useQuery({ queryKey: ['recetas', patientId], queryFn: async () => (await api.get<{ data: PrescriptionItem[] }>('/recetas', { params: { paciente: patientId } })).data.data });
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['recetas'] });

    return (
        <>
            {notice && <div className="mb-4"><Alert tone="success">{notice}</Alert></div>}
            {can('prescripciones.crear') && !creating && <Button className="mb-4" onClick={() => { setNotice(null); setCreating(true); }}><Pill className="size-4" aria-hidden /> Nueva receta</Button>}
            {creating && <PrescriptionForm patientId={patientId} onClose={() => setCreating(false)} onDone={(t) => { setCreating(false); setNotice(t); refresh(); }} />}
            {list.isLoading ? <Spinner /> : list.isError ? <Alert tone="error">{errorMessage(list.error)}</Alert> : list.data!.length === 0 ? (
                <Card><EmptyState title="Sin recetas." /></Card>
            ) : (
                <ul className="space-y-3">
                    {list.data!.map((p) => (
                        <li key={p.id}>
                            <Card className="flex flex-wrap items-start justify-between gap-3 p-4">
                                <div className="text-sm">
                                    <p className={p.status === 'cancelled' ? 'font-bold text-slate-600 line-through' : 'font-bold text-slate-900'}>{p.medication_name}</p>
                                    <p className="text-slate-700">{p.dose} · {p.route} · {p.frequency}{p.duration_days ? ` · ${p.duration_days} días` : ''}</p>
                                    {p.instructions && <p className="text-slate-600">{p.instructions}</p>}
                                    <p className="text-xs text-slate-600">{formatDate(p.issued_at, session?.hospital.timezone)} · {p.prescriber}</p>
                                    {p.allergy_override_reason && <p className="mt-1 text-xs text-amber-800">Emitida pese a alergia relacionada: {p.allergy_override_reason}</p>}
                                    {p.cancel_reason && <p className="mt-1 text-xs text-red-700">Anulada: {p.cancel_reason}</p>}
                                </div>
                                <div className="flex items-center gap-2">
                                    {p.status === 'issued' ? <Badge tone="ok">Vigente</Badge> : <Badge tone="critical">Anulada</Badge>}
                                    {p.status === 'issued' && can('prescripciones.anular') && <Button size="sm" variant="ghost" className="text-red-700" onClick={() => setCancelling(p)}>Anular</Button>}
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
            {cancelling && <ReasonDialog title={`Anular ${cancelling.medication_name}`} label="Motivo de la anulación" placeholder="Ej.: Se cambió por otro analgésico" confirm="Anular receta" danger
                onClose={() => setCancelling(null)} onSubmit={(reason) => api.post(`/recetas/${cancelling.id}/anular`, { reason })}
                onDone={() => { setNotice(`Se anuló la receta de ${cancelling.medication_name}.`); setCancelling(null); refresh(); }} />}
        </>
    );
}

function PrescriptionForm({ patientId, onClose, onDone }: { patientId: string; onClose: () => void; onDone: (t: string) => void }) {
    const [form, setForm] = useState({ medication_code: '', dose: '', frequency: '', duration_days: '', instructions: '' });
    const [warning, setWarning] = useState<{ findings: Finding[]; message: string } | null>(null);
    const meds = useQuery({ queryKey: ['medicamentos'], queryFn: async () => (await api.get<{ data: Medication[] }>('/medicamentos')).data.data });
    const save = useMutation({
        mutationFn: (override?: string) => api.post(`/expediente/${patientId}/recetas`, { ...form, duration_days: form.duration_days ? Number(form.duration_days) : null, override_reason: override }),
        onSuccess: () => { setWarning(null); onDone(`Receta emitida: ${meds.data?.find((m) => m.code === form.medication_code)?.name}.`); },
        onError: (e) => { const r = ruleError(e); if (r?.code === 'CONFIRMACION_REQUERIDA') setWarning({ findings: r.details.hallazgos as Finding[], message: r.message }); },
    });
    const errors = fieldErrors(save.error);
    const rule = ruleError(save.error);
    const blocked = rule?.code.startsWith('BLOQUEO') ? rule : null;
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };
    const med = meds.data?.find((m) => m.code === form.medication_code);

    return (
        <Card className="mb-6 p-5 ring-2 ring-nido-300">
            <h2 className="font-bold text-nido-900">Nueva receta</h2>
            <p className="mt-1 text-sm text-slate-600">Antes de emitirla, el sistema revisa las alergias del paciente.</p>
            <form className="mt-4 grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); save.mutate(undefined); }}>
                <div className="sm:col-span-2">
                    <Field label="Medicamento" htmlFor="rx-med" error={errors.medication_code} hint={med ? `${med.presentation} · vía ${med.route}` : 'Medicamentos vigentes hoy en el catálogo del hospital.'}>
                        <Select id="rx-med" value={form.medication_code} onChange={(e) => { set('medication_code')(e); save.reset(); }}>
                            <option value="">Seleccione…</option>
                            {meds.data?.map((m) => <option key={m.code} value={m.code}>{m.name}</option>)}
                        </Select>
                    </Field>
                </div>
                {blocked && (
                    <div className="sm:col-span-2" role="alert">
                        <div className="flex gap-3 rounded-lg bg-red-50 p-4 text-sm text-red-900 ring-2 ring-red-300">
                            <ShieldAlert className="size-6 shrink-0" aria-hidden />
                            <div><p className="font-bold">Receta bloqueada por alergia</p><p>{blocked.message}</p></div>
                        </div>
                    </div>
                )}
                <Field label="Dosis" htmlFor="rx-dose" error={errors.dose} hint="Ej.: 500 mg, 1 tableta"><Input id="rx-dose" value={form.dose} onChange={set('dose')} /></Field>
                <Field label="Frecuencia" htmlFor="rx-freq" error={errors.frequency} hint="Ej.: Cada 8 horas"><Input id="rx-freq" value={form.frequency} onChange={set('frequency')} /></Field>
                <Field label="Duración (días, opcional)" htmlFor="rx-days" error={errors.duration_days}><Input id="rx-days" type="number" min={1} max={365} value={form.duration_days} onChange={set('duration_days')} /></Field>
                <Field label="Indicaciones (opcional)" htmlFor="rx-ins" error={errors.instructions}><Input id="rx-ins" value={form.instructions} onChange={set('instructions')} placeholder="Ej.: Tomar con alimentos" /></Field>
                {save.isError && !blocked && !warning && Object.keys(errors).length === 0 && <div className="sm:col-span-2"><Alert tone="error">{errorMessage(save.error)}</Alert></div>}
                <div className="flex justify-end gap-2 sm:col-span-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={save.isPending && !warning} disabled={!form.medication_code}>Emitir receta</Button>
                </div>
            </form>
            <AllergyWarning warning={warning} pending={save.isPending} error={warning && rule?.code === 'CONFIRMACION_REQUERIDA' && save.variables ? rule.message : undefined}
                onClose={() => setWarning(null)} onConfirm={(reason) => save.mutate(reason)} />
        </Card>
    );
}

function AllergyWarning({ warning, pending, error, onClose, onConfirm }: { warning: { findings: Finding[]; message: string } | null; pending: boolean; error?: string; onClose: () => void; onConfirm: (r: string) => void }) {
    const [reason, setReason] = useState('');
    return (
        <Dialog open={warning !== null} onClose={onClose} title="Alergia relacionada">
            <div className="space-y-4">
                <ul className="space-y-1 text-sm">
                    {warning?.findings.map((f, i) => <li key={i} className="rounded bg-amber-50 px-3 py-2 text-amber-900 ring-1 ring-amber-200">Alergia <strong>{severity[f.severity] ?? f.severity}</strong> a <strong>{f.allergen}</strong>{f.match === 'familia' ? ' (misma familia)' : ''}</li>)}
                </ul>
                <p className="text-sm text-slate-600">{warning?.message}</p>
                {error && <Alert tone="error">{error}</Alert>}
                <Field label="Motivo para recetar de todos modos" htmlFor="rx-override"><Textarea id="rx-override" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: Tolera con protector gástrico" /></Field>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>Elegir otro medicamento</Button>
                    <Button loading={pending} onClick={() => onConfirm(reason)}>Emitir de todos modos</Button>
                </div>
            </div>
        </Dialog>
    );
}
