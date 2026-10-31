import { useState, type FormEvent, type ReactNode } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { addDays, formatDate, todayIn } from '@/lib/format';
import { useAuth } from '@/auth/AuthContext';
import { Alert, Button, Dialog, Field, Input, Select, Textarea } from '@/components/ui';
import type { CatalogLabTest, LabCatalogOptions, LabTestSheet } from './types';

const text = (v: number | null | undefined) => (v === null || v === undefined ? '' : String(v));

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <fieldset className="space-y-4 border-t border-slate-200 pt-4 first-of-type:border-t-0 first-of-type:pt-0">
            <legend className="float-left mb-1 w-full font-bold text-nido-900">{title}</legend>
            {description && <p className="clear-both text-xs text-slate-500">{description}</p>}
            <div className="clear-both space-y-4">{children}</div>
        </fieldset>
    );
}

/**
 * Módulo catálogo de pruebas de laboratorio. CU-01 a CU-03: alta o versión nueva con la ficha completa. Una versión no
 * se edita; si la vigente se cruza con la nueva, se cierra el día previo (RN-05). El formulario conserva lo capturado
 * y muestra cada error junto a su campo (RNF-04).
 */
export function LabTestFormDialog({ base, options, onClose, onDone }: { base?: CatalogLabTest; options: LabCatalogOptions; onClose: () => void; onDone: (t: CatalogLabTest) => void }) {
    const { session } = useAuth();
    const today = todayIn(session?.hospital.timezone);
    const minFrom = base ? [today, addDays(base.valid_from, 1)].sort()[1] : today;
    const [form, setForm] = useState<LabTestSheet>({
        code: base?.code ?? '', name: base?.name ?? '', section: base?.section ?? 'hematologia', turnaround_hours: text(base?.turnaround_hours),
        specimen_type: base?.specimen_type ?? 'sangre-total', container: base?.container ?? '', min_volume_ml: text(base?.min_volume_ml),
        fasting_hours: text(base?.fasting_hours ?? 0), preparation_instructions: base?.preparation_instructions ?? '', storage: base?.storage ?? 'refrigerada',
        stability_hours: text(base?.stability_hours), unit: base?.unit ?? '', ref_low: text(base?.ref_low), ref_high: text(base?.ref_high),
        critical_low: text(base?.critical_low), critical_high: text(base?.critical_high), valid_from: minFrom, valid_to: '',
    });
    const save = useMutation({
        mutationFn: async () => {
            const body = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v.trim() === '' ? null : v.trim()]));
            const url = base ? `/catalogo/pruebas-laboratorio/${base.code}/versiones` : '/catalogo/pruebas-laboratorio';
            return (await api.post<{ data: CatalogLabTest }>(url, base ? { ...body, version: base.version } : body)).data.data;
        },
        onSuccess: onDone,
    });
    const errors = fieldErrors(save.error);
    const set = (k: keyof LabTestSheet) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };
    const input = (k: keyof LabTestSheet, label: string, hint?: string, props: Record<string, unknown> = {}) => (
        <Field label={label} htmlFor={`lt-${k}`} error={errors[k]} hint={hint}>
            <Input id={`lt-${k}`} value={form[k]} onChange={set(k)} aria-invalid={!!errors[k]} {...props} />
        </Field>
    );
    const submit = (e: FormEvent) => { e.preventDefault(); save.mutate(); };

    return (
        <Dialog open onClose={onClose} title={base ? `Nueva versión de ${base.code}` : 'Registrar prueba'}>
            <form className="space-y-5" onSubmit={submit} noValidate>
                <p className="text-sm text-slate-600">
                    {base
                        ? `Si la versión ${base.version} sigue vigente ese día, se cerrará el día anterior. Las órdenes ya pedidas conservan los datos con que se pidieron.`
                        : 'Queda registrada a su nombre. Una vez guardada no se edita: un cambio se registra como versión nueva.'}
                </p>
                {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                {Object.keys(errors).length > 0 && <Alert tone="error">Revise los campos marcados ({Object.keys(errors).length}).</Alert>}

                <Section title="Datos de la prueba">
                    <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                        {base ? <Field label="Código" htmlFor="lt-code-ro"><Input id="lt-code-ro" value={base.code} readOnly /></Field>
                            : input('code', 'Código', 'Ej.: HEM-001', { autoFocus: true, maxLength: 7 })}
                        {input('name', 'Nombre', undefined, { maxLength: 150 })}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Sección" htmlFor="lt-section" error={errors.section}>
                            <Select id="lt-section" value={form.section} onChange={set('section')}>
                                {options.sections.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </Select>
                        </Field>
                        {input('turnaround_hours', 'Tiempo de respuesta (horas)', 'De 1 a 720.', { inputMode: 'numeric' })}
                    </div>
                </Section>

                <Section title="Requisitos de muestra">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Tipo de muestra" htmlFor="lt-specimen" error={errors.specimen_type}>
                            <Select id="lt-specimen" value={form.specimen_type} onChange={set('specimen_type')}>
                                {options.specimens.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </Select>
                        </Field>
                        {input('container', 'Contenedor', 'Ej.: Tubo tapa lila (EDTA)', { maxLength: 80 })}
                        {input('min_volume_ml', 'Volumen mínimo (mL)', 'Mayor que 0, hasta 9999.99.', { inputMode: 'decimal' })}
                        {input('fasting_hours', 'Horas de ayuno', '0 = no requiere ayuno.', { inputMode: 'numeric' })}
                        <Field label="Conservación" htmlFor="lt-storage" error={errors.storage}>
                            <Select id="lt-storage" value={form.storage} onChange={set('storage')}>
                                {options.storage.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </Select>
                        </Field>
                        {input('stability_hours', 'Estabilidad (horas)', 'Hasta cuándo se puede procesar.', { inputMode: 'numeric' })}
                    </div>
                    <Field label="Preparación del paciente (opcional)" htmlFor="lt-prep" error={errors.preparation_instructions} hint="Hasta 300 caracteres.">
                        <Textarea id="lt-prep" value={form.preparation_instructions} onChange={set('preparation_instructions')} maxLength={300} aria-invalid={!!errors.preparation_instructions} />
                    </Field>
                </Section>

                <Section title="Resultados" description="Los usan el ingreso y la validación de resultados para marcar valores bajos, altos y críticos.">
                    <div className="grid gap-4 sm:grid-cols-[8rem_1fr_1fr]">
                        {input('unit', 'Unidad', 'Ej.: mg/dL', { maxLength: 20 })}
                        {input('ref_low', 'Referencia baja', undefined, { inputMode: 'decimal' })}
                        {input('ref_high', 'Referencia alta', undefined, { inputMode: 'decimal' })}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {input('critical_low', 'Crítico bajo (opcional)', 'Menor que la referencia baja.', { inputMode: 'decimal' })}
                        {input('critical_high', 'Crítico alto (opcional)', 'Mayor que la referencia alta.', { inputMode: 'decimal' })}
                    </div>
                </Section>

                <Section title="Vigencia">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Desde" htmlFor="lt-from" error={errors.valid_from}
                            hint={base ? `Después del ${formatDate(base.valid_from)}, cuando empezó la versión ${base.version}.` : 'Desde hoy.'}>
                            <Input id="lt-from" type="date" value={form.valid_from} min={minFrom} max="2099-12-31" onChange={set('valid_from')} aria-invalid={!!errors.valid_from} />
                        </Field>
                        <Field label="Hasta (opcional)" htmlFor="lt-to" error={errors.valid_to} hint="Último día vigente. Vacío = sin fecha de cierre.">
                            <Input id="lt-to" type="date" value={form.valid_to} min={form.valid_from} max="2099-12-31" onChange={set('valid_to')} aria-invalid={!!errors.valid_to} />
                        </Field>
                    </div>
                </Section>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={save.isPending}>{base ? 'Registrar versión' : 'Registrar prueba'}</Button>
                </div>
            </form>
        </Dialog>
    );
}
