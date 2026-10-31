import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BedDouble, Plus, UserPlus } from 'lucide-react';
import { AdmissionDialog, type PresetBed } from '@/modules/admissions/AdmissionDialog';
import { AdmissionTrace } from '@/modules/admissions/AdmissionTrace';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/cn';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, PageHeader, Select, Spinner, Textarea } from '@/components/ui';

interface BedItem {
    id: string;
    code: string;
    type: 'standard' | 'uci' | 'aislamiento' | 'pediatrica';
    status: 'available' | 'occupied' | 'maintenance' | 'inactive';
    status_reason: string | null;
    next: ('available' | 'maintenance' | 'inactive')[];
    occupant: { episode_id: string; since: string; reason: string; patient: { id: string; code: string; name: string } } | null;
}
interface WardItem { id: string; code: string; name: string; location: string; capacity: number | null; beds: BedItem[] }

export const bedStatus = {
    available: { label: 'Disponible', className: 'bg-green-50 text-green-900 ring-green-300', dot: 'bg-green-600' },
    occupied: { label: 'Ocupada', className: 'bg-nido-50 text-nido-900 ring-nido-300', dot: 'bg-nido-700' },
    maintenance: { label: 'Mantenimiento', className: 'bg-amber-50 text-amber-900 ring-amber-300', dot: 'bg-amber-500' },
    inactive: { label: 'Inactiva', className: 'bg-slate-100 text-slate-700 ring-slate-300', dot: 'bg-slate-500' },
} as const;
const bedType: Record<BedItem['type'], string> = { standard: 'General', uci: 'UCI', aislamiento: 'Aislamiento', pediatrica: 'Pediátrica' };
const statusAction = { available: 'Habilitar', maintenance: 'Enviar a mantenimiento', inactive: 'Desactivar' } as const;

