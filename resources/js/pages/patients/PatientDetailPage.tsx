import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { CalendarPlus, FileText, Pencil, Phone, MapPin, ShieldAlert } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { appointmentStatus, formatDate, formatTime, patientStatus, sexLabels, weekdayDate } from '@/lib/format';
import type { Appointment, Patient } from '@/modules/clinical/types';
import { AllergyBanner } from '@/modules/allergies/AllergyBanner';
import { Alert, BackLink, Badge, Button, Card, EmptyState, Spinner } from '@/components/ui';

/** Ficha del paciente: quién es, cómo contactarlo y sus citas. Las acciones principales, arriba y a la vista. */
export function PatientDetailPage() {
    const { id } = useParams();
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const query = useQuery({
        queryKey: ['paciente', id],
        queryFn: async () => (await api.get<{ data: Patient; appointments: Appointment[] }>(`/pacientes/${id}`)).data,
    });

    if (query.isLoading) return <Spinner />;
    if (query.isError) return <><BackLink to="/pacientes">Pacientes</BackLink><Alert tone="error">{errorMessage(query.error)}</Alert></>;
    const { data: p, appointments } = query.data!;
    const now = Date.now();
    const upcoming = appointments.filter((a) => new Date(a.ends_at).getTime() >= now && ['scheduled', 'confirmed'].includes(a.status));
    const past = appointments.filter((a) => !upcoming.includes(a)).reverse();

    return (
        <>
            <BackLink to="/pacientes">Pacientes</BackLink>
            <Card className="p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <span className="grid size-14 place-items-center rounded-full bg-nido-100 text-lg font-bold text-nido-800" aria-hidden>
                            {p.given_name[0]}{p.family_name[0]}
                        </span>
                        <div>
                            <h1 className="text-2xl font-bold text-nido-900">{p.full_name}</h1>
                            <p className="mt-0.5 text-sm text-slate-600">
                                <span className="font-mono">{p.code}</span> · {p.age} años · {sexLabels[p.sex]} · Nació el {formatDate(p.birth_date)}
                            </p>
                            <div className="mt-2 flex gap-2"><Badge tone={patientStatus[p.status].tone}>{patientStatus[p.status].label}</Badge></div>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can('citas.gestionar') && p.status === 'active' && (
                            <Link to={`/citas/nueva?paciente=${p.id}`}><Button><CalendarPlus className="size-4" aria-hidden /> Agendar cita</Button></Link>
                        )}
                        {can('expediente.leer') && (
                            <Link to={`/expediente/${p.id}`}><Button variant="secondary"><FileText className="size-4" aria-hidden /> Expediente</Button></Link>
                        )}
                        {can('pacientes.gestionar') && (
                            <Link to={`/pacientes/${p.id}/editar`}><Button variant="secondary"><Pencil className="size-4" aria-hidden /> Editar datos</Button></Link>
                        )}
                    </div>
                </div>
                {/* Banner de alergias del módulo alergias-clinicas-alerta-visual-expediente: solo lo ven los roles clínicos. */}
                <AllergyBanner patientId={p.id} className="mt-4" />

                <dl className="mt-6 grid gap-x-6 gap-y-4 border-t border-slate-200 pt-6 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt className="font-semibold text-slate-500">Documento</dt>
                        <dd className="mt-0.5 text-slate-900">{p.document_number ? `${p.document_type} ${p.document_number}${p.document_country ? ` (${p.document_country})` : ''}` : 'Sin documento'}</dd></div>
                    <div><dt className="font-semibold text-slate-500">Teléfono</dt>
                        <dd className="mt-0.5 flex items-center gap-1.5 text-slate-900"><Phone className="size-3.5 text-slate-500" aria-hidden />{p.contact ?? 'No registrado'}</dd></div>
                    <div><dt className="font-semibold text-slate-500">Dirección</dt>
                        <dd className="mt-0.5 flex items-center gap-1.5 text-slate-900"><MapPin className="size-3.5 text-slate-500" aria-hidden />{p.address ?? 'No registrada'}</dd></div>
                    <div><dt className="font-semibold text-slate-500">Contacto de emergencia</dt>
                        <dd className="mt-0.5 flex items-center gap-1.5 text-slate-900"><ShieldAlert className="size-3.5 text-slate-500" aria-hidden />
                            {p.emergency_contact_name ? `${p.emergency_contact_name}${p.emergency_contact_phone ? ` · ${p.emergency_contact_phone}` : ''}` : 'No registrado'}</dd></div>
                </dl>
            </Card>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <AppointmentList title="Próximas citas" items={upcoming} tz={tz} empty="No tiene citas programadas." />
                <AppointmentList title="Citas recientes" items={past} tz={tz} empty="Sin citas en los últimos 30 días." />
            </div>
        </>
    );
}

function AppointmentList({ title, items, tz, empty }: { title: string; items: Appointment[]; tz?: string; empty: string }) {
    return (
        <Card>
            <h2 className="border-b border-slate-200 px-5 py-3 font-bold text-nido-900">{title}</h2>
            {items.length === 0 ? <EmptyState title={empty} /> : (
                <ul className="divide-y divide-slate-100">
                    {items.map((a) => (
                        <li key={a.id} className="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                            <div>
                                <p className="font-semibold first-letter:uppercase text-slate-900">{weekdayDate(a.starts_at, tz)}, {formatTime(a.starts_at, tz)}</p>
                                <p className="text-slate-600">{a.specialty?.name} · {a.doctor?.name}</p>
                                <p className="text-xs text-slate-500">Motivo: {a.reason}{a.status_reason ? ` · ${a.status_reason}` : ''}</p>
                            </div>
                            <Badge tone={appointmentStatus[a.status].tone}>{appointmentStatus[a.status].label}</Badge>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
