import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronRight, Clock, Plus, Stethoscope } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { scheduleSummary } from '@/lib/format';
import { cn } from '@/lib/cn';
import type { Doctor, Specialty } from '@/modules/clinical/types';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, PageHeader, Select, Spinner } from '@/components/ui';

const doctorStatus = { active: { label: 'Activo', tone: 'ok' }, inactive: { label: 'Inactivo', tone: 'neutral' }, suspended: { label: 'Suspendido', tone: 'warn' } } as const;

/** Módulo médicos-especialidades: médicos con su horario, y el catálogo de especialidades. */
export function DoctorsPage() {
    const [params, setParams] = useSearchParams();
    const tab = params.get('vista') === 'especialidades' ? 'especialidades' : 'medicos';

    return (
        <>
            <PageHeader title="Médicos" description="Quién atiende, en qué especialidad y en qué horario. Con esto se calculan las citas disponibles." />
            <div role="tablist" aria-label="Secciones" className="mb-4 inline-flex rounded-lg bg-white p-1 ring-1 ring-slate-200">
                {(['medicos', 'especialidades'] as const).map((t) => (
                    <button key={t} role="tab" aria-selected={tab === t} onClick={() => setParams(t === 'medicos' ? {} : { vista: t })}
                        className={cn('rounded-md px-4 py-1.5 text-sm font-semibold', tab === t ? 'bg-nido-700 text-white' : 'text-slate-600 hover:bg-slate-100')}>
                        {t === 'medicos' ? 'Médicos' : 'Especialidades'}
                    </button>
                ))}
            </div>
            {tab === 'medicos' ? <DoctorList /> : <SpecialtyList />}
        </>
    );
}

