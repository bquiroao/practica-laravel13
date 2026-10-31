import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, CheckCircle2, Search } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, ruleError } from '@/lib/api';
import { addDays, dateIn, formatTime, todayIn, weekdayDate } from '@/lib/format';
import { cn } from '@/lib/cn';
import type { Appointment, Doctor, Patient, Slot, Specialty } from '@/modules/clinical/types';
import { Alert, BackLink, Button, Card, EmptyState, Field, Input, PageHeader, Select, Spinner, Textarea } from '@/components/ui';

const STEPS = ['Paciente', 'Especialidad y día', 'Horario', 'Confirmar'];

/** Agendar una cita en cuatro pasos. Solo se ofrecen horarios realmente libres. */
export function NewAppointmentPage() {
    const { session } = useAuth();
    const tz = session?.hospital.timezone;
    const [params] = useSearchParams();
    const [patient, setPatient] = useState<Patient | null>(null);
    const [specialty, setSpecialty] = useState('');
    const [doctor, setDoctor] = useState('');
    const [date, setDate] = useState(todayIn(tz));
    const [slot, setSlot] = useState<Slot | null>(null);
    const [reason, setReason] = useState('');
    const [done, setDone] = useState<Appointment | null>(null);
    const queryClient = useQueryClient();

    // Si viene de la ficha del paciente, el paso 1 ya está resuelto.
    const preset = params.get('paciente');
    const presetQuery = useQuery({
        queryKey: ['paciente', preset],
        queryFn: async () => (await api.get<{ data: Patient; appointments: unknown[] }>(`/pacientes/${preset}`)).data,
        select: (r) => r.data,
        enabled: Boolean(preset) && !patient,
    });
    const selectedPatient = patient ?? presetQuery.data ?? null;

    const step = !selectedPatient ? 0 : !specialty ? 1 : !slot ? 2 : 3;

    const book = useMutation({
        mutationFn: async () => (await api.post<{ data: Appointment }>('/citas', {
            patient_id: selectedPatient!.id, doctor_id: slot!.doctor_id, specialty_id: specialty, starts_at: slot!.starts_at, reason,
        })).data.data,
        onSuccess: (a) => { setDone(a); queryClient.invalidateQueries({ queryKey: ['citas'] }); },
        onError: (e) => {
            // Si otra persona tomó el horario mientras tanto, se vuelve a elegir con la lista actualizada.
            if (['HORARIO_TOMADO', 'MEDICO_OCUPADO'].includes(ruleError(e)?.code ?? '')) {
                setSlot(null);
                queryClient.invalidateQueries({ queryKey: ['disponibilidad'] });
            }
        },
    });

    if (done) {
        return (
            <Card className="mx-auto max-w-lg p-8 text-center">
                <CheckCircle2 className="mx-auto size-12 text-normal" aria-hidden />
                <h1 className="mt-4 text-xl font-bold text-nido-900">Cita agendada</h1>
                <p className="mt-2 text-slate-600"><strong>{done.patient?.name}</strong> tiene cita de {done.specialty?.name} con {done.doctor?.name}</p>
                <p className="mt-1 text-lg font-semibold first-letter:uppercase text-nido-800">{weekdayDate(done.starts_at, tz)}, {formatTime(done.starts_at, tz)}</p>
                <div className="mt-6 flex flex-wrap justify-center gap-2">
                    <Link to={`/citas?fecha=${dateIn(done.starts_at, tz)}`}><Button variant="secondary">Ver agenda de ese día</Button></Link>
                    <Button onClick={() => { setDone(null); setPatient(null); setSpecialty(''); setSlot(null); setReason(''); book.reset(); }}>Agendar otra cita</Button>
                </div>
            </Card>
        );
    }

    return (
        <>
            <BackLink to="/citas">Agenda</BackLink>
            <PageHeader title="Nueva cita" description="Siga los pasos. Puede volver a cualquier paso con «Cambiar»." />
            <ol className="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Pasos">
                {STEPS.map((label, i) => (
                    <li key={label} aria-current={i === step ? 'step' : undefined}
                        className={cn('flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold ring-1',
                            i < step ? 'bg-nido-50 text-nido-800 ring-nido-200' : i === step ? 'bg-nido-700 text-white ring-nido-700' : 'bg-white text-slate-500 ring-slate-200')}>
                        <span className={cn('grid size-6 place-items-center rounded-full text-xs', i < step ? 'bg-nido-700 text-white' : i === step ? 'bg-white text-nido-800' : 'bg-slate-100')}>
                            {i < step ? <Check className="size-3.5" aria-hidden /> : i + 1}
                        </span>
                        {label}
                    </li>
                ))}
            </ol>

            <div className="space-y-4">
                <StepCard n={1} title="Paciente" done={selectedPatient ? `${selectedPatient.full_name} · ${selectedPatient.code}` : undefined}
                    onChange={() => { setPatient(null); setSlot(null); }} active={step === 0}>
                    <PatientPicker onPick={setPatient} />
                </StepCard>

                {selectedPatient && (
                    <StepCard n={2} title="Especialidad y día" active={step <= 2}>
                        <SpecialtyAndDate specialty={specialty} doctor={doctor} date={date} tz={tz}
                            onChange={(s, d, dt) => { setSpecialty(s); setDoctor(d); setDate(dt); setSlot(null); }} />
                    </StepCard>
                )}

                {selectedPatient && specialty && (
                    <StepCard n={3} title="Horario" active={step === 2} done={slot ? `${weekdayDate(slot.starts_at, tz)}, ${formatTime(slot.starts_at, tz)} · ${slot.doctor}` : undefined}
                        onChange={() => setSlot(null)}>
                        <SlotPicker specialty={specialty} doctor={doctor} date={date} tz={tz} onPick={setSlot}
                            notice={ruleError(book.error)?.code === 'HORARIO_TOMADO' || ruleError(book.error)?.code === 'MEDICO_OCUPADO' ? errorMessage(book.error) : undefined} />
                    </StepCard>
                )}

                {slot && (
                    <StepCard n={4} title="Confirmar" active>
                        <form className="space-y-4" onSubmit={(e: FormEvent) => { e.preventDefault(); book.mutate(); }}>
                            <Field label="Motivo de la consulta" htmlFor="appt-reason" hint="Lo verá el médico antes de atender.">
                                <Textarea id="appt-reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: Dolor de garganta desde hace 3 días" />
                            </Field>
                            {book.isError && !['HORARIO_TOMADO', 'MEDICO_OCUPADO'].includes(ruleError(book.error)?.code ?? '') && <Alert tone="error">{errorMessage(book.error)}</Alert>}
                            <div className="flex justify-end">
                                <Button type="submit" loading={book.isPending} disabled={!reason.trim()}>Agendar cita</Button>
                            </div>
                        </form>
                    </StepCard>
                )}
            </div>
        </>
    );
}

