export interface LabTest { code: string; name: string; specimen_type: string; unit: string; ref_low: number | null; ref_high: number | null }
export interface LabItem {
    id: string; test_code: string; test_name: string; specimen_type: string; unit: string; ref_low: number | null; ref_high: number | null;
    status: 'pending' | 'received' | 'rejected' | 'resulted' | 'validated'; specimen_code: string | null; rejection_reason: string | null;
    result: { id: string; value: number; flag: Flag; status: string; version: number; entered_by: string | null } | null;
}
export type Flag = 'normal' | 'low' | 'high' | 'critical_low' | 'critical_high';
export interface LabOrder { id: string; status: string; priority: 'routine' | 'urgent'; indication: string; ordered_by: string | null; created_at: string; items: LabItem[] }

export const itemStatus: Record<LabItem['status'], { label: string; tone: 'neutral' | 'nido' | 'warn' | 'ok' | 'critical' }> = {
    pending: { label: 'Muestra pendiente', tone: 'neutral' },
    received: { label: 'En proceso', tone: 'nido' },
    rejected: { label: 'Muestra rechazada', tone: 'critical' },
    resulted: { label: 'Por validar', tone: 'warn' },
    validated: { label: 'Validado', tone: 'ok' },
};

export const flagLabel: Record<Flag, { label: string; className: string }> = {
    normal: { label: 'Normal', className: 'text-slate-900' },
    low: { label: 'Bajo', className: 'text-amber-800 font-bold' },
    high: { label: 'Alto', className: 'text-amber-800 font-bold' },
    critical_low: { label: 'Crítico bajo', className: 'text-red-700 font-extrabold' },
    critical_high: { label: 'Crítico alto', className: 'text-red-700 font-extrabold' },
};

export const range = (t: { ref_low: number | null; ref_high: number | null; unit: string }) =>
    t.ref_low !== null || t.ref_high !== null ? `${t.ref_low ?? '—'} a ${t.ref_high ?? '—'} ${t.unit}` : 'Sin rango';
