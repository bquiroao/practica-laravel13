import { useState, type FormEvent } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BedDouble, ClipboardList, Search } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors, ruleError } from '@/lib/api';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/cn';
import { Alert, Badge, Button, Card, Dialog, Field, Input, Select, Spinner, Textarea } from '@/components/ui';
import { bedTypeLabels, type BedType } from './AdmissionDialog';

/*
 * Mejora del módulo admisión: la lista de espera de cama. Se ordena el ingreso, el paciente espera y, cuando hay una
 * cama compatible, se le asigna: ahí se crea el internamiento. La espera alimenta el indicador «Tiempo de espera de cama».
 */

interface Order {
    id: string; status: 'pendiente' | 'asignada' | 'cancelada'; status_label: string; patient: { id: string; code: string; name: string };
    bed_type: BedType; bed_type_label: string; isolation: boolean; source: string; source_label: string; priority: 'urgente' | 'programada';
    priority_label: string; reason: string; ordered_at: string; ordered_by: string | null; waiting_minutes: number;
}
interface Patient { id: string; code: string; full_name: string; age: number; status: string }
interface FreeBed { id: string; code: string; type_label: string; ward: { id: string; name: string; location: string } }
interface Admission { bed: { code: string; ward: { name: string } } }

const sources = { emergencia: 'Emergencia', consulta_externa: 'Consulta externa', referencia: 'Referencia de otro centro' } as const;

export function waited(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h === 0 ? `${m} min` : `${h} h ${m} min`;
}

