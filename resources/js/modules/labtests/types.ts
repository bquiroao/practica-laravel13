/** Módulo catálogo de pruebas de laboratorio: tipos de la API /catalogo/pruebas-laboratorio. */

export type LabTestState = 'vigente' | 'programada' | 'cerrada';

export interface CatalogLabTest {
    id: string;
    code: string;
    version: number;
    name: string;
    section: string;
    section_label: string;
    turnaround_hours: number;
    specimen_type: string;
    specimen_label: string;
    container: string;
    min_volume_ml: number;
    fasting_hours: number;
    preparation_instructions: string | null;
    storage: string;
    storage_label: string;
    stability_hours: number;
    unit: string;
    ref_low: number | null;
    ref_high: number | null;
    critical_low: number | null;
    critical_high: number | null;
    valid_from: string;
    valid_to: string | null;
    state: LabTestState;
    expires_in_days: number | null;
    expiring: boolean;
    initial_load: boolean;
    created_by: string | null;
    closed_by: string | null;
    created_at: string | null;
}

export interface Option { value: string; label: string }

export interface LabCatalogOptions { sections: Option[]; specimens: Option[]; storage: Option[]; expiring_days: number }

/** La ficha que se envía en un alta o una versión nueva; los números van como texto para conservar lo capturado. */
export interface LabTestSheet {
    code: string;
    name: string;
    section: string;
    turnaround_hours: string;
    specimen_type: string;
    container: string;
    min_volume_ml: string;
    fasting_hours: string;
    preparation_instructions: string;
    storage: string;
    stability_hours: string;
    unit: string;
    ref_low: string;
    ref_high: string;
    critical_low: string;
    critical_high: string;
    valid_from: string;
    valid_to: string;
}

/** Estado con texto, no solo color. */
export const stateStyle: Record<LabTestState, { label: string; tone: 'ok' | 'nido' | 'neutral' }> = {
    vigente: { label: 'Vigente', tone: 'ok' },
    programada: { label: 'Programada', tone: 'nido' },
    cerrada: { label: 'Cerrada', tone: 'neutral' },
};

export const hours = (h: number) => (h === 1 ? '1 hora' : `${h} horas`);
