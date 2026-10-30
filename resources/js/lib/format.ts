/** Fechas y horas siempre en la zona del hospital, en español de Guatemala. */

export function hospitalTz(tz?: string): string {
    return tz || 'America/Guatemala';
}

export function formatDate(iso: string, tz?: string, opts: Intl.DateTimeFormatOptions = { dateStyle: 'long' }): string {
    // Una fecha sola (AAAA-MM-DD) se muestra tal cual, sin moverla de día por la zona horaria.
    if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return new Intl.DateTimeFormat('es-GT', { ...opts, timeZone: 'UTC' }).format(new Date(`${iso}T12:00:00Z`));
    }
    return new Intl.DateTimeFormat('es-GT', { ...opts, timeZone: hospitalTz(tz) }).format(new Date(iso));
}

export function formatTime(iso: string, tz?: string): string {
    return new Intl.DateTimeFormat('es-GT', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: hospitalTz(tz) }).format(new Date(iso));
}

export function weekdayDate(iso: string, tz?: string): string {
    return formatDate(iso, tz, { weekday: 'long', day: 'numeric', month: 'long' });
}

/** «Hoy» (AAAA-MM-DD) en la zona del hospital. */
export function todayIn(tz?: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: hospitalTz(tz) }).format(new Date());
}

/** Fecha (AAAA-MM-DD) de un instante, en la zona del hospital. */
export function dateIn(iso: string, tz?: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: hospitalTz(tz) }).format(new Date(iso));
}

export function addDays(date: string, days: number): string {
    const d = new Date(`${date}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
}

export const weekdays = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

export const sexLabels: Record<string, string> = { female: 'Femenino', male: 'Masculino', other: 'Otro', unknown: 'No indicado' };

export const patientStatus: Record<string, { label: string; tone: 'ok' | 'neutral' | 'critical' }> = {
    active: { label: 'Activo', tone: 'ok' },
    inactive: { label: 'Inactivo', tone: 'neutral' },
    deceased: { label: 'Fallecido', tone: 'critical' },
};

export const appointmentStatus: Record<string, { label: string; tone: 'nido' | 'ok' | 'neutral' | 'warn' | 'critical'; action?: string }> = {
    scheduled: { label: 'Programada', tone: 'nido' },
    confirmed: { label: 'Confirmada', tone: 'ok', action: 'Confirmar' },
    completed: { label: 'Atendida', tone: 'neutral', action: 'Marcar atendida' },
    cancelled: { label: 'Cancelada', tone: 'critical', action: 'Cancelar' },
};

/** Resume un horario semanal: «Lun a vie · 08:00–12:00, 14:00–17:00». */
export function scheduleSummary(blocks: { weekday: number; starts_at: string; ends_at: string }[]): string {
    if (blocks.length === 0) return 'Sin horario';
    const byDay = new Map<number, string>();
    for (const b of blocks) byDay.set(b.weekday, [byDay.get(b.weekday), `${b.starts_at}–${b.ends_at}`].filter(Boolean).join(', '));
    const groups: { days: number[]; hours: string }[] = [];
    for (const [day, hours] of [...byDay.entries()].sort((a, b) => a[0] - b[0])) {
        const last = groups.at(-1);
        if (last && last.hours === hours && last.days.at(-1) === day - 1) last.days.push(day);
        else groups.push({ days: [day], hours });
    }
    const short = (d: number) => weekdays[d].slice(0, 3).toLowerCase();
    return groups.map((g) => `${g.days.length > 1 ? `${short(g.days[0])} a ${short(g.days.at(-1)!)}` : short(g.days[0])} · ${g.hours}`).join(' | ');
}
