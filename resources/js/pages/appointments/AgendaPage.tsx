import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarPlus, ChevronLeft, ChevronRight } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { addDays, appointmentStatus, formatTime, todayIn, weekdayDate } from '@/lib/format';
import type { Appointment, Doctor } from '@/modules/clinical/types';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, PageHeader, Select, Spinner, Textarea } from '@/components/ui';

/** Módulo citas médicas: la agenda de un día, con las acciones que cada cita permite en su estado. */
export function AgendaPage() {
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const today = todayIn(tz);
    const [params, setParams] = useSearchParams();
    const date = params.get('fecha') ?? today;
    const doctorId = params.get('medico') ?? '';
    const [notice, setNotice] = useState<string | null>(null);
    const [cancelling, setCancelling] = useState<Appointment | null>(null);
    const queryClient = useQueryClient();

    const setParam = (key: string, value: string) => {
        const next = new URLSearchParams(params);
        if (value) next.set(key, value);
        else next.delete(key);
        setParams(next);
    };

    const doctors = useQuery({ queryKey: ['medicos'], queryFn: async () => (await api.get<{ data: Doctor[] }>('/medicos')).data.data });
    const agenda = useQuery({
        queryKey: ['citas', date, doctorId],
        queryFn: async () => (await api.get<{ data: Appointment[] }>('/citas', { params: { desde: date, medico: doctorId || undefined } })).data.data,
        placeholderData: keepPreviousData,
    });
    const change = useMutation({
        mutationFn: async ({ a, status, reason }: { a: Appointment; status: string; reason?: string }) =>
            (await api.post<{ data: Appointment }>(`/citas/${a.id}/estado`, { status, reason })).data.data,
        onSuccess: (a) => {
            setCancelling(null);
            setNotice(`Cita de ${a.patient?.name} a las ${formatTime(a.starts_at, tz)}: ${appointmentStatus[a.status].label.toLowerCase()}.`);
            queryClient.invalidateQueries({ queryKey: ['citas'] });
        },
    });

    const items = agenda.data ?? [];
    const active = items.filter((a) => a.status !== 'cancelled');

    return (
        <>
            <PageHeader title="Citas" description="Agenda del día. Use las flechas para cambiar de día."
                actions={can('citas.gestionar') && <Link to="/citas/nueva"><Button><CalendarPlus className="size-4" aria-hidden /> Nueva cita</Button></Link>} />

            <Card className="mb-4 flex flex-wrap items-end gap-3 p-4">
                <div className="flex items-center gap-1">
                    <Button variant="secondary" size="sm" onClick={() => setParam('fecha', addDays(date, -1))} aria-label="Día anterior"><ChevronLeft className="size-4" /></Button>
                    <Button variant={date === today ? 'primary' : 'secondary'} size="sm" onClick={() => setParam('fecha', '')}>Hoy</Button>
                    <Button variant="secondary" size="sm" onClick={() => setParam('fecha', addDays(date, 1))} aria-label="Día siguiente"><ChevronRight className="size-4" /></Button>
                </div>
                <p className="min-w-48 flex-1 text-lg font-bold first-letter:uppercase text-nido-900" aria-live="polite">{weekdayDate(`${date}T12:00:00Z`, 'UTC')}</p>
                <Field label="Fecha" htmlFor="agenda-fecha"><Input id="agenda-fecha" type="date" value={date} onChange={(e) => setParam('fecha', e.target.value)} /></Field>
                <Field label="Médico" htmlFor="agenda-medico">
                    <Select id="agenda-medico" value={doctorId} onChange={(e) => setParam('medico', e.target.value)}>
                        <option value="">Todos los médicos</option>
                        {doctors.data?.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                    </Select>
                </Field>
            </Card>

            {notice && <div className="mb-4"><Alert tone="success">{notice}</Alert></div>}
            {change.isError && !cancelling && <div className="mb-4"><Alert tone="error">{errorMessage(change.error)}</Alert></div>}

            <Card>
                <p className="border-b border-slate-200 px-5 py-3 text-sm text-slate-600">
                    {agenda.isLoading ? 'Cargando…' : `${active.length} cita${active.length === 1 ? '' : 's'} activa${active.length === 1 ? '' : 's'}${items.length > active.length ? ` · ${items.length - active.length} cancelada${items.length - active.length === 1 ? '' : 's'}` : ''}`}
                </p>
                {agenda.isLoading ? <Spinner /> : agenda.isError ? <div className="p-4"><Alert tone="error">{errorMessage(agenda.error)}</Alert></div>
                    : items.length === 0 ? (
                        <EmptyState title="No hay citas este día.">
                            {can('citas.gestionar') && <Link to="/citas/nueva" className="font-semibold text-nido-700 underline">Agendar una cita</Link>}
                        </EmptyState>
                    ) : (
                        <ol className="divide-y divide-slate-100">
                            {items.map((a) => (
                                <li key={a.id} className={`grid gap-3 px-5 py-4 sm:grid-cols-[5rem_1fr_auto] sm:items-center ${a.status === 'cancelled' ? 'bg-slate-50' : ''}`}>
                                    <div className={`text-lg font-bold tabular-nums ${a.status === 'cancelled' ? 'text-slate-600 line-through' : 'text-nido-800'}`}>{formatTime(a.starts_at, tz)}<span className="block text-xs font-normal text-slate-500">a {formatTime(a.ends_at, tz)}</span></div>
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link to={`/pacientes/${a.patient?.id}`} className="font-semibold text-slate-900 hover:underline">{a.patient?.name}</Link>
                                            <Badge tone={appointmentStatus[a.status].tone}>{appointmentStatus[a.status].label}</Badge>
                                        </div>
                                        <p className="text-sm text-slate-600">{a.specialty?.name} · {a.doctor?.name}</p>
                                        <p className="text-xs text-slate-500">Motivo: {a.reason}{a.status_reason ? ` · ${a.status_reason}` : ''}</p>
                                    </div>
                                    {can('citas.gestionar') && a.next.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {a.next.filter((s) => s !== 'cancelled').map((s) => (
                                                <Button key={s} size="sm" variant={s === 'completed' ? 'primary' : 'secondary'}
                                                    loading={change.isPending && change.variables?.a.id === a.id && change.variables.status === s}
                                                    onClick={() => change.mutate({ a, status: s })}>{appointmentStatus[s].action}</Button>
                                            ))}
                                            <Button size="sm" variant="ghost" className="text-red-700" onClick={() => { change.reset(); setCancelling(a); }}>Cancelar</Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
            </Card>

            <CancelDialog appointment={cancelling} tz={tz} pending={change.isPending} error={change.isError ? errorMessage(change.error) : undefined}
                onClose={() => setCancelling(null)} onConfirm={(reason) => cancelling && change.mutate({ a: cancelling, status: 'cancelled', reason })} />
        </>
    );
}

function CancelDialog({ appointment, tz, pending, error, onClose, onConfirm }: {
    appointment: Appointment | null; tz?: string; pending: boolean; error?: string; onClose: () => void; onConfirm: (reason: string) => void;
}) {
    const [reason, setReason] = useState('');
    return (
        <Dialog open={appointment !== null} onClose={onClose} title="Cancelar cita">
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); onConfirm(reason); }}>
                <p className="text-sm text-slate-600">
                    Se cancelará la cita de <strong>{appointment?.patient?.name}</strong> a las {appointment && formatTime(appointment.starts_at, tz)} con {appointment?.doctor?.name}. El horario quedará libre.
                </p>
                {error && <Alert tone="error">{error}</Alert>}
                <Field label="Motivo de la cancelación" htmlFor="cancel-reason">
                    <Textarea id="cancel-reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: El paciente llamó para reprogramar" />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Volver</Button>
                    <Button type="submit" variant="danger" loading={pending}>Cancelar cita</Button>
                </div>
            </form>
        </Dialog>
    );
}