/** Mapa de camas (módulos catálogo de salas y camas, y traslados y altas): cada cama con su color y sus acciones. */
export function BedsPage() {
    const { can } = useAuth();
    const [selected, setSelected] = useState<{ bed: BedItem; ward: WardItem } | null>(null);
    const [newWard, setNewWard] = useState(false);
    const [newBed, setNewBed] = useState<WardItem | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [filter, setFilter] = useState('');
    // Módulo admisión: null = cerrado; 'libre' = elegir cama en el asistente; o la cama desde la que se abrió.
    const [admit, setAdmit] = useState<PresetBed | 'libre' | null>(null);
    const map = useQuery({ queryKey: ['camas'], queryFn: async () => (await api.get<{ data: WardItem[] }>('/camas')).data.data });

    const wards = map.data ?? [];
    const visible = filter ? wards.map((w) => ({ ...w, beds: w.beds.filter((b) => b.status === filter) })) : wards;
    const done = (text: string) => { setSelected(null); setNewWard(false); setNewBed(null); setAdmit(null); setNotice(text); };

    return (
        <>
            <PageHeader title="Camas" description="Toque una cama para ver quién la ocupa o cambiar su estado."
                actions={<>
                    {can('admisiones.gestionar') && <Button onClick={() => setAdmit('libre')}><UserPlus className="size-4" aria-hidden /> Admitir paciente</Button>}
                    {can('camas.gestionar') && <Button variant={can('admisiones.gestionar') ? 'secondary' : 'primary'} onClick={() => setNewWard(true)}><Plus className="size-4" aria-hidden /> Nueva sala</Button>}
                </>} />

            <div className="mb-4 flex flex-wrap items-center gap-2" role="group" aria-label="Filtrar por estado">
                <button onClick={() => setFilter('')} className={cn('rounded-full px-3 py-1 text-sm font-semibold ring-1', filter === '' ? 'bg-nido-700 text-white ring-nido-700' : 'bg-white text-slate-700 ring-slate-300')}>Todas</button>
                {(Object.keys(bedStatus) as (keyof typeof bedStatus)[]).map((s) => (
                    <button key={s} onClick={() => setFilter(s)} aria-pressed={filter === s}
                        className={cn('inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold ring-1', filter === s ? 'bg-nido-700 text-white ring-nido-700' : 'bg-white text-slate-700 ring-slate-300')}>
                        <span className={cn('size-2.5 rounded-full', bedStatus[s].dot)} aria-hidden /> {bedStatus[s].label}
                        <span className="tabular-nums">({wards.flatMap((w) => w.beds).filter((b) => b.status === s).length})</span>
                    </button>
                ))}
            </div>

            {notice && <div className="mb-4"><Alert tone="success">{notice}</Alert></div>}
            {map.isLoading ? <Spinner /> : map.isError ? <Alert tone="error">{errorMessage(map.error)}</Alert> : wards.length === 0 ? (
                <Card><EmptyState title="Todavía no hay salas.">{can('camas.gestionar') ? 'Cree la primera con «Nueva sala».' : 'Pida al administrador que las registre.'}</EmptyState></Card>
            ) : (
                <div className="space-y-4">
                    {visible.map((ward) => (
                        <Card key={ward.id} className="p-5">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h2 className="font-bold text-nido-900">{ward.name} <span className="font-mono text-xs text-slate-500">{ward.code}</span></h2>
                                    <p className="text-sm text-slate-600">{ward.location} · {wards.find((w) => w.id === ward.id)!.beds.length} camas{ward.capacity ? ` de ${ward.capacity}` : ''}</p>
                                </div>
                                {can('camas.gestionar') && <Button size="sm" variant="ghost" onClick={() => setNewBed(ward)}><Plus className="size-4" aria-hidden /> Agregar cama</Button>}
                            </div>
                            {ward.beds.length === 0 ? <p className="mt-3 text-sm text-slate-500">{filter ? 'Ninguna cama en ese estado.' : 'Sala sin camas.'}</p> : (
                                <ul className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                                    {ward.beds.map((bed) => (
                                        <li key={bed.id}>
                                            <button onClick={() => setSelected({ bed, ward })}
                                                className={cn('flex h-full w-full flex-col items-start rounded-lg p-3 text-left ring-1 transition hover:ring-2', bedStatus[bed.status].className)}
                                                aria-label={`Cama ${bed.code}, ${bedStatus[bed.status].label}${bed.occupant ? `, ${bed.occupant.patient.name}` : ''}`}>
                                                <span className="flex w-full items-center justify-between gap-1">
                                                    <span className="flex items-center gap-1.5 font-bold"><BedDouble className="size-4" aria-hidden />{bed.code}</span>
                                                    {bed.type !== 'standard' && <span className="text-[0.7rem] font-bold uppercase">{bedType[bed.type]}</span>}
                                                </span>
                                                <span className="mt-1 text-xs font-semibold">{bedStatus[bed.status].label}</span>
                                                {bed.occupant && <span className="mt-1 line-clamp-2 text-sm font-semibold">{bed.occupant.patient.name}</span>}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    ))}
                </div>
            )}

            {selected && <BedDialog bed={selected.bed} ward={selected.ward} wards={wards} onClose={() => setSelected(null)} onDone={done}
                onAdmit={() => { setAdmit({ id: selected.bed.id, code: selected.bed.code, type: selected.bed.type, ward: { id: selected.ward.id, name: selected.ward.name } }); setSelected(null); }} />}
            {admit && <AdmissionDialog preset={admit === 'libre' ? undefined : admit} wards={wards} onClose={() => setAdmit(null)} onDone={done} />}
            {newWard && <NewWardDialog onClose={() => setNewWard(false)} onDone={done} />}
            {newBed && <NewBedDialog ward={newBed} onClose={() => setNewBed(null)} onDone={done} />}
        </>
    );
}

function BedDialog({ bed, ward, wards, onClose, onDone, onAdmit }: { bed: BedItem; ward: WardItem; wards: WardItem[]; onClose: () => void; onDone: (t: string) => void; onAdmit: () => void }) {
    const { can, session } = useAuth();
    const queryClient = useQueryClient();
    const [mode, setMode] = useState<'view' | 'transfer' | 'discharge' | 'status'>('view');
    const [target, setTarget] = useState('');
    const [icu, setIcu] = useState(false);
    const [status, setStatus] = useState<string>(bed.next[0] ?? '');
    const [dischargeType, setDischargeType] = useState('medica');
    const [reason, setReason] = useState('');
    const free = useMemo(() => wards.flatMap((w) => w.beds.filter((b) => b.status === 'available' && b.id !== bed.id && (!icu || b.type === 'uci'))
        .map((b) => ({ ...b, label: `${w.name} · ${b.code}${b.type !== 'standard' ? ` (${bedType[b.type]})` : ''}` }))), [wards, bed.id, icu]);

    const run = useMutation({
        mutationFn: async () => {
            if (mode === 'transfer') return api.post(`/internamientos/${bed.occupant!.episode_id}/traslado`, { bed_id: target, reason, require_icu: icu });
            if (mode === 'discharge') return api.post(`/internamientos/${bed.occupant!.episode_id}/alta`, { type: dischargeType, reason });
            return api.patch(`/camas/${bed.id}/estado`, { status, reason });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['camas'] });
            queryClient.invalidateQueries({ queryKey: ['ocupacion'] });
            const name = bed.occupant?.patient.name;
            onDone(mode === 'transfer' ? `${name} fue trasladado a ${free.find((b) => b.id === target)?.label}.`
                : mode === 'discharge' ? `Alta registrada para ${name}. La cama ${bed.code} quedó en mantenimiento para limpieza.`
                    : `La cama ${bed.code} quedó «${bedStatus[status as keyof typeof bedStatus].label}».`);
        },
    });
    const errors = fieldErrors(run.error);

    return (
        <Dialog open onClose={onClose} title={`Cama ${bed.code} · ${ward.name}`}>
            <div className="space-y-4">
                <div className="flex flex-wrap gap-2">
                    <Badge tone={bed.status === 'available' ? 'ok' : bed.status === 'maintenance' ? 'warn' : bed.status === 'occupied' ? 'nido' : 'neutral'}>{bedStatus[bed.status].label}</Badge>
                    <Badge>{bedType[bed.type]}</Badge>
                </div>
                {bed.occupant ? (
                    <div className="rounded-lg bg-slate-50 p-3 text-sm ring-1 ring-slate-200">
                        <Link to={`/pacientes/${bed.occupant.patient.id}`} className="font-bold text-nido-800 hover:underline">{bed.occupant.patient.name}</Link>
                        <p className="text-slate-600">{bed.occupant.patient.code} · desde el {formatDate(bed.occupant.since, session?.hospital.timezone)}</p>
                        <p className="text-slate-600">Motivo de ingreso: {bed.occupant.reason}</p>
                        <details className="mt-2">
                            <summary className="cursor-pointer font-semibold text-nido-700">Ver admisión y trazabilidad</summary>
                            <div className="mt-2"><AdmissionTrace admissionId={bed.occupant.episode_id} /></div>
                        </details>
                    </div>
                ) : bed.status_reason && <p className="text-sm text-slate-600">Último cambio: {bed.status_reason}</p>}

                {run.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(run.error)}</Alert>}

                {mode === 'view' && (
                    <div className="flex flex-wrap gap-2">
                        {bed.occupant && can('traslados.gestionar') && <>
                            <Button onClick={() => setMode('transfer')}>Trasladar a otra cama</Button>
                            <Button variant="secondary" onClick={() => setMode('discharge')}>Dar de alta</Button>
                        </>}
                        {!bed.occupant && can('camas.gestionar') && bed.next.length > 0 && <Button variant="secondary" onClick={() => setMode('status')}>Cambiar estado</Button>}
                        {!bed.occupant && bed.status === 'available' && (can('admisiones.gestionar')
                            ? <Button onClick={onAdmit}><UserPlus className="size-4" aria-hidden /> Admitir paciente</Button>
                            : <p className="text-sm text-slate-600">Cama lista para un nuevo ingreso.</p>)}
                    </div>
                )}

                {mode !== 'view' && (
                    <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); run.mutate(); }}>
                        {mode === 'transfer' && <>
                            <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="accent-nido-700" checked={icu} onChange={(e) => { setIcu(e.target.checked); setTarget(''); }} /> Necesita cama de UCI</label>
                            <Field label="Cama destino" htmlFor="tr-bed" error={errors.bed_id} hint={free.length === 0 ? 'No hay camas disponibles con ese requisito.' : `${free.length} camas disponibles.`}>
                                <Select id="tr-bed" value={target} onChange={(e) => setTarget(e.target.value)}>
                                    <option value="">Seleccione…</option>
                                    {free.map((b) => <option key={b.id} value={b.id}>{b.label}</option>)}
                                </Select>
                            </Field>
                        </>}
                        {mode === 'discharge' && (
                            <Field label="Tipo de alta" htmlFor="dc-type">
                                <Select id="dc-type" value={dischargeType} onChange={(e) => setDischargeType(e.target.value)}>
                                    <option value="medica">Alta médica</option><option value="voluntaria">Alta voluntaria</option>
                                    <option value="referencia">Referencia a otro centro</option><option value="defuncion">Defunción</option>
                                </Select>
                            </Field>
                        )}
                        {mode === 'status' && (
                            <Field label="Nuevo estado" htmlFor="st-status">
                                <Select id="st-status" value={status} onChange={(e) => setStatus(e.target.value)}>
                                    {bed.next.map((s) => <option key={s} value={s}>{statusAction[s]}</option>)}
                                </Select>
                            </Field>
                        )}
                        <Field label={mode === 'discharge' ? 'Resumen del alta' : 'Motivo'} htmlFor="mv-reason" error={errors.reason}
                            hint={mode === 'discharge' ? 'Indicaciones y seguimiento, p. ej. «Control en consulta externa en 7 días».' : undefined}>
                            <Textarea id="mv-reason" value={reason} onChange={(e) => setReason(e.target.value)} />
                        </Field>
                        {mode === 'discharge' && dischargeType === 'defuncion' && <Alert tone="error">La ficha del paciente quedará marcada como «Fallecido».</Alert>}
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="secondary" onClick={() => { setMode('view'); run.reset(); }}>Volver</Button>
                            <Button type="submit" loading={run.isPending} disabled={mode === 'transfer' && !target} variant={mode === 'discharge' && dischargeType === 'defuncion' ? 'danger' : 'primary'}>
                                {mode === 'transfer' ? 'Trasladar' : mode === 'discharge' ? 'Registrar alta' : 'Guardar'}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </Dialog>
    );
}

function NewWardDialog({ onClose, onDone }: { onClose: () => void; onDone: (t: string) => void }) {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({ code: '', name: '', location: '', capacity: '' });
    const save = useMutation({
        mutationFn: () => api.post('/salas', { ...form, capacity: form.capacity ? Number(form.capacity) : null }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['camas'] }); onDone(`Se creó la sala ${form.name}. Ahora agréguele camas.`); },
    });
    const errors = fieldErrors(save.error);
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };
    return (
        <Dialog open onClose={onClose} title="Nueva sala">
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <Field label="Nombre" htmlFor="w-name" error={errors.name}><Input id="w-name" value={form.name} onChange={set('name')} placeholder="Ej.: Medicina de mujeres" /></Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Código" htmlFor="w-code" error={errors.code} hint="Corto, p. ej. MED-2."><Input id="w-code" className="uppercase" value={form.code} onChange={set('code')} /></Field>
                    <Field label="Capacidad (opcional)" htmlFor="w-cap" error={errors.capacity} hint="Máximo de camas."><Input id="w-cap" type="number" min={1} value={form.capacity} onChange={set('capacity')} /></Field>
                </div>
                <Field label="Ubicación" htmlFor="w-loc" error={errors.location}><Input id="w-loc" value={form.location} onChange={set('location')} placeholder="Ej.: Segundo nivel, ala este" /></Field>
                <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button><Button type="submit" loading={save.isPending}>Crear sala</Button></div>
            </form>
        </Dialog>
    );
}

function NewBedDialog({ ward, onClose, onDone }: { ward: WardItem; onClose: () => void; onDone: (t: string) => void }) {
    const queryClient = useQueryClient();
    const [code, setCode] = useState('');
    const [type, setType] = useState('standard');
    const save = useMutation({
        mutationFn: () => api.post(`/salas/${ward.id}/camas`, { code, type }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['camas'] }); onDone(`Se agregó la cama ${code.toUpperCase()} a ${ward.name}.`); },
    });
    const errors = fieldErrors(save.error);
    return (
        <Dialog open onClose={onClose} title={`Agregar cama a ${ward.name}`}>
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <Field label="Código de la cama" htmlFor="b-code" error={errors.code} hint="Como está rotulada, p. ej. 207 o U-4."><Input id="b-code" className="uppercase" value={code} onChange={(e) => setCode(e.target.value)} /></Field>
                <Field label="Tipo" htmlFor="b-type">
                    <Select id="b-type" value={type} onChange={(e) => setType(e.target.value)}>
                        {Object.entries(bedType).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </Select>
                </Field>
                <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button><Button type="submit" loading={save.isPending}>Agregar cama</Button></div>
            </form>
        </Dialog>
    );
}
