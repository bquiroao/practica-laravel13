import { useState, type FormEvent } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api, errorMessage, fieldErrors, ruleError } from '@/lib/api';
import { addDays, formatDate, todayIn } from '@/lib/format';
import { useAuth } from '@/auth/AuthContext';
import { Alert, Button, Dialog, Field, Input, Select } from '@/components/ui';
import type { CatalogMedication, CatalogOptions, MedicationSheet } from './types';

/**
 * Módulo catálogo de medicamentos. CU-01 (alta) y CU-03 (versión nueva): el mismo formulario con la ficha completa.
 * Una versión nueva no edita la vigente: la cierra el día en que empieza la nueva (RN-01).
 */
export function MedicationFormDialog({ base, options, onClose, onDone, onOpenCode }: {
    base?: CatalogMedication; options: CatalogOptions; onClose: () => void; onDone: (m: CatalogMedication) => void; onOpenCode: (code: string) => void;
}) {
    const { session } = useAuth();
    const today = todayIn(session?.hospital.timezone);
    // RN-01: la versión nueva empieza después de la vigente; por defecto hoy, si ya se puede.
    const minFrom = base ? addDays(base.valid_from, 1) : undefined;
    const [form, setForm] = useState<MedicationSheet>(base ? {
        name: base.name, active_ingredient: base.active_ingredient, strength_value: base.strength_value, strength_unit: base.strength_unit,
        dosage_form: base.dosage_form, route: base.route, presentation: base.presentation, sanitary_registration: base.sanitary_registration ?? '',
        therapeutic_group: base.therapeutic_group, allergy_groups: base.allergy_groups, valid_from: minFrom! > today ? minFrom! : today,
    } : {
        name: '', active_ingredient: '', strength_value: '', strength_unit: 'mg', dosage_form: 'TABLETA', route: 'ORAL', presentation: '',
        sanitary_registration: '', therapeutic_group: 'OTRO', allergy_groups: [], valid_from: today,
    });
    // En un alta, si no se marcan familias, el sistema las reconoce por el principio activo.
    const [groupsTouched, setGroupsTouched] = useState(!!base);
    const save = useMutation({
        mutationFn: async () => (await api.post<{ data: CatalogMedication }>(base ? `/catalogo/medicamentos/${base.code}/versiones` : '/catalogo/medicamentos', {
            ...form, sanitary_registration: form.sanitary_registration.trim() || null, valid_from: form.valid_from || null,
            allergy_groups: groupsTouched ? form.allergy_groups : null, ...(base ? { version: base.version } : {}),
        })).data.data,
        onSuccess: onDone,
    });
    const errors = fieldErrors(save.error);
    const rule = ruleError(save.error);
    const duplicate = rule?.code === 'DUPLICADO' ? String(rule.details?.codigo_existente ?? '') : '';
    const set = (k: keyof MedicationSheet) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };
    const toggleGroup = (g: string) => {
        setGroupsTouched(true);
        setForm((f) => ({ ...f, allergy_groups: f.allergy_groups.includes(g) ? f.allergy_groups.filter((x) => x !== g) : [...f.allergy_groups, g] }));
    };
    const submit = (e: FormEvent) => { e.preventDefault(); save.mutate(); };

    return (
        <Dialog open onClose={onClose} title={base ? `Nueva versión de ${base.code}` : 'Registrar medicamento'}>
            <form className="space-y-4" onSubmit={submit} noValidate>
                <p className="text-sm text-slate-600">
                    {base
                        ? `La versión ${base.version} queda vigente hasta el día en que empiece esta. Envíe la ficha completa: el código no cambia.`
                        : 'El sistema asigna el código MED-NNNNNN. Antes de registrar, busque si ya existe uno equivalente.'}
                </p>
                {duplicate && (
                    <Alert tone="error">
                        {errorMessage(save.error)}{' '}
                        <button type="button" className="font-semibold underline" onClick={() => onOpenCode(duplicate)}>Ver {duplicate}</button>
                    </Alert>
                )}
                {save.isError && !duplicate && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                {save.isError && Object.keys(errors).length > 0 && <Alert tone="error">Revise los campos marcados ({Object.keys(errors).length}).</Alert>}

                <Field label="Nombre" htmlFor="med-name" error={errors.name} hint="Como se mostrará al recetar. Ej.: Loratadina 10 mg tableta.">
                    <Input id="med-name" value={form.name} onChange={set('name')} maxLength={150} aria-invalid={!!errors.name} autoFocus />
                </Field>
                <Field label="Principio activo" htmlFor="med-ingredient" error={errors.active_ingredient} hint="Ej.: Loratadina. Se compara sin mayúsculas ni tildes para evitar duplicados.">
                    <Input id="med-ingredient" value={form.active_ingredient} onChange={set('active_ingredient')} maxLength={120} aria-invalid={!!errors.active_ingredient} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-[1fr_9rem]">
                    <Field label="Concentración" htmlFor="med-strength" error={errors.strength_value} hint="Mayor que 0, hasta 3 decimales con punto. Ej.: 0.9">
                        <Input id="med-strength" inputMode="decimal" value={form.strength_value} onChange={set('strength_value')} aria-invalid={!!errors.strength_value} />
                    </Field>
                    <Field label="Unidad" htmlFor="med-unit" error={errors.strength_unit}>
                        <Select id="med-unit" value={form.strength_unit} onChange={set('strength_unit')} aria-invalid={!!errors.strength_unit}>
                            {options.units.map((u) => <option key={u} value={u}>{u}</option>)}
                        </Select>
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Forma farmacéutica" htmlFor="med-form" error={errors.dosage_form}>
                        <Select id="med-form" value={form.dosage_form} onChange={set('dosage_form')}>
                            {options.forms.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Vía de administración" htmlFor="med-route" error={errors.route}>
                        <Select id="med-route" value={form.route} onChange={set('route')}>
                            {options.routes.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </Select>
                    </Field>
                </div>
                <Field label="Presentación" htmlFor="med-presentation" error={errors.presentation} hint="Ej.: Caja con 10 tabletas.">
                    <Input id="med-presentation" value={form.presentation} onChange={set('presentation')} maxLength={120} aria-invalid={!!errors.presentation} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Grupo terapéutico" htmlFor="med-group" error={errors.therapeutic_group} hint="Agrupa las recetas en los reportes.">
                        <Select id="med-group" value={form.therapeutic_group} onChange={set('therapeutic_group')}>
                            {options.therapeutic_groups.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Registro sanitario (opcional)" htmlFor="med-rs" error={errors.sanitary_registration} hint="De 4 a 30 letras, números o guiones.">
                        <Input id="med-rs" value={form.sanitary_registration} onChange={set('sanitary_registration')} maxLength={30} aria-invalid={!!errors.sanitary_registration} />
                    </Field>
                </div>
                <fieldset className="space-y-2 text-sm">
                    <legend className="mb-1 font-semibold text-slate-700">Familias para alergias cruzadas</legend>
                    <p className="text-xs text-slate-500">
                        {groupsTouched ? 'Al recetar, una alergia a la familia marcada bloquea o advierte.' : 'Si no marca ninguna, el sistema la reconoce por el principio activo (p. ej. amoxicilina → penicilinas).'}
                    </p>
                    <div className="flex flex-wrap gap-x-4 gap-y-2">
                        {options.allergy_groups.map((o) => (
                            <label key={o.value} className="flex items-center gap-2">
                                <input type="checkbox" className="accent-nido-700" checked={form.allergy_groups.includes(o.value)} onChange={() => toggleGroup(o.value)} />{o.label}
                            </label>
                        ))}
                    </div>
                    {errors['allergy_groups.0'] && <p className="text-xs font-medium text-red-700" role="alert">{errors['allergy_groups.0']}</p>}
                </fieldset>
                <Field label="Vigente desde" htmlFor="med-from" error={errors.valid_from}
                    hint={base ? `Debe ser después del ${formatDate(base.valid_from)}, cuando empezó la versión ${base.version}.` : 'Por defecto, hoy.'}>
                    <Input id="med-from" type="date" value={form.valid_from} min={minFrom} max="2099-12-31" onChange={set('valid_from')} aria-invalid={!!errors.valid_from} />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={save.isPending}>{base ? 'Registrar versión' : 'Registrar medicamento'}</Button>
                </div>
            </form>
        </Dialog>
    );
}

/** CU-04: la baja cierra la vigencia; el medicamento no se borra y deja de poder recetarse desde esa fecha. */
export function MedicationRetireDialog({ medication, onClose, onDone }: { medication: CatalogMedication; onClose: () => void; onDone: (m: CatalogMedication) => void }) {
    const { session } = useAuth();
    const today = todayIn(session?.hospital.timezone);
    const [date, setDate] = useState(today);
    const [reason, setReason] = useState('');
    const save = useMutation({
        mutationFn: async () => (await api.post<{ data: CatalogMedication }>(`/catalogo/medicamentos/${medication.code}/baja`, { date, reason, version: medication.version })).data.data,
        onSuccess: onDone,
    });
    const errors = fieldErrors(save.error);

    return (
        <Dialog open onClose={onClose} title={`Dar de baja ${medication.code}`}>
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                <p className="text-sm text-slate-600">
                    <strong>{medication.name}</strong> deja de estar vigente el día de la baja: ya no aparece al buscar ni se puede recetar. El registro y su historial se conservan.
                </p>
                {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <Field label="Fecha de baja" htmlFor="med-retire-date" error={errors.date} hint="Desde hoy. Ese día ya no está vigente.">
                    <Input id="med-retire-date" type="date" value={date} min={today} max="2099-12-31" onChange={(e) => setDate(e.target.value)} aria-invalid={!!errors.date} />
                </Field>
                <Field label="Motivo" htmlFor="med-retire-reason" error={errors.reason} hint="Queda en la bitácora. Ej.: «Retiro del formulario».">
                    <Input id="med-retire-reason" value={reason} onChange={(e) => setReason(e.target.value)} maxLength={200} aria-invalid={!!errors.reason} />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" variant="danger" loading={save.isPending} disabled={reason.trim().length < 5}>Dar de baja</Button>
                </div>
            </form>
        </Dialog>
    );
}

/** CU-06: eliminar solo un registro erróneo que nunca se usó. Pide confirmación (hallazgo H-03 de la semana 9). */
export function MedicationDeleteDialog({ medication, onClose, onDone }: { medication: CatalogMedication; onClose: () => void; onDone: () => void }) {
    const remove = useMutation({ mutationFn: async () => api.delete(`/catalogo/medicamentos/${medication.code}`), onSuccess: onDone });

    return (
        <Dialog open onClose={onClose} title={`¿Eliminar ${medication.code}?`}>
            <div className="space-y-4">
                <p className="text-sm text-slate-600">
                    Se borra <strong>{medication.name}</strong> con todas sus versiones. Úselo solo para un registro hecho por error: nunca se usó en una receta.
                    Esta acción no se puede deshacer.
                </p>
                {remove.isError && <Alert tone="error">{errorMessage(remove.error)}</Alert>}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose} autoFocus>Cancelar</Button>
                    <Button type="button" variant="danger" loading={remove.isPending} onClick={() => remove.mutate()}>Eliminar</Button>
                </div>
            </div>
        </Dialog>
    );
}
