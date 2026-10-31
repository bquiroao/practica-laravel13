import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { formatDate, formatTime } from '@/lib/format';
import { Alert, Badge, Button, Dialog, Field, Spinner, Textarea } from '@/components/ui';
import { statusTone, type AlertDetail, type CriticalAlert } from './types';

/** CU-07: la alerta con su historial completo y a quién se notificó; desde aquí se acusa (CU-04) y se cierra (CU-06). */
export function AlertDetailDialog({ id, onClose, onChanged }: { id: string; onClose: () => void; onChanged: (a: CriticalAlert, message: string) => void }) {
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const [note, setNote] = useState('');
    const detail = useQuery({ queryKey: ['alertas', 'detalle', id], queryFn: async () => (await api.get<{ data: AlertDetail }>(`/alertas/${id}`)).data.data });
    const acknowledge = useMutation({
        mutationFn: async () => (await api.post<{ data: CriticalAlert }>(`/alertas/${id}/acuse`)).data.data,
        onSuccess: (a) => { detail.refetch(); onChanged(a, 'Alerta acusada: queda registrado que usted la atendió.'); },
    });
    const close = useMutation({
        mutationFn: async () => (await api.post<{ data: CriticalAlert }>(`/alertas/${id}/cierre`, { note })).data.data,
        onSuccess: (a) => { detail.refetch(); onChanged(a, 'Alerta cerrada.'); },
    });
    const errors = fieldErrors(close.error);
    const a = detail.data;
    const when = (iso: string) => `${formatDate(iso, tz, { dateStyle: 'medium' })} ${formatTime(iso, tz)}`;

    return (
        <Dialog open onClose={onClose} title={a ? a.title : 'Alerta'}>
            {detail.isLoading ? <Spinner /> : detail.isError ? <Alert tone="error">{errorMessage(detail.error)}</Alert> : (
                <div className="space-y-4 text-sm">
                    <div className="flex flex-wrap gap-2">
                        <Badge tone={a!.priority === 'CRITICA' ? 'critical' : 'warn'}>Prioridad {a!.priority_label.toLowerCase()}</Badge>
                        <Badge tone={statusTone[a!.status]}>{a!.status_label}</Badge>
                        <Badge tone="neutral">Nivel {a!.level}: {a!.level_label}</Badge>
                    </div>
                    <p className="text-base text-slate-900">{a!.description}</p>
                    <p className="text-slate-600">
                        Paciente <span className="font-mono">{a!.patient.code}</span>
                        {can('expediente.leer') && <> · <Link className="font-semibold text-nido-700 hover:underline" to={`/expediente/${a!.patient.id}`}>Abrir expediente</Link></>}
                        {' '}· {a!.source_label} · {when(a!.created_at)}
                    </p>
                    {a!.deadline_at && <p className="font-semibold text-red-800">Debe acusarse antes de las {formatTime(a!.deadline_at, tz)}; si no, sube al nivel siguiente.</p>}
                    {a!.escalation_exhausted && a!.status === 'ESCALADA' && <Alert tone="error">Se agotó el escalamiento: nadie la ha acusado en ningún nivel.</Alert>}
                    {a!.acknowledged_by && <p>Acusada por <strong>{a!.acknowledged_by}</strong> el {when(a!.acknowledged_at!)}.</p>}
                    {a!.close_note && <Alert tone="success">Cerrada por {a!.closed_by}: {a!.close_note}</Alert>}

                    {acknowledge.isError && <Alert tone="error">{errorMessage(acknowledge.error)}</Alert>}
                    {a!.can_acknowledge && <Button variant="danger" loading={acknowledge.isPending} onClick={() => acknowledge.mutate()}>Acusar: yo me encargo</Button>}
                    {!a!.notified_to_me && ['ENVIADA', 'ESCALADA'].includes(a!.status) && (
                        <p className="text-xs text-slate-500">No se le notificó esta alerta, así que no puede acusarla. La acusa quien la recibió.</p>
                    )}

                    {a!.can_close && (
                        <form className="space-y-2 border-t border-slate-200 pt-4" onSubmit={(e) => { e.preventDefault(); close.mutate(); }}>
                            {close.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(close.error)}</Alert>}
                            <Field label="Nota de cierre" htmlFor="al-note" error={errors.note} hint="Qué se hizo. Queda en el historial.">
                                <Textarea id="al-note" value={note} onChange={(e) => setNote(e.target.value)} maxLength={500} aria-invalid={!!errors.note} />
                            </Field>
                            <Button type="submit" variant="secondary" loading={close.isPending} disabled={note.trim().length < 5}>Cerrar alerta</Button>
                        </form>
                    )}

                    <section aria-labelledby="al-history" className="border-t border-slate-200 pt-4">
                        <h3 id="al-history" className="font-bold text-nido-900">Historial</h3>
                        <ol className="mt-2 space-y-1.5">
                            {a!.events.map((e) => (
                                <li key={e.id} className="flex flex-wrap justify-between gap-2 rounded-lg bg-slate-50 px-3 py-1.5">
                                    <span><strong>{e.event_label}</strong> · nivel {e.level} · {e.actor}</span>
                                    <span className="text-slate-600">{when(e.occurred_at)}</span>
                                </li>
                            ))}
                        </ol>
                    </section>
                    <section aria-labelledby="al-recipients">
                        <h3 id="al-recipients" className="font-bold text-nido-900">Notificada a</h3>
                        <ul className="mt-2 space-y-1">
                            {a!.notifications.map((n, i) => (
                                <li key={i} className="text-slate-700">
                                    Nivel {n.level}: {n.recipient} · {n.delivered ? (n.read_at ? 'la vio' : 'entregada') : `no se pudo entregar (${n.attempts} intentos)`}
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            )}
        </Dialog>
    );
}
