import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, errorMessage, fieldErrors, ruleError } from '@/lib/api';
import { formatDate, todayIn } from '@/lib/format';
import { useAuth } from '@/auth/AuthContext';
import type { DuplicateCandidate, Patient } from '@/modules/clinical/types';
import { Alert, BackLink, Button, Card, Dialog, Field, FormSection, Input, PageHeader, Select, Spinner, Textarea } from '@/components/ui';

type Form = {
    given_name: string; family_name: string; birth_date: string; sex: string;
    document_type: string; document_number: string; document_country: string;
    contact: string; address: string; emergency_contact_name: string; emergency_contact_phone: string; status: string;
};

const empty: Form = {
    given_name: '', family_name: '', birth_date: '', sex: '', document_type: 'DPI', document_number: '', document_country: '',
    contact: '', address: '', emergency_contact_name: '', emergency_contact_phone: '', status: 'active',
};

// Cambiar cualquiera de estos datos exige explicar el motivo (módulo pacientes, R-07).
const IDENTITY: (keyof Form)[] = ['given_name', 'family_name', 'birth_date', 'sex', 'document_type', 'document_number', 'document_country', 'status'];

const fromPatient = (p: Patient): Form => ({
    given_name: p.given_name, family_name: p.family_name, birth_date: p.birth_date, sex: p.sex, document_type: p.document_type ?? '',
    document_number: p.document_number ?? '', document_country: p.document_country ?? '', contact: p.contact ?? '', address: p.address ?? '',
    emergency_contact_name: p.emergency_contact_name ?? '', emergency_contact_phone: p.emergency_contact_phone ?? '', status: p.status,
});