function DoctorList() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const [creating, setCreating] = useState(false);
    const doctors = useQuery({ queryKey: ['medicos'], queryFn: async () => (await api.get<{ data: Doctor[] }>('/medicos')).data.data });

    if (doctors.isLoading) return <Spinner />;
    if (doctors.isError) return <Alert tone="error">{errorMessage(doctors.error)}</Alert>;

    return (
        <>
            {can('medicos.gestionar') && <div className="mb-4 flex justify-end"><Button onClick={() => setCreating(true)}><Plus className="size-4" aria-hidden /> Registrar médico</Button></div>}
            {doctors.data!.length === 0 ? <Card><EmptyState title="Todavía no hay médicos registrados en este hospital." /></Card> : (
                <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {doctors.data!.map((d) => (
                        <li key={d.id}>
                            <Card className="flex h-full flex-col p-5">
                                <div className="flex items-start justify-between gap-2">
                                    <span className="grid size-10 place-items-center rounded-full bg-nido-100 text-nido-700"><Stethoscope className="size-5" aria-hidden /></span>
                                    <Badge tone={doctorStatus[d.status].tone}>{doctorStatus[d.status].label}</Badge>
                                </div>
                                <h2 className="mt-3 font-bold text-nido-900">{d.name}</h2>
                                <p className="text-xs text-slate-500">Colegiado {d.license_number}</p>
                                <div className="mt-3 flex flex-wrap gap-1">
                                    {d.specialties.length === 0 ? <span className="text-xs text-slate-500">Sin especialidad vigente</span>
                                        : d.specialties.map((s) => <Badge key={s.link_id} tone={s.is_primary ? 'nido' : 'neutral'}>{s.name}</Badge>)}
                                </div>
                                <p className="mt-3 flex items-start gap-1.5 text-sm text-slate-600"><Clock className="mt-0.5 size-4 shrink-0 text-slate-500" aria-hidden />
                                    <span>{scheduleSummary(d.schedule)}{d.hours_per_week > 0 && <span className="text-slate-500"> · {d.hours_per_week} h/semana</span>}</span></p>
                                <div className="mt-auto pt-4">
                                    <Link to={`/medicos/${d.id}`} className="inline-flex items-center gap-1 text-sm font-semibold text-nido-700 hover:underline">
                                        {can('medicos.gestionar') ? 'Ver y editar horario' : 'Ver detalle'} <ChevronRight className="size-4" aria-hidden />
                                    </Link>
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
            {creating && <CreateDoctorDialog onClose={() => setCreating(false)} onCreated={(id) => navigate(`/medicos/${id}`)} />}
        </>
    );
}

function CreateDoctorDialog({ onClose, onCreated }: { onClose: () => void; onCreated: (id: string) => void }) {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({ user_id: '', license_number: '', specialty_id: '' });
    const candidates = useQuery({ queryKey: ['medicos', 'candidatos'], queryFn: async () => (await api.get<{ data: { id: string; name: string; email: string }[] }>('/medicos/candidatos')).data.data });
    const specialties = useQuery({ queryKey: ['especialidades'], queryFn: async () => (await api.get<{ data: Specialty[] }>('/especialidades')).data.data });
    const create = useMutation({
        mutationFn: async () => (await api.post<{ data: Doctor }>('/medicos', form)).data.data,
        onSuccess: (d) => { queryClient.invalidateQueries({ queryKey: ['medicos'] }); onCreated(d.id); },
    });
    const errors = fieldErrors(create.error);
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => {
        const value = e.target.value;
        setForm((f) => ({ ...f, [k]: value }));
    };

    return (
        <Dialog open onClose={onClose} title="Registrar médico">
            {candidates.isLoading ? <Spinner /> : candidates.data?.length === 0 ? (
                <div className="space-y-4">
                    <Alert>Para registrar un médico, la persona primero debe tener una cuenta con el rol «Médico» en este hospital.</Alert>
                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" onClick={onClose}>Cerrar</Button>
                        <Link to="/admin/usuarios"><Button>Ir a Usuarios</Button></Link>
                    </div>
                </div>
            ) : (
                <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); create.mutate(); }}>
                    {create.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(create.error)}</Alert>}
                    <Field label="Persona" htmlFor="dr-user" error={errors.user_id} hint="Solo aparecen cuentas con rol «Médico» que aún no tienen perfil.">
                        <Select id="dr-user" value={form.user_id} onChange={set('user_id')} required>
                            <option value="">Seleccione…</option>
                            {candidates.data?.map((c) => <option key={c.id} value={c.id}>{c.name} ({c.email})</option>)}
                        </Select>
                    </Field>
                    <Field label="Número de colegiado" htmlFor="dr-license" error={errors.license_number}>
                        <Input id="dr-license" value={form.license_number} onChange={set('license_number')} required />
                    </Field>
                    <Field label="Especialidad principal" htmlFor="dr-specialty" error={errors.specialty_id} hint="Podrá agregar otras especialidades después.">
                        <Select id="dr-specialty" value={form.specialty_id} onChange={set('specialty_id')} required>
                            <option value="">Seleccione…</option>
                            {specialties.data?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </Select>
                    </Field>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                        <Button type="submit" loading={create.isPending}>Registrar y definir horario</Button>
                    </div>
                </form>
            )}
        </Dialog>
    );
}

function SpecialtyList() {
    const { can } = useAuth();
    const queryClient = useQueryClient();
    const [name, setName] = useState('');
    const list = useQuery({ queryKey: ['especialidades', 'todas'], queryFn: async () => (await api.get<{ data: Specialty[] }>('/especialidades', { params: { todas: 1 } })).data.data });
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['especialidades'] });
    const create = useMutation({ mutationFn: () => api.post('/especialidades', { name }), onSuccess: () => { setName(''); refresh(); } });
    const toggle = useMutation({ mutationFn: (s: Specialty) => api.patch(`/especialidades/${s.id}`, { status: s.status === 'active' ? 'inactive' : 'active' }), onSuccess: refresh });

    if (list.isLoading) return <Spinner />;
    if (list.isError) return <Alert tone="error">{errorMessage(list.error)}</Alert>;

    return (
        <Card>
            {can('medicos.gestionar') && (
                <form className="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row sm:items-end" onSubmit={(e) => { e.preventDefault(); create.mutate(); }}>
                    <div className="flex-1"><Field label="Nueva especialidad" htmlFor="new-specialty" error={create.isError ? errorMessage(create.error) : undefined}>
                        <Input id="new-specialty" value={name} onChange={(e) => setName(e.target.value)} placeholder="Ej.: Neurología" />
                    </Field></div>
                    <Button type="submit" loading={create.isPending} disabled={!name.trim()}><Plus className="size-4" aria-hidden /> Agregar</Button>
                </form>
            )}
            <ul className="divide-y divide-slate-100">
                {list.data!.map((s) => (
                    <li key={s.id} className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p className={cn('font-semibold', s.status === 'active' ? 'text-slate-900' : 'text-slate-500 line-through')}>{s.name}</p>
                            <p className="text-xs text-slate-500">{s.doctors === 0 ? 'Ningún médico' : `${s.doctors} médico${s.doctors === 1 ? '' : 's'}`}</p>
                        </div>
                        {can('medicos.gestionar') && (
                            <Button size="sm" variant="ghost" loading={toggle.isPending && toggle.variables?.id === s.id} onClick={() => toggle.mutate(s)}>
                                {s.status === 'active' ? 'Desactivar' : 'Activar'}
                            </Button>
                        )}
                    </li>
                ))}
            </ul>
        </Card>
    );
}
