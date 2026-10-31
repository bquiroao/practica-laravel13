/** Módulo alergias clínicas: forma de la alerta que devuelve GET /expediente/{paciente}/alergias. */

export type AlertLevel = 'SIN_REGISTRO' | 'INFORMATIVA' | 'MODERADA' | 'ALTA' | 'CRITICA';

export interface AllergyItem {
    id: string;
    allergen: string;
    allergen_code: string | null;
    type_label: string;
    severity: string;
    severity_label: string;
    reaction: string | null;
    onset_date: string | null;
    status: 'active' | 'inactive' | 'resolved';
    status_label: string;
    resolved_at: string | null;
    recorded_at: string | null;
    verified_by: string | null;
    level: AlertLevel;
    level_label: string;
}

export interface AllergyAlert {
    patient: { id: string; code: string };
    level: AlertLevel;
    level_label: string;
    requires_acknowledgement: boolean;
    acknowledged: boolean;
    fingerprint: string;
    allergies: AllergyItem[];
    history: AllergyItem[];
}

export const allergyTypes = { allergy: 'Alergia', intolerance: 'Intolerancia', unknown: 'Sin clasificar' } as const;
export const severities = { mild: 'Leve', moderate: 'Moderada', severe: 'Grave', life_threatening: 'Pone en riesgo la vida', unknown: 'Desconocida' } as const;

/** DEC-02: SIN_REGISTRO va en gris neutro, nunca en verde, porque no confirma que el paciente no tenga alergias. */
export const levelStyle: Record<AlertLevel, { box: string; chip: string }> = {
    CRITICA: { box: 'bg-red-50 text-red-900 ring-2 ring-red-700 border-l-8 border-red-700', chip: 'bg-red-700 text-white' },
    ALTA: { box: 'bg-red-50 text-red-900 ring-1 ring-red-300 border-l-8 border-red-600', chip: 'bg-red-100 text-red-900 ring-1 ring-red-300' },
    MODERADA: { box: 'bg-amber-50 text-amber-900 ring-1 ring-amber-300 border-l-8 border-amber-500', chip: 'bg-amber-100 text-amber-900 ring-1 ring-amber-300' },
    INFORMATIVA: { box: 'bg-nido-50 text-nido-900 ring-1 ring-nido-200 border-l-8 border-nido-500', chip: 'bg-white text-nido-900 ring-1 ring-nido-200' },
    SIN_REGISTRO: { box: 'bg-slate-50 text-slate-800 ring-1 ring-slate-300 border-l-8 border-slate-400', chip: 'bg-white text-slate-800 ring-1 ring-slate-300' },
};