function StepCard({ n, title, done, active, onChange, children }: { n: number; title: string; done?: string; active: boolean; onChange?: () => void; children: ReactNode }) {
    return (
        <Card className={cn('p-5', active && !done && 'ring-2 ring-nido-300')}>
            <div className="flex items-center justify-between gap-3">
                <h2 className="font-bold text-nido-900">{n}. {title}</h2>
                {done && onChange && <button onClick={onChange} className="text-sm font-semibold text-nido-700 underline">Cambiar</button>}
            </div>
            {done ? <p className="mt-1 text-sm first-letter:uppercase text-slate-700">{done}</p> : <div className="mt-4">{children}</div>}
        </Card>
    );
}

function PatientPicker({ onPick }: { onPick: (p: Patient) => void }) {
    const [text, setText] = useState('');
    const [q, setQ] = useState('');
    const result = useQuery({
        queryKey: ['pacientes', q],
        queryFn: async () => (await api.get<{ data: Patient[] }>('/pacientes', { params: { q } })).data.data,
        enabled: q.length > 0,
        retry: false,
    });
    return (
        <>
            <form role="search" className="flex gap-2" onSubmit={(e) => { e.preventDefault(); setQ(text.trim()); }}>
                <label htmlFor="appt-patient" className="sr-only">Buscar paciente</label>
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500" aria-hidden />
                    <Input id="appt-patient" className="pl-9" autoFocus value={text} onChange={(e) => setText(e.target.value)} placeholder="Nombre, DPI o código del paciente" />
                </div>
                <Button type="submit" variant="secondary">Buscar</Button>
            </form>
            {result.isFetching && <Spinner />}
            {result.isError && <div className="mt-3"><Alert tone="error">{errorMessage(result.error)}</Alert></div>}
            {result.data && (result.data.length === 0 ? (
                <EmptyState title="No se encontró al paciente."><Link to="/pacientes/nuevo" className="font-semibold text-nido-700 underline">Registrarlo primero</Link></EmptyState>
            ) : (
                <ul className="mt-3 divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                    {result.data.map((p) => (
                        <li key={p.id}>
                            <button disabled={p.status !== 'active'} onClick={() => onPick(p)}
                                className="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm hover:bg-nido-50 disabled:cursor-not-allowed disabled:opacity-50">
                                <span><strong>{p.full_name}</strong> <span className="text-xs text-slate-500">· {p.code} · {p.age} años</span></span>
                                <span className="text-sm font-semibold text-nido-700">{p.status === 'active' ? 'Elegir' : 'Ficha no activa'}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            ))}
        </>
    );
}

