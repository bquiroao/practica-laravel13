import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Copy, Plus, Star, Trash2 } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { weekdays } from '@/lib/format';
import type { Doctor, ScheduleBlock, Specialty } from '@/modules/clinical/types';
import { Alert, BackLink, Badge, Button, Card, PageHeader, Select, Spinner } from '@/components/ui';

// Horas de 06:00 a 22:00 en medias horas: las únicas que acepta el sistema.
const HOURS = Array.from({ length: 33 }, (_, i) => `${String(6 + Math.floor(i / 2)).padStart(2, '0')}:${i % 2 ? '30' : '00'}`);

/** Detalle del médico: especialidades y horario semanal, editables en la misma pantalla. */
export function DoctorDetailPage() {
    const { id } = useParams();
    const { can } = useAuth();
    const editable = can('medicos.gestionar');
    const queryClient = useQueryClient();
    const doctor = useQuery({ queryKey: ['medico', id], queryFn: async () => (await api.get<{ data: Doctor }>(`/medicos/${id}`)).data.data });
    const update = (d: Doctor) => { queryClient.setQueryData(['medico', id], d); queryClient.invalidateQueries({ queryKey: ['medicos'] }); };

    if (doctor.isLoading) return <Spinner />;
    if (doctor.isError) return <><BackLink to="/medicos">Médicos</BackLink><Alert tone="error">{errorMessage(doctor.error)}</Alert></>;
    const d = doctor.data!;

    return (
        <>
            <BackLink to="/medicos">Médicos</BackLink>
            <PageHeader title={d.name} description={`Colegiado ${d.license_number}`} actions={editable && <StatusSelect doctor={d} onSaved={update} />} />
            <div className="grid gap-6 lg:grid-cols-[1fr_2fr]">
                <SpecialtiesCard doctor={d} editable={editable} onSaved={update} />
                <ScheduleCard doctor={d} editable={editable} onSaved={update} />
            </div>
        </>
    );
}

function StatusSelect({ doctor, onSaved }: { doctor: Doctor; onSaved: (d: Doctor) => void }) {
    const save = useMutation({ mutationFn: async (status: string) => (await api.patch<{ data: Doctor }>(`/medicos/${doctor.id}/estado`, { status })).data.data, onSuccess: onSaved });
    return (
        <div className="flex items-center gap-2">
            <label htmlFor="dr-status" className="text-sm font-semibold text-slate-700">Estado</label>
            <Select id="dr-status" className="w-40" value={doctor.status} disabled={save.isPending} onChange={(e) => save.mutate(e.target.value)}>
                <option value="active">Activo</option><option value="suspended">Suspendido</option><option value="inactive">Inactivo</option>
            </Select>
        </div>
    );
}

