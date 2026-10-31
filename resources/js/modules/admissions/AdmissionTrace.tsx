import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { formatDate, formatTime } from '@/lib/format';
import { Alert, Spinner } from '@/components/ui';

/** Módulo admisión (CU-06): de dónde llegó el paciente y la trazabilidad de su internamiento, en orden (RN-05). */

interface Admission { id: string; source: string | null; source_label: string; admitted_at: string; status: 'activa' | 'cerrada'; stays: number }
interface TraceEvent { accion: string; usuario: string; detalle: Record<string, string>; ocurrido_en: string }

const actions: Record<string, string> = {
    ADMISION_CREADA: 'Admisión registrada',
    CAMA_ASIGNADA: 'Cama asignada',
    TRASLADO: 'Traslado de cama',
    ALTA: 'Alta',
};

export function AdmissionTrace({ admissionId }: { admissionId: string }) {
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const canTrace = can('admisiones.gestionar') || can('auditoria.consultar');
    const admission = useQuery({
        queryKey: ['admision', admissionId],
        queryFn: async () => (await api.get<{ data: Admission }>(`/admisiones/${admissionId}`)).data.data,
    });
    const events = useQuery({
        queryKey: ['admision', admissionId, 'eventos'],
        queryFn: async () => (await api.get<{ data: TraceEvent[] }>(`/admisiones/${admissionId}/eventos`)).data.data,
        enabled: canTrace,
    });

    if (admission.isLoading) return <Spinner label="Cargando la admisión…" />;
    if (admission.isError) return <Alert tone="error">{errorMessage(admission.error)}</Alert>;
    const a = admission.data!;

    return (
        <div className="space-y-2 text-sm">
            <p className="text-slate-700">
                {a.source ? <>Llegó por <strong>{a.source_label.toLowerCase()}</strong></> : 'Internado'} el {formatDate(a.admitted_at, tz, { dateStyle: 'medium' })} a las {formatTime(a.admitted_at, tz)}
                {a.stays > 1 ? ` · ${a.stays - 1} traslado${a.stays > 2 ? 's' : ''}` : ''}.
            </p>
            {canTrace && (
                events.isLoading ? <Spinner label="Cargando la trazabilidad…" /> : events.isError ? <Alert tone="error">{errorMessage(events.error)}</Alert> : (
                    <ol className="space-y-1.5 border-l-2 border-nido-200 pl-3" aria-label="Trazabilidad de la admisión">
                        {events.data!.length === 0 && <li className="text-slate-600">Este internamiento se cargó antes del módulo de admisión y no tiene eventos.</li>}
                        {events.data!.map((e, i) => (
                            <li key={i}>
                                <span className="font-semibold text-slate-900">{actions[e.accion] ?? e.accion}</span>
                                {e.detalle.cama && e.accion !== 'ALTA' && <span className="text-slate-700"> · {e.detalle.cama}</span>}
                                <span className="block text-xs text-slate-600">{e.usuario} · {formatDate(e.ocurrido_en, tz, { dateStyle: 'short' })} {formatTime(e.ocurrido_en, tz)}</span>
                            </li>
                        ))}
                    </ol>
                )
            )}
        </div>
    );
}
