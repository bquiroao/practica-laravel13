import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertOctagon, CheckCircle2, Plus, ShieldAlert, ShieldQuestion } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, ruleError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/cn';
import { Button, Spinner } from '@/components/ui';
import { AllergyFormDialog, AllergyStatusDialog } from './AllergyDialogs';
import { levelStyle, type AllergyAlert, type AllergyItem } from './types';

/**
 * Módulo alergias clínicas y alerta visual en el expediente: el banner que va debajo del nombre del paciente, en la
 * ficha y en el expediente. Muestra las alergias activas de mayor a menor riesgo y, si la alerta es ALTA o CRÍTICA,
 * pide «He leído la alerta» a cada usuario (RN-09). Las demás pantallas solo lo colocan con el id del paciente.
 */
export function AllergyBanner({ patientId, className }: { patientId: string; className?: string }) {
    const { can } = useAuth();
    const queryClient = useQueryClient();
    const allowed = can('expediente.leer') || can('alergias.gestionar') || can('prescripciones.crear');
    const manage = can('alergias.gestionar');
    const [adding, setAdding] = useState(false);
    const [closing, setClosing] = useState<AllergyItem | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const key = ['alergias', patientId];

    const alert = useQuery({
        queryKey: key,
        queryFn: async () => (await api.get<{ data: AllergyAlert }>(`/expediente/${patientId}/alergias`)).data.data,
        enabled: allowed,
    });
    const update = (data: AllergyAlert, message: string) => {
        // Prescripciones revisa las alergias en el servidor al emitir cada receta: no hay nada más que refrescar.
        queryClient.setQueryData(key, data);
        setNotice(message);
    };
    const acknowledge = useMutation({
        mutationFn: async (fingerprint: string) => (await api.post<{ data: AllergyAlert }>(`/expediente/${patientId}/alergias/reconocimiento`, { fingerprint })).data.data,
        onSuccess: (data) => update(data, 'Alerta reconocida. Queda registrado que usted la leyó.'),
        onError: (e) => { if (ruleError(e)?.code === 'ALERTA_CAMBIO') queryClient.invalidateQueries({ queryKey: key }); },
    });

    if (!allowed) return null;
    if (alert.isLoading) return <div className={className}><Spinner label="Revisando alergias…" /></div>;
    if (alert.isError) return <p className={cn('rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200', className)} role="alert">No se pudieron cargar las alergias: {errorMessage(alert.error)}</p>;

    const a = alert.data!;
    const style = levelStyle[a.level];
    const Icon = a.level === 'CRITICA' ? AlertOctagon : a.level === 'SIN_REGISTRO' ? ShieldQuestion : ShieldAlert;
    const changed = ruleError(acknowledge.error)?.code === 'ALERTA_CAMBIO';

    return (
        <section aria-label="Alergias del paciente" className={cn('rounded-lg px-4 py-3', style.box, className)}
            role={a.requires_acknowledgement ? 'alert' : undefined}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <p className="flex items-center gap-2 font-bold">
                    <Icon className="size-5 shrink-0" aria-hidden />
                    {a.level === 'SIN_REGISTRO' ? 'Sin alergias registradas' : `Alergias · alerta ${a.level_label.toLowerCase()}`}
                </p>
                {manage && <Button size="sm" variant="secondary" onClick={() => { setNotice(null); setAdding(true); }}><Plus className="size-4" aria-hidden /> Registrar alergia</Button>}
            </div>

            {a.level === 'SIN_REGISTRO' ? (
                <p className="mt-1 text-sm">Esto no confirma que no tenga alergias: pregúntele al paciente antes de indicar medicamentos.</p>
            ) : (
                <ul className="mt-2 flex flex-wrap gap-2">
                    {a.allergies.map((x) => (
                        <li key={x.id} className={cn('rounded-lg px-3 py-1.5 text-sm', levelStyle[x.level].chip)}>
                            <span className="font-bold">{x.allergen}</span> · {x.severity_label}{x.type_label !== 'Alergia' ? ` (${x.type_label.toLowerCase()})` : ''}
                            {x.reaction && <span className="block text-xs opacity-90">{x.reaction}</span>}
                            {manage && <button type="button" onClick={() => { setNotice(null); setClosing(x); }} className="mt-0.5 block text-xs font-semibold underline">Inactivar o resolver</button>}
                        </li>
                    ))}
                </ul>
            )}

            {a.requires_acknowledgement && (
                <div className="mt-3 flex flex-wrap items-center gap-3">
                    <Button size="sm" variant="danger" loading={acknowledge.isPending} onClick={() => acknowledge.mutate(a.fingerprint)}>He leído la alerta</Button>
                    <span className="text-xs">Confirme que la leyó antes de indicar medicamentos o procedimientos.</span>
                </div>
            )}
            {changed && <p className="mt-2 text-sm font-semibold" role="alert">Las alergias cambiaron mientras la leía. Revise la lista actualizada y vuelva a confirmar.</p>}
            {acknowledge.isError && !changed && <p className="mt-2 text-sm font-semibold" role="alert">{errorMessage(acknowledge.error)}</p>}
            {a.acknowledged && <p className="mt-2 flex items-center gap-1 text-xs font-semibold"><CheckCircle2 className="size-4" aria-hidden /> Usted ya reconoció esta alerta.</p>}
            {notice && <p className="mt-2 text-sm font-semibold" role="status">{notice}</p>}

            {a.history.length > 0 && (
                <details className="mt-2 text-sm">
                    <summary className="cursor-pointer font-semibold">Alergias inactivas o resueltas ({a.history.length})</summary>
                    <ul className="mt-1 space-y-0.5">
                        {a.history.map((x) => (
                            <li key={x.id}>{x.allergen} · {x.status_label.toLowerCase()}{x.resolved_at ? ` el ${formatDate(x.resolved_at, undefined, { dateStyle: 'medium' })}` : ''}</li>
                        ))}
                    </ul>
                </details>
            )}

            {adding && <AllergyFormDialog patientId={patientId} onClose={() => setAdding(false)}
                onDone={(data, allergen) => { setAdding(false); update(data, `Alergia a ${allergen} registrada.`); }} />}
            {closing && <AllergyStatusDialog allergy={closing} onClose={() => setClosing(null)}
                onDone={(data, label) => { setClosing(null); update(data, `La alergia a ${closing.allergen} quedó ${label}.`); }} />}
        </section>
    );
}
