import { useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { ChevronRight, Search, UserPlus } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { patientStatus, sexLabels } from '@/lib/format';
import type { Patient } from '@/modules/clinical/types';
import { Alert, Badge, Button, Card, EmptyState, Input, PageHeader, Spinner, Table, td, th } from '@/components/ui';

/** Módulo pacientes: una sola caja de búsqueda que entiende nombre, DPI, pasaporte o código. */
export function PatientsPage() {
    const { can } = useAuth();
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();
    const q = params.get('q') ?? '';
    const [text, setText] = useState(q);
    useEffect(() => setText(q), [q]);
    const result = useQuery({
        queryKey: ['pacientes', q],
        queryFn: async () => (await api.get<{ data: Patient[]; meta: { criterio: string; limite: number } }>('/pacientes', { params: { q: q || undefined } })).data,
        placeholderData: keepPreviousData,
        retry: false,
    });

    const onSearch = (e: FormEvent) => {
        e.preventDefault();
        setParams(text.trim() ? { q: text.trim() } : {});
    };

    const rows = result.data?.data ?? [];

    return (
        <>
            <PageHeader title="Pacientes" description="Busque a la persona antes de registrarla, para no duplicar su ficha."
                actions={can('pacientes.gestionar') && <Link to="/pacientes/nuevo"><Button><UserPlus className="size-4" aria-hidden /> Registrar paciente</Button></Link>} />

            <Card>
                <form onSubmit={onSearch} role="search" className="border-b border-slate-200 p-4">
                    <label htmlFor="buscar-paciente" className="mb-1.5 block text-sm font-semibold text-slate-700">Buscar paciente</label>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500" aria-hidden />
                            <Input id="buscar-paciente" className="pl-9" value={text} onChange={(e) => setText(e.target.value)} autoFocus
                                placeholder="Ej.: María Pérez · 2456 78912 0101 · HN-CEN-000012" aria-describedby="ayuda-busqueda" />
                        </div>
                        <Button type="submit">Buscar</Button>
                    </div>
                    <p id="ayuda-busqueda" className="mt-1.5 text-xs text-slate-500">
                        Escriba al menos 3 letras del nombre o apellido, o el DPI, pasaporte o código completo. No importan tildes ni mayúsculas.
                    </p>
                </form>

                {result.isLoading ? <Spinner /> : result.isError ? <div className="p-4"><Alert tone="error">{errorMessage(result.error)}</Alert></div> : (
                    <>
                        <p className="px-4 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-500" aria-live="polite">
                            {q === '' ? 'Pacientes recientes' : `${rows.length} resultado${rows.length === 1 ? '' : 's'} para «${q}»`}
                            {rows.length >= (result.data?.meta.limite ?? 20) && ' · se muestran los primeros; afine la búsqueda'}
                        </p>
                        {rows.length === 0 ? (
                            <EmptyState title={q ? 'No se encontró ningún paciente.' : 'Todavía no hay pacientes registrados.'}>
                                {can('pacientes.gestionar') && <>Si es la primera vez que viene, <Link className="font-semibold text-nido-700 underline" to="/pacientes/nuevo">regístrelo aquí</Link>.</>}
                            </EmptyState>
                        ) : (
                            <Table caption="Pacientes encontrados">
                                <thead><tr>
                                    <th scope="col" className={th}>Paciente</th><th scope="col" className={th}>Código</th>
                                    <th scope="col" className={th}>Edad</th><th scope="col" className={th}>Documento</th>
                                    <th scope="col" className={th}>Teléfono</th><th scope="col" className={th}><span className="sr-only">Abrir</span></th>
                                </tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {rows.map((p) => (
                                        <tr key={p.id} className="cursor-pointer hover:bg-nido-50/60" onClick={() => navigate(`/pacientes/${p.id}`)}>
                                            <td className={td}>
                                                <Link to={`/pacientes/${p.id}`} className="font-semibold text-slate-900 hover:underline" onClick={(e) => e.stopPropagation()}>{p.full_name}</Link>
                                                <span className="ml-2 align-middle">{p.status !== 'active' && <Badge tone={patientStatus[p.status].tone}>{patientStatus[p.status].label}</Badge>}</span>
                                                <p className="text-xs text-slate-500">{sexLabels[p.sex]}</p>
                                            </td>
                                            <td className={`${td} font-mono text-xs`}>{p.code}</td>
                                            <td className={td}>{p.age} años</td>
                                            <td className={td}>{p.document_number ? <><span className="text-xs text-slate-500">{p.document_type}</span> {p.document_number}</> : <span className="text-slate-500">Sin documento</span>}</td>
                                            <td className={td}>{p.contact ?? <span className="text-slate-500">—</span>}</td>
                                            <td className={`${td} text-right`}><ChevronRight className="ml-auto size-4 text-slate-500" aria-hidden /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        )}
                    </>
                )}
            </Card>
        </>
    );
}