/** Registro y edición de la ficha, en secciones cortas y con avisos claros de duplicados. */
export function PatientFormPage() {
    const { id } = useParams();
    const editing = Boolean(id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { session } = useAuth();
    const [form, setForm] = useState<Form>(empty);
    const [motivo, setMotivo] = useState('');
    const [duplicates, setDuplicates] = useState<DuplicateCandidate[] | null>(null);
    const [existing, setExisting] = useState<{ id: string; codigo: string } | null>(null);

    const current = useQuery({
        queryKey: ['paciente', id],
        queryFn: async () => (await api.get<{ data: Patient; appointments: unknown[] }>(`/pacientes/${id}`)).data,
        select: (r) => r.data,
        enabled: editing,
    });
    useEffect(() => {
        if (current.data) setForm(fromPatient(current.data));
    }, [current.data]);

    const original = current.data ? fromPatient(current.data) : null;
    const identityChanged = editing && original !== null && IDENTITY.some((k) => form[k].trim() !== original[k].trim());

    const save = useMutation({
        mutationFn: async (extra: Record<string, unknown>) => {
            const body = { ...form, document_type: form.document_type || null, motivo: motivo || undefined, ...extra };
            return editing
                ? (await api.put<{ data: Patient }>(`/pacientes/${id}`, { ...body, version: current.data!.version })).data.data
                : (await api.post<{ data: Patient }>('/pacientes', { ...body, status: undefined })).data.data;
        },
        onSuccess: (patient) => {
            queryClient.invalidateQueries({ queryKey: ['pacientes'] });
            queryClient.invalidateQueries({ queryKey: ['paciente', patient.id] });
            navigate(`/pacientes/${patient.id}`, { state: { saved: true } });
        },
        onError: (e) => {
            const rule = ruleError(e);
            if (rule?.code === 'POSIBLE_DUPLICADO') setDuplicates(rule.details.candidatos as DuplicateCandidate[]);
            if (rule?.code === 'PACIENTE_EXISTENTE') setExisting(rule.details as { id: string; codigo: string });
        },
    });

    if (editing && current.isLoading) return <Spinner />;
    if (editing && current.isError) return <Alert tone="error">{errorMessage(current.error)}</Alert>;

    const errors = fieldErrors(save.error);
    const rule = ruleError(save.error);
    const set = (k: keyof Form) => (e: { target: { value: string } }) => {
        const value = e.target.value;
        setForm((f) => ({ ...f, [k]: value }));
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        setExisting(null);
        save.mutate({});
    };
    const docError = rule?.code === 'DOCUMENTO_INVALIDO' || rule?.code === 'DOCUMENTO_EN_USO' ? rule.message : errors.document_number;
    const showRuleAlert = rule && !['POSIBLE_DUPLICADO', 'PACIENTE_EXISTENTE', 'DOCUMENTO_INVALIDO', 'DOCUMENTO_EN_USO'].includes(rule.code);

    return (
        <>
            <BackLink to={editing ? `/pacientes/${id}` : '/pacientes'}>{editing ? 'Volver a la ficha' : 'Pacientes'}</BackLink>
            <PageHeader title={editing ? `Editar datos de ${current.data?.full_name}` : 'Registrar paciente'}
                description={editing ? `Código ${current.data?.code}. Los campos con * son obligatorios.` : `Se le asignará un código de ${session?.hospital.name}. Los campos con * son obligatorios.`} />

            <Card className="p-6">
                <form onSubmit={submit} noValidate>
                    {existing && (
                        <div className="mb-6"><Alert tone="error">
                            Ya hay un paciente con ese documento (<strong>{existing.codigo}</strong>).{' '}
                            <Link className="font-semibold underline" to={`/pacientes/${existing.id}`}>Abrir su ficha</Link> en lugar de crear otra.
                        </Alert></div>
                    )}
                    {showRuleAlert && <div className="mb-6"><Alert tone="error">{rule.message}{rule.code === 'CONFLICTO_VERSION' && (
                        <> <button type="button" className="font-semibold underline" onClick={() => { save.reset(); current.refetch(); }}>Recargar los datos</button></>
                    )}</Alert></div>}
                    {save.isError && !rule && Object.keys(errors).length === 0 && <div className="mb-6"><Alert tone="error">{errorMessage(save.error)}</Alert></div>}

                    <FormSection title="Datos personales" description="Tal como aparecen en el documento de identidad.">
                        <Field label="Nombres *" htmlFor="given_name" error={errors.given_name}>
                            <Input id="given_name" value={form.given_name} onChange={set('given_name')} autoComplete="off" aria-invalid={Boolean(errors.given_name)} />
                        </Field>
                        <Field label="Apellidos *" htmlFor="family_name" error={errors.family_name}>
                            <Input id="family_name" value={form.family_name} onChange={set('family_name')} autoComplete="off" aria-invalid={Boolean(errors.family_name)} />
                        </Field>
                        <Field label="Fecha de nacimiento *" htmlFor="birth_date" error={errors.birth_date}>
                            <Input id="birth_date" type="date" max={todayIn()} value={form.birth_date} onChange={set('birth_date')} aria-invalid={Boolean(errors.birth_date)} />
                        </Field>
                        <Field label="Sexo *" htmlFor="sex" error={errors.sex}>
                            <Select id="sex" value={form.sex} onChange={set('sex')} aria-invalid={Boolean(errors.sex)}>
                                <option value="">Seleccione…</option>
                                <option value="female">Femenino</option><option value="male">Masculino</option>
                                <option value="other">Otro</option><option value="unknown">Prefiere no indicar</option>
                            </Select>
                        </Field>
                        {editing && (
                            <Field label="Estado de la ficha" htmlFor="status" hint="«Inactivo» oculta al paciente de nuevas citas.">
                                <Select id="status" value={form.status} onChange={set('status')}>
                                    <option value="active">Activo</option><option value="inactive">Inactivo</option><option value="deceased">Fallecido</option>
                                </Select>
                            </Field>
                        )}
                    </FormSection>

                    <FormSection title="Documento de identidad" description="Evita registrar dos veces a la misma persona. Si no lo trae, puede dejarlo vacío.">
                        <Field label="Tipo de documento" htmlFor="document_type">
                            <Select id="document_type" value={form.document_type} onChange={set('document_type')}>
                                <option value="">Sin documento</option>
                                <option value="DPI">DPI (Guatemala)</option>
                                <option value="PASAPORTE">Pasaporte</option>
                            </Select>
                        </Field>
                        {form.document_type && (
                            <Field label={form.document_type === 'DPI' ? 'Número de DPI' : 'Número de pasaporte'} htmlFor="document_number" error={docError}
                                hint={form.document_type === 'DPI' ? '13 dígitos; puede escribirlo con o sin espacios.' : 'De 6 a 20 letras o números.'}>
                                <Input id="document_number" inputMode={form.document_type === 'DPI' ? 'numeric' : 'text'} value={form.document_number}
                                    onChange={set('document_number')} aria-invalid={Boolean(docError)} />
                            </Field>
                        )}
                        {form.document_type === 'PASAPORTE' && (
                            <Field label="País que lo emitió" htmlFor="document_country" hint="Código de 3 letras: GTM, MEX, SLV, HND, USA…" error={errors.document_country}>
                                <Input id="document_country" maxLength={3} className="uppercase" value={form.document_country} onChange={set('document_country')} />
                            </Field>
                        )}
                    </FormSection>

                    <FormSection title="Contacto" description="Para avisarle de sus citas y para emergencias.">
                        <Field label="Teléfono" htmlFor="contact" error={errors.contact}><Input id="contact" type="tel" value={form.contact} onChange={set('contact')} /></Field>
                        <Field label="Dirección" htmlFor="address" error={errors.address}><Input id="address" value={form.address} onChange={set('address')} /></Field>
                        <Field label="Contacto de emergencia" htmlFor="emergency_contact_name" hint="Nombre y parentesco, p. ej. «Juan Pérez (esposo)»." error={errors.emergency_contact_name}>
                            <Input id="emergency_contact_name" value={form.emergency_contact_name} onChange={set('emergency_contact_name')} />
                        </Field>
                        <Field label="Teléfono de emergencia" htmlFor="emergency_contact_phone" error={errors.emergency_contact_phone}>
                            <Input id="emergency_contact_phone" type="tel" value={form.emergency_contact_phone} onChange={set('emergency_contact_phone')} />
                        </Field>
                    </FormSection>

                    {identityChanged && (
                        <div className="mb-6 rounded-lg bg-amber-50 p-4 ring-1 ring-amber-200">
                            <Field label="Motivo del cambio *" htmlFor="motivo" hint="Cambió datos de identidad o el estado. Queda en la bitácora; al menos 10 caracteres."
                                error={rule?.code === 'MOTIVO_REQUERIDO' ? rule.message : undefined}>
                                <Textarea id="motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} placeholder="Ej.: Corrección del apellido según DPI presentado" />
                            </Field>
                        </div>
                    )}

                    <div className="flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-6">
                        <Link to={editing ? `/pacientes/${id}` : '/pacientes'}><Button type="button" variant="secondary">Cancelar</Button></Link>
                        <Button type="submit" loading={save.isPending}>{editing ? 'Guardar cambios' : 'Registrar paciente'}</Button>
                    </div>
                </form>
            </Card>

            <DuplicateDialog candidates={duplicates} onClose={() => setDuplicates(null)} pending={save.isPending}
                error={rule?.code === 'MOTIVO_REQUERIDO' ? rule.message : undefined}
                onConfirm={(reason) => { setMotivo(reason); save.mutate({ confirmar_duplicado: true, motivo: reason }); }} />
        </>
    );
}

function DuplicateDialog({ candidates, onClose, onConfirm, pending, error }: {
    candidates: DuplicateCandidate[] | null; onClose: () => void; onConfirm: (reason: string) => void; pending: boolean; error?: string;
}) {
    const [reason, setReason] = useState('');
    return (
        <Dialog open={candidates !== null} onClose={onClose} title="¿Es la misma persona?">
            <p className="text-sm text-slate-600">Ya hay {candidates?.length === 1 ? 'un paciente' : 'pacientes'} con el mismo nombre y fecha de nacimiento:</p>
            <ul className="mt-3 space-y-2">
                {candidates?.map((c) => (
                    <li key={c.id} className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-200">
                        <span><strong>{c.nombre}</strong><br /><span className="text-xs text-slate-500">{c.codigo} · {formatDate(c.nacimiento)}{c.documento ? ` · Doc. ${c.documento}` : ''}</span></span>
                        <Link to={`/pacientes/${c.id}`} className="shrink-0 text-sm font-semibold text-nido-700 underline">Abrir ficha</Link>
                    </li>
                ))}
            </ul>
            <p className="mt-4 text-sm text-slate-600">Si es la misma persona, use su ficha. Si es otra, explique cómo lo comprobó:</p>
            <form className="mt-2 space-y-3" onSubmit={(e) => { e.preventDefault(); onConfirm(reason); }}>
                <Field label="Motivo" htmlFor="dup-reason" error={error} hint="Al menos 10 caracteres.">
                    <Textarea id="dup-reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: Distinto DPI y distinta madre" />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Volver</Button>
                    <Button type="submit" loading={pending}>Es otra persona, registrar</Button>
                </div>
            </form>
        </Dialog>
    );
}
