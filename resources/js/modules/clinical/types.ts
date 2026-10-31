export interface Patient {
    id: string;
    code: string;
    given_name: string;
    family_name: string;
    full_name: string;
    birth_date: string;
    age: number;
    sex: 'female' | 'male' | 'other' | 'unknown';
    status: 'active' | 'inactive' | 'deceased';
    document_type: 'DPI' | 'PASAPORTE' | null;
    document_number: string | null;
    document_country: string | null;
    contact: string | null;
    address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    version: number;
}

export interface Appointment {
    id: string;
    starts_at: string;
    ends_at: string;
    status: 'scheduled' | 'confirmed' | 'completed' | 'cancelled';
    reason: string;
    status_reason: string | null;
    next: ('confirmed' | 'completed' | 'cancelled')[];
    patient?: { id: string; code: string; name: string };
    doctor?: { id: string; name: string };
    specialty?: { id: string; name: string };
}

export interface Specialty {
    id: string;
    code: string;
    name: string;
    status: 'active' | 'inactive';
    doctors: number;
}

export interface ScheduleBlock {
    weekday: number;
    starts_at: string;
    ends_at: string;
}

export interface Doctor {
    id: string;
    name: string;
    license_number: string;
    status: 'active' | 'inactive' | 'suspended';
    specialties: { link_id: string; id: string; name: string; is_primary: boolean; valid_from: string }[];
    schedule: ScheduleBlock[];
    hours_per_week: number;
}

export interface Slot {
    doctor_id: string;
    doctor: string;
    starts_at: string;
    ends_at: string;
}

export interface DuplicateCandidate {
    id: string;
    codigo: string;
    nombre: string;
    nacimiento: string;
    documento: string | null;
}
