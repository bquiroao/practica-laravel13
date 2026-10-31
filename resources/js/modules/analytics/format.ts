/**
 * Módulo reportes y analytics básicos: el único lugar donde se redondea un indicador (C-10 de la revisión técnica).
 * La API entrega el valor completo; aquí se muestra con la precisión de su unidad.
 */

export type Unit = '%' | 'cantidad' | 'dias' | 'minutos';

const decimal = new Intl.NumberFormat('es-GT', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const integer = new Intl.NumberFormat('es-GT', { maximumFractionDigits: 0 });

export function formatIndicator(value: number | null | undefined, unit: Unit): string {
    if (value === null || value === undefined) return 'Sin datos';
    if (unit === '%') return `${decimal.format(value)} %`;
    if (unit === 'dias') return `${decimal.format(value)} ${Math.abs(value - 1) < 0.05 ? 'día' : 'días'}`;
    if (unit === 'minutos') return duration(value);
    return integer.format(value);
}

/** Minutos como «45 min» o «2 h 15 min»: una espera se lee mejor en horas y minutos. */
function duration(minutes: number): string {
    const total = Math.round(minutes);
    const h = Math.floor(total / 60);
    const m = total % 60;
    return h === 0 ? `${m} min` : m === 0 ? `${integer.format(h)} h` : `${integer.format(h)} h ${m} min`;
}

export function formatCount(value: number): string {
    return integer.format(value);
}

/** Diferencia contra el período anterior: puntos en los porcentajes, unidades en lo demás. */
export function formatDifference(diff: number, unit: Unit): string {
    const sign = diff > 0 ? '+' : diff < 0 ? '−' : '';
    const abs = Math.abs(diff);
    if (unit === '%') return `${sign}${decimal.format(abs)} ${abs === 1 ? 'punto' : 'puntos'}`;
    if (unit === 'dias') return `${sign}${decimal.format(abs)} días`;
    if (unit === 'minutos') return `${sign}${duration(abs)}`;
    return `${sign}${integer.format(abs)}`;
}

/** Parte de un desglose como porcentaje del total, calculado al mostrar desde los conteos. */
export function share(part: number, total: number): string {
    return total > 0 ? `${decimal.format((part / total) * 100)} %` : '—';
}
