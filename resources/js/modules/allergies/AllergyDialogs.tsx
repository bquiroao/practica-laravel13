import { useState, type FormEvent } from 'react';
import { useMutation } from '@tanstack/react-query';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { todayIn } from '@/lib/format';
import { useAuth } from '@/auth/AuthContext';
import { Alert, Button, Dialog, Field, Input, Select, Textarea } from '@/components/ui';
import { allergyTypes, severities, type AllergyAlert, type AllergyItem } from './types';

/** Módulo alergias clínicas (CU-01): registrar una alergia verificada. */
export function AllergyFormDialog({ patientId, onClose, onDone }: { patientId: string; onClose: () => void; onDone: (alert: AllergyAlert, allergen: string) => void }) {
    const { session } = useAuth();
    const [form, setForm] = useState({ allergen_name: '', allergen_code: '', allergy_type: 'allergy', severity: 'moderate', reaction: '', onset_date: '' });
    const save = useMutation({
        mutationFn: async () => (await api.post<{ data: AllergyItem; alert: AllergyAlert }>(`/expediente/${patientId}/alergias`, {
            ...form, allergen_code: form.allergen_code || null, reaction: form.reaction || null, onset_date: form.onset_date || null,
        })).data,
        onSuccess: (r) => onDone(r.alert, r.data.allergen),
    });
    const errors = fieldErrors(save.error);
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };
    const submit = (e: FormEvent) => { e.preventDefault(); save.mutate(); };

    return (
        <Dialog open onClose={onClose} title="Registrar alergia">
            <form className="space-y-4" onSubmit={submit} noValidate>
                <p className="text-sm text-slate-600">Quedará registrada a su nombre como alergia verificada.</p>
                {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <div className="grid gap-4 sm:grid-cols-[1fr_9rem]">
                    <Field label="Alérgeno" htmlFor="al-name" error={errors.allergen_name} hint="Medicamento, alimento o sustancia. Ej.: Penicilina, Látex, Maní.">
                        <Input id="al-name" value={form.allergen_name} onChange={set('allergen_name')} aria-invalid={!!errors.allergen_name} autoFocus />
                    </Field>
                    <Field label="Código (opcional)" htmlFor="al-code" error={errors.allergen_code} hint="Ej.: J01CA04">
                        <Input id="al-code" value={form.allergen_code} onChange={set('allergen_code')} aria-invalid={!!errors.allergen_code} />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Tipo" htmlFor="al-type" error={errors.allergy_type}>
                        <Select id="al-type" value={form.allergy_type} onChange={set('allergy_type')}>
                            {Object.entries(allergyTypes).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                        </Select>
                    </Field>
                    <Field label="Severidad" htmlFor="al-sev" error={errors.severity} hint="«Desconocida» se trata como grave.">
                        <Select id="al-sev" value={form.severity} onChange={set('severity')}>
                            {Object.entries(severities).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                        </Select>
                    </Field>
                </div>
                <Field label="Reacción (opcional)" htmlFor="al-reaction" error={errors.reaction} hint="Qué le pasó. Ej.: «Urticaria y dificultad para respirar».">
                    <Textarea id="al-reaction" value={form.reaction} onChange={set('reaction')} maxLength={500} aria-invalid={!!errors.reaction} />
                </Field>
                <Field label="Desde cuándo (opcional)" htmlFor="al-onset" error={errors.onset_date}>
                    <Input id="al-onset" type="date" value={form.onset_date} max={todayIn(session?.hospital.timezone)} onChange={set('onset_date')} aria-invalid={!!errors.onset_date} />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={save.isPending} disabled={!form.allergen_name.trim() && !form.allergen_code.trim()}>Registrar alergia</Button>
                </div>
            </form>
        </Dialog>
    );
}

/** Módulo alergias clínicas (CU-06, RN-10): una alergia no se borra; se inactiva o se resuelve con motivo. */
export function AllergyStatusDialog({ allergy, onClose, onDone }: { allergy: AllergyItem; onClose: () => void; onDone: (alert: AllergyAlert, label: string) => void }) {
    const [status, setStatus] = useState<'resolved' | 'inactive'>('resolved');
    const [reason, setReason] = useState('');
    const save = useMutation({
        mutationFn: async () => (await api.patch<{ data: AllergyItem; alert: AllergyAlert }>(`/alergias/${allergy.id}/estado`, { status, reason })).data,
        onSuccess: (r) => onDone(r.alert, status === 'resolved' ? 'resuelta' : 'inactiva'),
    });
    const errors = fieldErrors(save.error);

    return (
        <Dialog open onClose={onClose} title={`Alergia a ${allergy.allergen}`}>
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                <p className="text-sm text-slate-600">La alergia no se borra: queda en el historial y deja de mostrarse en la alerta y de revisarse al recetar.</p>
                {save.isError && Object.keys(errors).length === 0 && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <fieldset className="space-y-2 text-sm">
                    <legend className="mb-1 font-semibold text-slate-700">¿Qué pasó?</legend>
                    <label className="flex items-start gap-2"><input type="radio" className="mt-1 accent-nido-700" checked={status === 'resolved'} onChange={() => setStatus('resolved')} />
                        <span><strong>Ya no la tiene</strong> (resuelta), p. ej. por una prueba de provocación negativa.</span></label>
                    <label className="flex items-start gap-2"><input type="radio" className="mt-1 accent-nido-700" checked={status === 'inactive'} onChange={() => setStatus('inactive')} />
                        <span><strong>Se registró por error</strong> (inactiva).</span></label>
                </fieldset>
                <Field label="Motivo" htmlFor="al-reason" error={errors.reason} hint="Queda en la bitácora. Ej.: «Prueba de provocación negativa».">
                    <Textarea id="al-reason" value={reason} onChange={(e) => setReason(e.target.value)} aria-invalid={!!errors.reason} />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={save.isPending} disabled={reason.trim().length < 5}>Guardar</Button>
                </div>
            </form>
        </Dialog>
    );
}
