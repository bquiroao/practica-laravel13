/** Módulo alertas críticas y notificaciones internas: tipos de la API /alertas. */

export type AlertStatus = 'CREADA' | 'ENVIADA' | 'ESCALADA' | 'ACUSADA' | 'CERRADA';

export interface CriticalAlert {
    id: string;
    patient: { id: string; code: string };
    source: 'laboratorio' | 'signos_vitales';
    source_label: string;
    priority: 'CRITICA' | 'ALTA';
    priority_label: string;
    title: string;
    description: string;
    status: AlertStatus;
    status_label: string;
    level: number;
    level_label: string;
    deadline_at: string | null;
    escalation_exhausted: boolean;
    acknowledged_by: string | null;
    acknowledged_at: string | null;
    closed_by: string | null;
    closed_at: string | null;
    close_note: string | null;
    created_at: string;
    notified_to_me: boolean;
    can_acknowledge: boolean;
    can_close: boolean;
}

export interface AlertEvent { id: string; event: string; event_label: string; level: number; actor: string; detail: Record<string, unknown>; occurred_at: string }

export interface AlertNotification { recipient: string | null; level: number; attempts: number; delivered: boolean; read_at: string | null }

export interface AlertDetail extends CriticalAlert { events: AlertEvent[]; notifications: AlertNotification[] }

export interface AlertPolicy { priority: 'CRITICA' | 'ALTA'; label: string; ack_minutes: number }

/** Estado con texto, no solo color. */
export const statusTone: Record<AlertStatus, 'critical' | 'warn' | 'nido' | 'ok' | 'neutral'> = {
    CREADA: 'neutral', ENVIADA: 'warn', ESCALADA: 'critical', ACUSADA: 'nido', CERRADA: 'ok',
};

export const summaryKey = ['alertas', 'resumen'] as const;