export function AdmissionOrdersPanel({ onDone }: { onDone: (message: string) => void }) {
    const { can, session } = useAuth();
    const manage = can('admisiones.gestionar');
    const [ordering, setOrdering] = useState(false);
    const [assigning, setAssigning] = useState<Order | null>(null);
    const [cancelling, setCancelling] = useState<Order | null>(null);
    const orders = useQuery({
        queryKey: ['admision', 'ordenes'],
        queryFn: async () => (await api.get<{ data: Order[] }>('/admisiones/ordenes')).data.data,
        refetchInterval: 60_000,
    });
    const list = orders.data ?? [];

    return (
        <Card className="mb-6">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                <h2 className="flex items-center gap-2 font-bold text-nido-900">
                    <ClipboardList className="size-5" aria-hidden /> Esperando cama
                    {list.length > 0 && <Badge tone="warn">{list.length}</Badge>}
                </h2>
                {manage && <Button size="sm" variant="secondary" onClick={() => setOrdering(true)}>Nueva orden de ingreso</Button>}
            </div>
            {orders.isLoading ? <Spinner /> : orders.isError ? <div className="p-5"><Alert tone="error">{errorMessage(orders.error)}</Alert></div> : list.length === 0 ? (
                <p className="px-5 py-4 text-sm text-slate-600">Nadie espera cama. Una orden de ingreso deja al paciente en espera hasta que haya una cama compatible.</p>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {list.map((o) => (
                        <li key={o.id} className="flex flex-wrap items-start justify-between gap-3 px-5 py-3 text-sm">
                            <div className="min-w-0">
                                <p className="font-semibold text-slate-900">
                                    {o.patient.name} <span className="font-mono text-xs font-normal text-slate-500">{o.patient.code}</span>
                                </p>
                                <p className="text-slate-600">{o.bed_type_label}{o.isolation ? ' con aislamiento' : ''} · {o.source_label} · {o.reason}</p>
                                <p className="text-xs text-slate-500">Ordenada a las {formatTime(o.ordered_at, session?.hospital.timezone)} por {o.ordered_by} · espera {waited(o.waiting_minutes)}</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge tone={o.priority === 'urgente' ? 'critical' : 'neutral'}>{o.priority_label}</Badge>
                                {manage && <Button size="sm" onClick={() => setAssigning(o)}><BedDouble className="size-4" aria-hidden /> Asignar cama</Button>}
                                {manage && <Button size="sm" variant="ghost" onClick={() => setCancelling(o)}>Cancelar</Button>}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {ordering && <OrderDialog onClose={() => setOrdering(false)} onDone={(m) => { setOrdering(false); onDone(m); }} />}
            {assigning && <AssignBedDialog order={assigning} onClose={() => setAssigning(null)} onDone={(m) => { setAssigning(null); onDone(m); }} />}
            {cancelling && <CancelDialog order={cancelling} onClose={() => setCancelling(null)} onDone={(m) => { setCancelling(null); onDone(m); }} />}
        </Card>
    );
}

function OrderDialog({ onClose, onDone }: { onClose: () => void; onDone: (m: string) => void }) {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [term, setTerm] = useState('');
    const [patient, setPatient] = useState<Patient | null>(null);
    const [form, setForm] = useState({ bed_type: 'standard' as BedType, isolation: false, source: 'emergencia' as keyof typeof sources, priority: 'urgente', reason: '' });
    const patients = useQuery({
        queryKey: ['admision', 'pacientes', term],
        queryFn: async () => (await api.get<{ data: Patient[] }>('/pacientes', { params: { q: term } })).data.data,
        enabled: term.length >= 3,
    });
    const save = useMutation({
        mutationFn: async () => (await api.post<{ data: Order }>('/admisiones/ordenes', { patient_id: patient!.id, ...form })).data.data,
        onSuccess: (o) => { queryClient.invalidateQueries({ queryKey: ['admision', 'ordenes'] }); onDone(`${o.patient.name} quedó en espera de cama ${o.bed_type_label.toLowerCase()}.`); },
    });
    const errors = fieldErrors(save.error);
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };

    return (
        <Dialog open onClose={onClose} title="Nueva orden de ingreso">
            {!patient ? (
                <div className="space-y-3">
                    <form className="flex gap-2" onSubmit={(e: FormEvent) => { e.preventDefault(); setTerm(search.trim()); }}>
                        <Input aria-label="Buscar paciente" placeholder="Nombre, DPI o código" value={search} onChange={(e) => setSearch(e.target.value)} autoFocus />
                        <Button type="submit" variant="secondary"><Search className="size-4" aria-hidden /> Buscar</Button>
                    </form>
                    {patients.isFetching && <Spinner />}
                    <ul className="space-y-1">
                        {patients.data?.map((p) => (
                            <li key={p.id}>
                                <button type="button" disabled={p.status !== 'active'} onClick={() => setPatient(p)}
                                    className="w-full rounded-lg px-3 py-2 text-left text-sm ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-50">
                                    <strong>{p.full_name}</strong> <span className="font-mono text-xs text-slate-500">{p.code}</span> · {p.age} años
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : (
                <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                    <p className="text-sm">Paciente: <strong>{patient.full_name}</strong> <button type="button" className="text-nido-700 underline" onClick={() => setPatient(null)}>cambiar</button></p>
                    {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Tipo de cama" htmlFor="ord-type" error={errors.bed_type}>
                            <Select id="ord-type" value={form.bed_type} onChange={set('bed_type')}>
                                {Object.entries(bedTypeLabels).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                            </Select>
                        </Field>
                        <Field label="Prioridad" htmlFor="ord-priority" error={errors.priority}>
                            <Select id="ord-priority" value={form.priority} onChange={set('priority')}>
                                <option value="urgente">Urgente</option><option value="programada">Programada</option>
                            </Select>
                        </Field>
                        <Field label="Origen" htmlFor="ord-source" error={errors.source}>
                            <Select id="ord-source" value={form.source} onChange={set('source')}>
                                {Object.entries(sources).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                            </Select>
                        </Field>
                        <label className="flex items-center gap-2 self-end pb-2 text-sm">
                            <input type="checkbox" className="accent-nido-700" checked={form.isolation} onChange={(e) => setForm((f) => ({ ...f, isolation: e.target.checked }))} />
                            Necesita aislamiento
                        </label>
                    </div>
                    <Field label="Motivo del ingreso" htmlFor="ord-reason" error={errors.reason} hint="Ej.: «Neumonía, requiere oxígeno».">
                        <Textarea id="ord-reason" value={form.reason} onChange={set('reason')} maxLength={500} aria-invalid={!!errors.reason} />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                        <Button type="submit" loading={save.isPending} disabled={form.reason.trim().length < 5}>Ordenar ingreso</Button>
                    </div>
                </form>
            )}
        </Dialog>
    );
}

function AssignBedDialog({ order, onClose, onDone }: { order: Order; onClose: () => void; onDone: (m: string) => void }) {
    const queryClient = useQueryClient();
    const [bedId, setBedId] = useState('');
    const requirement = { bed_type: order.bed_type, isolation: order.isolation ? 1 : 0 };
    const beds = useQuery({
        queryKey: ['camas-disponibles', requirement],
        queryFn: async () => (await api.get<{ data: FreeBed[]; total: number }>('/camas-disponibles', { params: requirement })).data,
    });
    const assign = useMutation({
        mutationFn: async () => (await api.post<{ data: Admission }>(`/admisiones/ordenes/${order.id}/asignar`, { bed_id: bedId })).data.data,
        onSuccess: (a) => {
            ['admision', 'camas', 'ocupacion', 'camas-disponibles'].forEach((k) => queryClient.invalidateQueries({ queryKey: [k] }));
            onDone(`${order.patient.name} quedó internado en ${a.bed.ward.name}, cama ${a.bed.code}, después de esperar ${waited(order.waiting_minutes)}.`);
        },
        onError: (e) => { if (ruleError(e)?.code === 'CAMA_YA_ASIGNADA') { setBedId(''); beds.refetch(); } },
    });

    return (
        <Dialog open onClose={onClose} title={`Cama para ${order.patient.name}`}>
            <div className="space-y-4 text-sm">
                <p className="text-slate-600">Necesita: {order.bed_type_label.toLowerCase()}{order.isolation ? ', con aislamiento' : ''}. Espera desde hace {waited(order.waiting_minutes)}.</p>
                {assign.isError && <Alert tone="error">{errorMessage(assign.error)}</Alert>}
                {beds.isLoading ? <Spinner /> : beds.data!.data.length === 0 ? (
                    <Alert tone="info">No hay camas compatibles libres ahora. El paciente sigue en la lista de espera.</Alert>
                ) : (
                    <fieldset className="space-y-2">
                        <legend className="mb-1 font-semibold text-slate-700">Camas disponibles ({beds.data!.total})</legend>
                        {beds.data!.data.map((b) => (
                            <label key={b.id} className={cn('flex items-center gap-3 rounded-lg px-3 py-2 ring-1', bedId === b.id ? 'bg-nido-50 ring-nido-400' : 'ring-slate-200')}>
                                <input type="radio" name="assign-bed" className="accent-nido-700" checked={bedId === b.id} onChange={() => setBedId(b.id)} />
                                <span><strong>{b.ward.name}, cama {b.code}</strong> · {b.type_label}{b.ward.location ? ` · ${b.ward.location}` : ''}</span>
                            </label>
                        ))}
                    </fieldset>
                )}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cerrar</Button>
                    <Button type="button" loading={assign.isPending} disabled={!bedId} onClick={() => assign.mutate()}>Asignar e internar</Button>
                </div>
            </div>
        </Dialog>
    );
}

function CancelDialog({ order, onClose, onDone }: { order: Order; onClose: () => void; onDone: (m: string) => void }) {
    const queryClient = useQueryClient();
    const [reason, setReason] = useState('');
    const cancel = useMutation({
        mutationFn: async () => api.post(`/admisiones/ordenes/${order.id}/cancelar`, { reason }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['admision', 'ordenes'] }); onDone(`Se canceló la orden de ingreso de ${order.patient.name}.`); },
    });
    const errors = fieldErrors(cancel.error);

    return (
        <Dialog open onClose={onClose} title="Cancelar orden de ingreso">
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); cancel.mutate(); }}>
                <p className="text-sm text-slate-600">{order.patient.name} sale de la lista de espera. Queda registrado en la bitácora.</p>
                {cancel.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(cancel.error)}</Alert>}
                <Field label="Motivo" htmlFor="ord-cancel" error={errors.reason} hint="Ej.: «Mejoró y se fue a casa».">
                    <Input id="ord-cancel" value={reason} onChange={(e) => setReason(e.target.value)} maxLength={300} aria-invalid={!!errors.reason} autoFocus />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Volver</Button>
                    <Button type="submit" variant="danger" loading={cancel.isPending} disabled={reason.trim().length < 5}>Cancelar orden</Button>
                </div>
            </form>
        </Dialog>
    );
}
