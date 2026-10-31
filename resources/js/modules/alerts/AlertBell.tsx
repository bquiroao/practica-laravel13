import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { summaryKey } from './types';

/**
 * Módulo alertas críticas: el número de la campana, las alertas que esperan el acuse de este usuario. Se revisa cada
 * 30 segundos. Cambio de la semana 12: el título de la pestaña dice «(2) …» para que se vea aunque esté en segundo plano.
 */
export function AlertBell() {
    const summary = useQuery({
        queryKey: summaryKey,
        queryFn: async () => (await api.get<{ data: { pendientes: number; abiertas: number } }>('/alertas/resumen')).data.data,
        refetchInterval: 30_000,
        refetchIntervalInBackground: true,
    });
    const pending = summary.data?.pendientes ?? 0;

    useEffect(() => {
        const base = document.title.replace(/^\(\d+\)\s/, '');
        document.title = pending > 0 ? `(${pending}) ${base}` : base;
    }, [pending]);

    if (pending === 0) return null;
    return (
        <span className="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-red-700 px-1 text-xs font-bold text-white">
            {pending > 99 ? '99+' : pending}
            <span className="sr-only"> {pending === 1 ? 'alerta espera su acuse' : 'alertas esperan su acuse'}</span>
        </span>
    );
}
