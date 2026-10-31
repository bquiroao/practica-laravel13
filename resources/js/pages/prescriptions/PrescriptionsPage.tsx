import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/cn';
import { Alert, Badge, Card, EmptyState, PageHeader, Spinner } from '@/components/ui';
import type { Medication, PrescriptionItem } from '@/pages/records/PrescriptionsTab';

/** Prescripciones: recetas recientes del hospital y el catálogo de medicamentos (provisional). */
export function PrescriptionsPage() {
    const { session } = useAuth();
    const [params, setParams] = useSearchParams();
    const tab = params.get('vista') === 'catalogo' ? 'catalogo' : 'recetas';
    const list = useQuery({ queryKey: ['recetas', 'todas'], queryFn: async () => (await api.get<{ data: PrescriptionItem[] }>('/recetas')).data.data, enabled: tab === 'recetas' });
    const meds = useQuery({ queryKey: ['medicamentos'], queryFn: async () => (await api.get<{ data: Medication[] }>('/medicamentos')).data.data, enabled: tab === 'catalogo' });

    return (
        <>
            <PageHeader title="Prescripciones" description="Para recetar, abra el expediente del paciente y use la pestaña «Recetas»: el sistema revisa sus alergias." />
            <div role="tablist" aria-label="Secciones" className="mb-4 inline-flex rounded-lg bg-white p-1 ring-1 ring-slate-200">
                {[['recetas', 'Recetas recientes'], ['catalogo', 'Catálogo de medicamentos']].map(([k, l]) => (
                    <button key={k} role="tab" aria-selected={tab === k} onClick={() => setParams(k === 'recetas' ? {} : { vista: k })}
                        className={cn('rounded-md px-4 py-1.5 text-sm font-semibold', tab === k ? 'bg-nido-700 text-white' : 'text-slate-600 hover:bg-slate-100')}>{l}</button>
                ))}
            </div>
            {tab === 'recetas' ? (list.isLoading ? <Spinner /> : list.isError ? <Alert tone="error">{errorMessage(list.error)}</Alert> : list.data!.length === 0 ? (
                <Card><EmptyState title="Todavía no hay recetas." /></Card>
            ) : (
                <Card>
                    <ul className="divide-y divide-slate-100">
                        {list.data!.map((p) => (
                            <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                                <div>
                                    <p className="font-semibold text-slate-900">{p.medication_name} <span className="font-normal text-slate-600">· {p.dose} · {p.frequency}</span></p>
                                    <p className="text-slate-600"><Link className="font-semibold text-nido-700 hover:underline" to={`/expediente/${p.patient.id}?vista=recetas`}>{p.patient.name}</Link> · {formatDate(p.issued_at, session?.hospital.timezone)} · {p.prescriber}</p>
                                </div>
                                {p.status === 'issued' ? <Badge tone="ok">Vigente</Badge> : <Badge tone="critical">Anulada</Badge>}
                            </li>
                        ))}
                    </ul>
                </Card>
            )) : (
                <Card>
                    <p className="border-b border-slate-200 px-5 py-3 text-sm text-slate-600">Catálogo provisional. Lo administrará el módulo de catálogo de medicamentos.</p>
                    {meds.isLoading ? <Spinner /> : (
                        <ul className="divide-y divide-slate-100">
                            {meds.data?.map((m) => <li key={m.code} className="flex justify-between gap-2 px-5 py-3 text-sm"><span><strong>{m.name}</strong> <span className="font-mono text-xs text-slate-500">{m.code}</span></span><span className="text-slate-600">{m.presentation} · {m.route}</span></li>)}
                        </ul>
                    )}
                </Card>
            )}
        </>
    );
}
