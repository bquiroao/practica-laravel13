/** Módulo catálogo de medicamentos: tipos de la API /catalogo/medicamentos. */

export type MedicationState = 'vigente' | 'programada' | 'reemplazada' | 'baja';

export interface CatalogMedication {
    id: string;
    code: string;
    version: number;
    name: string;
    active_ingredient: string;
    strength_value: string;
    strength_unit: string;
    strength: string;
    dosage_form: string;
    dosage_form_label: string;
    route: string;
    route_label: string;
    presentation: string;
    sanitary_registration: string | null;
    therapeutic_group: string;
    therapeutic_group_label: string;
    allergy_groups: string[];
    legacy_code: string | null;
    valid_from: string;
    valid_to: string | null;
    close_reason: string | null;
    state: MedicationState;
    created_by: string | null;
    closed_by: string | null;
    created_at: string | null;
}

export interface MedicationDetailMeta {
    fecha: string;
    vigente: boolean;
    versiones: number;
    ultima_version: number;
    abierto: boolean;
    recetas: number;
    puede_eliminarse: boolean;
}

export interface Option { value: string; label: string }

export interface CatalogOptions {
    units: string[];
    forms: Option[];
    routes: Option[];
    therapeutic_groups: Option[];
    allergy_groups: Option[];
}

/** La ficha que se envía en un alta o una versión nueva. */
export interface MedicationSheet {
    name: string;
    active_ingredient: string;
    strength_value: string;
    strength_unit: string;
    dosage_form: string;
    route: string;
    presentation: string;
    sanitary_registration: string;
    therapeutic_group: string;
    allergy_groups: string[];
    valid_from: string;
}

/** Estado con texto, no solo color (semana 9). */
export const stateStyle: Record<MedicationState, { label: string; tone: 'ok' | 'nido' | 'neutral' | 'critical' }> = {
    vigente: { label: 'Vigente', tone: 'ok' },
    programada: { label: 'Programada', tone: 'nido' },
    reemplazada: { label: 'Reemplazada', tone: 'neutral' },
    baja: { label: 'Dado de baja', tone: 'critical' },
};
