import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ChevronRight, Search } from 'lucide-react';
import { api, errorMessage } from '@/lib/api';
import type { Patient } from '@/modules/clinical/types';
import { Alert, Button, Card, EmptyState, Input, PageHeader, Spinner } from '@/components/ui';

/** Entrada al expediente: buscar al paciente y abrir su historia clínica. */
export function RecordSearchPage() {
    const [text, setText] = useState('');
    const [q, setQ] = useState('');
    const result = useQuery({
        queryKey: ['pacientes', q],
        queryFn: async () => (await api.get<{ data: Patient[] }>('/pacientes', { params: { q: q || undefined } })).data.data,
        retry: false,
    });
    const submit = (e: FormEvent) => { e.preventDefault(); setQ(text.trim()); };

    return (
        <>
            <PageHeader title="Expediente" description="Busque al paciente para ver y escribir en su historia clínica." />
            <Card>
                <form onSubmit={submit} role="search" className="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row">
                    <label htmlFor="exp-buscar" className="sr-only">Buscar paciente</label>
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500" aria-hidden />
                        <Input id="exp-buscar" className="pl-9" autoFocus value={text} onChange={(e) => setText(e.target.value)} placeholder="Nombre, DPI o código del paciente" />
                    </div>
                    <Button type="submit">Buscar</Button>
                </form>
                <p className="px-4 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{q ? `Resultados para «${q}»` : 'Pacientes recientes'}</p>
                {result.isLoading ? <Spinner /> : result.isError ? <div className="p-4"><Alert tone="error">{errorMessage(result.error)}</Alert></div>
                    : result.data!.length === 0 ? <EmptyState title="No se encontró ningún paciente." /> : (
                        <ul className="divide-y divide-slate-100">
                            {result.data!.map((p) => (
                                <li key={p.id}>
                                    <Link to={`/expediente/${p.id}`} className="flex items-center justify-between gap-3 px-4 py-3 hover:bg-nido-50/60">
                                        <span><span className="font-semibold text-slate-900">{p.full_name}</span>
                                            <span className="block text-xs text-slate-500">{p.code} · {p.age} años</span></span>
                                        <ChevronRight className="size-4 text-slate-500" aria-hidden />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
            </Card>
        </>
    );
}