function SpecialtiesCard({ doctor, editable, onSaved }: { doctor: Doctor; editable: boolean; onSaved: (d: Doctor) => void }) {
    const [adding, setAdding] = useState('');
    const all = useQuery({ queryKey: ['especialidades'], queryFn: async () => (await api.get<{ data: Specialty[] }>('/especialidades')).data.data, enabled: editable });
    const add = useMutation({
        mutationFn: async () => (await api.post<{ data: Doctor }>(`/medicos/${doctor.id}/especialidades`, { specialty_id: adding, is_primary: doctor.specialties.length === 0 })).data.data,
        onSuccess: (d) => { setAdding(''); onSaved(d); },
    });
    const remove = useMutation({ mutationFn: async (link: string) => (await api.delete<{ data: Doctor }>(`/medicos/${doctor.id}/especialidades/${link}`)).data.data, onSuccess: onSaved });
    const available = (all.data ?? []).filter((s) => !doctor.specialties.some((x) => x.id === s.id));

    return (
        <Card className="p-5">
            <h2 className="font-bold text-nido-900">Especialidades</h2>
            <p className="mt-1 text-sm text-slate-500">Solo se le pueden agendar citas de estas especialidades.</p>
            {(add.isError || remove.isError) && <div className="mt-3"><Alert tone="error">{errorMessage(add.error ?? remove.error)}</Alert></div>}
            <ul className="mt-4 space-y-2">
                {doctor.specialties.length === 0 && <li className="text-sm text-slate-500">Sin especialidades vigentes.</li>}
                {doctor.specialties.map((s) => (
                    <li key={s.link_id} className="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                        <span className="flex items-center gap-2 font-semibold text-slate-800">
                            {s.is_primary && <Star className="size-4 fill-amber-400 text-amber-500" aria-label="Principal" />}{s.name}
                            {s.is_primary && <Badge tone="nido">Principal</Badge>}
                        </span>
                        {editable && (
                            <button onClick={() => remove.mutate(s.link_id)} className="rounded p-1 text-slate-500 hover:bg-red-50 hover:text-red-700" aria-label={`Quitar ${s.name}`}>
                                <Trash2 className="size-4" aria-hidden />
                            </button>
                        )}
                    </li>
                ))}
            </ul>
            {editable && available.length > 0 && (
                <form className="mt-4 flex gap-2" onSubmit={(e) => { e.preventDefault(); add.mutate(); }}>
                    <label htmlFor="add-specialty" className="sr-only">Agregar especialidad</label>
                    <Select id="add-specialty" value={adding} onChange={(e) => setAdding(e.target.value)}>
                        <option value="">Agregar especialidad…</option>
                        {available.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </Select>
                    <Button type="submit" variant="secondary" disabled={!adding} loading={add.isPending}>Agregar</Button>
                </form>
            )}
        </Card>
    );
}

function ScheduleCard({ doctor, editable, onSaved }: { doctor: Doctor; editable: boolean; onSaved: (d: Doctor) => void }) {
    const [blocks, setBlocks] = useState<ScheduleBlock[]>(doctor.schedule);
    const [saved, setSaved] = useState(false);
    useEffect(() => setBlocks(doctor.schedule), [doctor.schedule]);
    const dirty = JSON.stringify(blocks) !== JSON.stringify(doctor.schedule);
    const save = useMutation({
        mutationFn: async () => (await api.put<{ data: Doctor }>(`/medicos/${doctor.id}/horario`, { blocks })).data.data,
        onSuccess: (d) => { onSaved(d); setSaved(true); },
    });

    const change = (index: number, patch: Partial<ScheduleBlock>) => { setSaved(false); setBlocks((b) => b.map((x, i) => (i === index ? { ...x, ...patch } : x))); };
    const add = (weekday: number) => { setSaved(false); setBlocks((b) => [...b, { weekday, starts_at: '08:00', ends_at: '12:00' }]); };
    const remove = (index: number) => { setSaved(false); setBlocks((b) => b.filter((_, i) => i !== index)); };
    // Atajo: copiar el horario del lunes a martes–viernes.
    const copyMonday = () => {
        setSaved(false);
        setBlocks((b) => [...b.filter((x) => x.weekday === 1 || x.weekday > 5), ...[2, 3, 4, 5].flatMap((day) => b.filter((x) => x.weekday === 1).map((x) => ({ ...x, weekday: day })))]);
    };

    return (
        <Card className="p-5">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 className="font-bold text-nido-900">Horario semanal de atención</h2>
                    <p className="mt-1 text-sm text-slate-500">Las citas se ofrecen en bloques de 30 minutos dentro de este horario.</p>
                </div>
                {editable && blocks.some((b) => b.weekday === 1) && (
                    <Button size="sm" variant="ghost" onClick={copyMonday}><Copy className="size-4" aria-hidden /> Copiar lunes a martes–viernes</Button>
                )}
            </div>
            {save.isError && <div className="mt-3"><Alert tone="error">{errorMessage(save.error)}</Alert></div>}
            {saved && !dirty && <div className="mt-3"><Alert tone="success">Horario guardado.</Alert></div>}
            <ul className="mt-4 divide-y divide-slate-100">
                {[1, 2, 3, 4, 5, 6, 7].map((day) => {
                    const items = blocks.map((b, i) => ({ b, i })).filter(({ b }) => b.weekday === day);
                    return (
                        <li key={day} className="grid gap-2 py-3 sm:grid-cols-[7rem_1fr] sm:items-start">
                            <p className="pt-2 text-sm font-semibold text-slate-700">{weekdays[day]}</p>
                            <div className="space-y-2">
                                {items.length === 0 && <p className="pt-2 text-sm text-slate-500">No atiende</p>}
                                {items.map(({ b, i }) => (
                                    <div key={i} className="flex flex-wrap items-center gap-2 text-sm">
                                        {editable ? (
                                            <>
                                                <label className="sr-only" htmlFor={`b${i}-s`}>Entrada {weekdays[day]}</label>
                                                <Select id={`b${i}-s`} className="w-28" value={b.starts_at} onChange={(e) => change(i, { starts_at: e.target.value })}>
                                                    {HOURS.map((h) => <option key={h}>{h}</option>)}
                                                </Select>
                                                <span className="text-slate-500">a</span>
                                                <label className="sr-only" htmlFor={`b${i}-e`}>Salida {weekdays[day]}</label>
                                                <Select id={`b${i}-e`} className="w-28" value={b.ends_at} onChange={(e) => change(i, { ends_at: e.target.value })}>
                                                    {HOURS.map((h) => <option key={h}>{h}</option>)}
                                                </Select>
                                                <button onClick={() => remove(i)} className="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-700" aria-label={`Quitar bloque del ${weekdays[day]}`}>
                                                    <Trash2 className="size-4" aria-hidden />
                                                </button>
                                            </>
                                        ) : <span className="pt-2">{b.starts_at} a {b.ends_at}</span>}
                                    </div>
                                ))}
                                {editable && (
                                    <button onClick={() => add(day)} className="inline-flex items-center gap-1 text-sm font-semibold text-nido-700 hover:underline">
                                        <Plus className="size-4" aria-hidden /> Agregar horario
                                    </button>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
            {editable && (
                <div className="mt-4 flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    {dirty && <span className="text-sm text-amber-700">Hay cambios sin guardar</span>}
                    <Button variant="secondary" disabled={!dirty} onClick={() => setBlocks(doctor.schedule)}>Descartar</Button>
                    <Button disabled={!dirty} loading={save.isPending} onClick={() => save.mutate()}>Guardar horario</Button>
                </div>
            )}
        </Card>
    );
}
