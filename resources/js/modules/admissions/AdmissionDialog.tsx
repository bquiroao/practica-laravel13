import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, BedDouble, Search } from 'lucide-react';
import { api, errorMessage, fieldErrors, ruleError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { Alert, Button, Dialog, Field, Input, Select, Spinner, Textarea } from '@/components/ui';

/*
 * Módulo admisión hospitalaria y asignación de cama: asistente en tres pasos (paciente, lo que necesita y cama) más la
 * confirmación. Si se abre desde una cama, esa cama queda elegida y solo se comprueba que sea compatible.
 */

export type BedType = 'standard' | 'uci' | 'aislamiento' | 'pediatrica';
export interface PresetBed { id: string; code: string; type: BedType; ward: { id: string; name: string } }
interface Patient { id: string; code: string; full_name: string; age: number; status: string }
interface FreeBed { id: string; code: string; type: BedType; type_label: string; ward: { id: string; code: string; name: string; location: string } }
interface Admission { id: string; patient: { code: string }; bed: { code: string; ward: { name: string } } }

export const bedTypeLabels: Record<BedType, string> = { standard: 'General', uci: 'UCI', aislamiento: 'Aislamiento', pediatrica: 'Pediátrica' };
const sources = { emergencia: 'Emergencia', consulta_externa: 'Consulta externa', referencia: 'Referencia de otro centro' } as const;
/** Cambio preparado de la semana 12: avisar cuando quedan pocas camas para ese requerimiento. */
const FEW_BEDS = 2;

export function AdmissionDialog({ preset, wards, onClose, onDone }: {
    preset?: PresetBed;
    wards: { id: string; name: string }[];
    onClose: () => void;
    onDone: (message: string) => void;
}) {
    const queryClient = useQueryClient();
    const [step, setStep] = useState<1 | 2 | 3 | 4>(1);
    const [search, setSearch] = useState('');
    const [term, setTerm] = useState('');
    const [patient, setPatient] = useState<Patient | null>(null);
    const [form, setForm] = useState({
        source: 'emergencia' as keyof typeof sources,
        bedType: (preset?.type === 'aislamiento' ? 'standard' : preset?.type ?? 'standard') as BedType,
        isolation: preset?.type === 'aislamiento',
        wardId: preset?.ward.id ?? '',
        reason: '',
    });
    const [bedId, setBedId] = useState(preset?.id ?? '');

    const patients = useQuery({
        queryKey: ['admision', 'pacientes', term],
        queryFn: async () => (await api.get<{ data: Patient[] }>('/pacientes', { params: { q: term } })).data.data,
        enabled: term.length >= 3,
    });
    const requirement = { bed_type: form.bedType, isolation: form.isolation ? 1 : 0, ward_id: form.wardId || undefined };
    const beds = useQuery({
        queryKey: ['camas-disponibles', requirement],
        queryFn: async () => (await api.get<{ data: FreeBed[]; total: number }>('/camas-disponibles', { params: requirement })).data,
        enabled: step >= 3,
    });
    const chosen = beds.data?.data.find((b) => b.id === bedId);

    const admit = useMutation({
        mutationFn: async () => (await api.post<{ data: Admission }>('/admisiones', {
            patient_id: patient!.id, bed_id: bedId, bed_type: form.bedType, isolation: form.isolation, ward_id: form.wardId || null,
            source: form.source, reason: form.reason,
        })).data.data,
        onSuccess: (a) => {
            queryClient.invalidateQueries({ queryKey: ['camas'] });
            queryClient.invalidateQueries({ queryKey: ['ocupacion'] });
            onDone(`${patient!.full_name} quedó internado en ${a.bed.ward.name}, cama ${a.bed.code}.`);
        },
        onError: (e) => {
            // CU-05: otra persona tomó la cama: se vuelve a la lista actualizada para elegir otra.
            if (ruleError(e)?.code === 'CAMA_YA_ASIGNADA') {
                setBedId('');
                setStep(3);
                queryClient.invalidateQueries({ queryKey: ['camas-disponibles'] });
            }
        },
    });
    const rule = ruleError(admit.error);
    const errors = fieldErrors(admit.error);

    // Si se cambia lo que necesita el paciente, la cama elegida puede dejar de servir.
    useEffect(() => { if (!preset) setBedId(''); }, [form.bedType, form.isolation, form.wardId, preset]);

    const reasonOk = form.reason.trim().length >= 5;
    const titles = { 1: 'Paso 1 de 3 · Paciente', 2: 'Paso 2 de 3 · Qué necesita', 3: 'Paso 3 de 3 · Cama', 4: 'Confirmar admisión' } as const;

    return (
        <Dialog open onClose={onClose} title={preset ? `Admitir en la cama ${preset.code} · ${preset.ward.name}` : 'Admitir paciente'}>
            <p className="mb-3 text-sm font-semibold text-nido-800" aria-live="polite">{titles[step]}</p>

            {step === 1 && (
                <div className="space-y-3">
                    <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); setTerm(search.trim()); }}>
                        <div className="flex-1">
                            <Field label="Buscar paciente" htmlFor="ad-search" hint="Código, documento o nombre (3 letras o más). Ej.: HN-CEN-000123 o «López».">
                                <Input id="ad-search" value={search} onChange={(e) => setSearch(e.target.value)} autoFocus />
                            </Field>
                        </div>
                        <Button type="submit" variant="secondary" className="mt-7" disabled={search.trim().length < 3} aria-label="Buscar"><Search className="size-4" aria-hidden /></Button>
                    </form>
                    {patients.isFetching && <Spinner label="Buscando…" />}
                    {patients.isError && <Alert tone="error">{errorMessage(patients.error)}</Alert>}
                    {patients.data && patients.data.length === 0 && <p className="text-sm text-slate-600">No se encontró ningún paciente. Revise el código o regístrelo primero en «Pacientes».</p>}
                    {patients.data && patients.data.length > 0 && (
                        <ul className="max-h-60 divide-y divide-slate-100 overflow-y-auto rounded-lg ring-1 ring-slate-200">
                            {patients.data.map((p) => (
                                <li key={p.id}>
                                    <button type="button" disabled={p.status !== 'active'} onClick={() => { setPatient(p); setStep(2); }}
                                        className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-nido-50 disabled:cursor-not-allowed disabled:opacity-60">
                                        <span><span className="font-semibold text-slate-900">{p.full_name}</span> <span className="text-slate-600">· {p.age} años</span></span>
                                        <span className="font-mono text-xs text-slate-600">{p.status === 'active' ? p.code : 'No activo'}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {step === 2 && (
                <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); if (reasonOk) setStep(3); }}>
                    <p className="text-sm text-slate-700">Paciente: <strong>{patient!.full_name}</strong> <span className="font-mono text-xs">{patient!.code}</span></p>
                    <Field label="¿Por dónde llega?" htmlFor="ad-source">
                        <Select id="ad-source" value={form.source} onChange={(e) => setForm((f) => ({ ...f, source: e.target.value as keyof typeof sources }))}>
                            {Object.entries(sources).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                        </Select>
                    </Field>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" className="accent-nido-700" checked={form.isolation} disabled={preset !== undefined}
                            onChange={(e) => setForm((f) => ({ ...f, isolation: e.target.checked }))} />
                        Requiere aislamiento (va a una cama de aislamiento)
                    </label>
                    {!form.isolation && (
                        <Field label="Tipo de cama" htmlFor="ad-type">
                            <Select id="ad-type" value={form.bedType} disabled={preset !== undefined} onChange={(e) => setForm((f) => ({ ...f, bedType: e.target.value as BedType }))}>
                                {(['standard', 'uci', 'pediatrica'] as const).map((t) => <option key={t} value={t}>{bedTypeLabels[t]}</option>)}
                            </Select>
                        </Field>
                    )}
                    {!preset && (
                        <Field label="Sala (opcional)" htmlFor="ad-ward" hint="Déjela en «Cualquier sala» para ver todas las camas compatibles.">
                            <Select id="ad-ward" value={form.wardId} onChange={(e) => setForm((f) => ({ ...f, wardId: e.target.value }))}>
                                <option value="">Cualquier sala</option>
                                {wards.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
                            </Select>
                        </Field>
                    )}
                    <Field label="Motivo del ingreso" htmlFor="ad-reason" hint="Ej.: «Neumonía, requiere oxígeno».">
                        <Textarea id="ad-reason" value={form.reason} onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))} />
                    </Field>
                    <div className="flex justify-between gap-2">
                        <Button type="button" variant="secondary" onClick={() => setStep(1)}>Cambiar paciente</Button>
                        <Button type="submit" disabled={!reasonOk}>{preset ? 'Revisar la cama' : 'Ver camas'}</Button>
                    </div>
                </form>
            )}

            {step === 3 && (
                <div className="space-y-3">
                    {rule?.code === 'CAMA_YA_ASIGNADA' && <Alert tone="error">{rule.message}</Alert>}
                    {beds.isLoading && <Spinner label="Buscando camas compatibles…" />}
                    {beds.isError && <Alert tone="error">{errorMessage(beds.error)}</Alert>}
                    {beds.data && preset && (chosen
                        ? <Alert tone="success">La cama {preset.code} es compatible y está libre.</Alert>
                        : <Alert tone="error">La cama {preset.code} ya no está libre o no es compatible con lo que necesita el paciente. Cierre y elija otra cama en el mapa.</Alert>)}
                    {beds.data && !preset && (
                        <>
                            <p className="text-sm text-slate-700" role="status">
                                Quedan {beds.data.total} cama{beds.data.total === 1 ? '' : 's'} compatible{beds.data.total === 1 ? '' : 's'} (al momento de la consulta).
                            </p>
                            {beds.data.total > 0 && beds.data.total <= FEW_BEDS && (
                                <Alert tone="error"><AlertTriangle className="mr-1 inline size-4" aria-hidden />Pocas camas disponibles para este requerimiento.</Alert>
                            )}
                            {beds.data.total === 0 ? (
                                <Alert>No hay camas libres con ese requerimiento. Pruebe con «Cualquier sala» o avise a supervisión de camas.</Alert>
                            ) : (
                                <fieldset>
                                    <legend className="sr-only">Camas compatibles</legend>
                                    <ul className="grid max-h-64 gap-2 overflow-y-auto sm:grid-cols-2">
                                        {beds.data.data.map((b) => (
                                            <li key={b.id}>
                                                <label className={cn('flex cursor-pointer items-start gap-2 rounded-lg p-3 text-sm ring-1', bedId === b.id ? 'bg-nido-50 ring-2 ring-nido-600' : 'ring-slate-200 hover:bg-slate-50')}>
                                                    <input type="radio" name="ad-bed" className="mt-1 accent-nido-700" checked={bedId === b.id} onChange={() => setBedId(b.id)} />
                                                    <span>
                                                        <span className="flex items-center gap-1 font-bold text-slate-900"><BedDouble className="size-4" aria-hidden />{b.code}</span>
                                                        <span className="block text-slate-700">{b.ward.name} · {b.type_label}</span>
                                                        <span className="block text-xs text-slate-600">{b.ward.location}</span>
                                                    </span>
                                                </label>
                                            </li>
                                        ))}
                                    </ul>
                                </fieldset>
                            )}
                        </>
                    )}
                    <div className="flex justify-between gap-2">
                        <Button type="button" variant="secondary" onClick={() => { admit.reset(); setStep(2); }}>Volver</Button>
                        <Button type="button" disabled={!chosen} onClick={() => { admit.reset(); setStep(4); }}>Continuar</Button>
                    </div>
                </div>
            )}

            {step === 4 && chosen && (
                <div className="space-y-4">
                    <dl className="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1.5 rounded-lg bg-slate-50 p-3 text-sm ring-1 ring-slate-200">
                        <dt className="font-semibold text-slate-600">Paciente</dt><dd>{patient!.full_name} <span className="font-mono text-xs">{patient!.code}</span></dd>
                        <dt className="font-semibold text-slate-600">Llega por</dt><dd>{sources[form.source]}</dd>
                        <dt className="font-semibold text-slate-600">Cama</dt><dd>{chosen.ward.name} · {chosen.code} ({chosen.type_label})</dd>
                        <dt className="font-semibold text-slate-600">Motivo</dt><dd>{form.reason}</dd>
                    </dl>
                    {admit.isError && (
                        <Alert tone="error">
                            {Object.values(errors)[0] ?? errorMessage(admit.error)}
                            {Array.isArray(rule?.details.motivos) && <ul className="mt-1 list-disc pl-5">{(rule!.details.motivos as string[]).map((m) => <li key={m}>{m}</li>)}</ul>}
                            {rule?.details.reintentable === true && <span className="block">Puede intentar de nuevo en unos segundos.</span>}
                        </Alert>
                    )}
                    <div className="flex justify-between gap-2">
                        <Button type="button" variant="secondary" onClick={() => { admit.reset(); setStep(3); }}>Volver</Button>
                        <Button type="button" loading={admit.isPending} onClick={() => admit.mutate()}>Admitir y asignar cama</Button>
                    </div>
                </div>
            )}
        </Dialog>
    );
}