function SpecialtyAndDate({ specialty, doctor, date, tz, onChange }: { specialty: string; doctor: string; date: string; tz?: string; onChange: (s: string, d: string, date: string) => void }) {
    const specialties = useQuery({ queryKey: ['especialidades'], queryFn: async () => (await api.get<{ data: Specialty[] }>('/especialidades')).data.data });
    const doctors = useQuery({
        queryKey: ['medicos', 'especialidad', specialty],
        queryFn: async () => (await api.get<{ data: Doctor[] }>('/medicos', { params: { especialidad: specialty, estado: 'active' } })).data.data,
        enabled: Boolean(specialty),
    });
    const today = todayIn(tz);
    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Especialidad" htmlFor="appt-specialty">
                <Select id="appt-specialty" value={specialty} onChange={(e) => onChange(e.target.value, '', date)}>
                    <option value="">Seleccione…</option>
                    {specialties.data?.filter((s) => s.doctors > 0).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </Select>
            </Field>
            <Field label="Médico (opcional)" htmlFor="appt-doctor" hint="Déjelo en «Cualquiera» para ver todas las opciones.">
                <Select id="appt-doctor" value={doctor} disabled={!specialty} onChange={(e) => onChange(specialty, e.target.value, date)}>
                    <option value="">Cualquiera</option>
                    {doctors.data?.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                </Select>
            </Field>
            <Field label="Día" htmlFor="appt-date" hint="Hasta 2 semanas a partir de hoy.">
                <Input id="appt-date" type="date" min={today} max={addDays(today, 13)} value={date} onChange={(e) => onChange(specialty, doctor, e.target.value)} />
            </Field>
        </div>
    );
}

function SlotPicker({ specialty, doctor, date, tz, onPick, notice }: { specialty: string; doctor: string; date: string; tz?: string; onPick: (s: Slot) => void; notice?: string }) {
    const slots = useQuery({
        queryKey: ['disponibilidad', specialty, doctor, date],
        queryFn: async () => (await api.get<{ data: Slot[] }>('/disponibilidad', { params: { especialidad: specialty, desde: date, medico: doctor || undefined } })).data.data,
        enabled: Boolean(specialty && date),
    });
    const byDoctor = useMemo(() => {
        const map = new Map<string, Slot[]>();
        for (const s of slots.data ?? []) map.set(s.doctor, [...(map.get(s.doctor) ?? []), s]);
        return [...map.entries()];
    }, [slots.data]);

    if (slots.isLoading) return <Spinner label="Buscando horarios libres…" />;
    if (slots.isError) return <Alert tone="error">{errorMessage(slots.error)}</Alert>;
    return (
        <>
            {notice && <div className="mb-3"><Alert tone="error">{notice}</Alert></div>}
            <p className="mb-3 text-sm first-letter:uppercase text-slate-600">{weekdayDate(`${date}T12:00:00Z`, 'UTC')}</p>
            {byDoctor.length === 0 ? (
                <EmptyState title="No hay horarios libres ese día.">Pruebe con otro día u otro médico.</EmptyState>
            ) : (
                <div className="space-y-4">
                    {byDoctor.map(([name, list]) => (
                        <div key={name}>
                            <p className="mb-2 text-sm font-semibold text-slate-700">{name} <span className="font-normal text-slate-500">· {list.length} horarios libres</span></p>
                            <div className="flex flex-wrap gap-2">
                                {list.map((s) => (
                                    <button key={s.starts_at} onClick={() => onPick(s)}
                                        className="rounded-lg bg-white px-3 py-1.5 text-sm font-semibold tabular-nums text-nido-800 ring-1 ring-nido-200 hover:bg-nido-700 hover:text-white">
                                        {formatTime(s.starts_at, tz)}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
