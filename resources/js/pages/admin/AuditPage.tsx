import { useState, type FormEvent } from 'react';
import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query';
import { ShieldCheck } from 'lucide-react';
import { api, errorMessage, type Paginated } from '@/lib/api';
import { Alert, Badge, Button, Card, EmptyState, Field, Input, PageHeader, Select, Spinner, Table, td, th } from '@/components/ui';
import { Pagination } from '@/components/Pagination';

interface AuditRow {
    id: number;
    secuencia: number;
    ocurrido_en: string;
    usuario: string | null;
    modulo: string;
    accion: string;
    resultado: 'exito' | 'denegado' | 'error';
    recurso: string | null;
    ip: string | null;
    detalle: Record<string, unknown>;
}
interface Filters { modulo: string; resultado: string; desde: string; hasta: string }

const empty: Filters = { modulo: '', resultado: '', desde: '', hasta: '' };
const tone = { exito: 'ok', denegado: 'warn', error: 'critical' } as const;

/** Módulo gobernanza-auditoría: bitácora del hospital, de solo inserción y con sello encadenado. */
export function AuditPage() {
    const [page, setPage] = useState(1);
    const [draft, setDraft] = useState<Filters>(empty);
    const [filters, setFilters] = useState<Filters>(empty);
    const logs = useQuery({
        queryKey: ['auditoria', page, filters],
        queryFn: async () => (await api.get<Paginated<AuditRow>>('/auditoria', {
            params: { page, ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v)) },
        })).data,
        placeholderData: keepPreviousData,
    });
    const verify = useMutation({
        mutationFn: async () => (await api.get<{ integra: boolean; revisados: number; primera_falla: number | null }>('/auditoria/verificacion')).data,
    });

    const apply = (e: FormEvent) => {
        e.preventDefault();
        setPage(1);
        setFilters(draft);
    };

    return (
        <>
            <PageHeader title="Auditoría" description="Movimientos del hospital. La bitácora no se puede editar ni borrar; cada registro se sella con el anterior."
                actions={<Button variant="secondary" onClick={() => verify.mutate()} loading={verify.isPending}><ShieldCheck className="size-4" aria-hidden /> Verificar integridad</Button>} />

            {verify.data && (
                <div className="mb-4">
                    {verify.data.integra
                        ? <Alert tone="success">Cadena íntegra: se revisaron {verify.data.revisados} registros.</Alert>
                        : <Alert tone="error">La cadena está alterada desde el registro #{verify.data.primera_falla}.</Alert>}
                </div>
            )}
            {verify.isError && <div className="mb-4"><Alert tone="error">{errorMessage(verify.error)}</Alert></div>}

            <Card>
                <form onSubmit={apply} className="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                    <Field label="Módulo" htmlFor="f-modulo">
                        <Select id="f-modulo" value={draft.modulo} onChange={(e) => setDraft({ ...draft, modulo: e.target.value })}>
                            <option value="">Todos</option>
                            <option value="autenticacion">Autenticación</option>
                            <option value="usuarios">Usuarios</option>
                            <option value="rbac">Roles y permisos</option>
                        </Select>
                    </Field>
                    <Field label="Resultado" htmlFor="f-resultado">
                        <Select id="f-resultado" value={draft.resultado} onChange={(e) => setDraft({ ...draft, resultado: e.target.value })}>
                            <option value="">Todos</option><option value="exito">Éxito</option><option value="denegado">Denegado</option><option value="error">Error</option>
                        </Select>
                    </Field>
                    <Field label="Desde" htmlFor="f-desde"><Input id="f-desde" type="date" value={draft.desde} onChange={(e) => setDraft({ ...draft, desde: e.target.value })} /></Field>
                    <Field label="Hasta" htmlFor="f-hasta"><Input id="f-hasta" type="date" value={draft.hasta} onChange={(e) => setDraft({ ...draft, hasta: e.target.value })} /></Field>
                    <div className="flex gap-2">
                        <Button type="submit">Filtrar</Button>
                        <Button type="button" variant="ghost" onClick={() => { setDraft(empty); setFilters(empty); setPage(1); }}>Limpiar</Button>
                    </div>
                </form>

                {logs.isLoading ? <Spinner /> : logs.isError ? <div className="p-4"><Alert tone="error">{errorMessage(logs.error)}</Alert></div>
                    : logs.data!.data.length === 0 ? <EmptyState title="No hay movimientos con esos filtros." /> : (
                        <Table caption="Bitácora de movimientos">
                            <thead className="bg-slate-50"><tr>
                                <th scope="col" className={th}>#</th><th scope="col" className={th}>Fecha</th><th scope="col" className={th}>Usuario</th>
                                <th scope="col" className={th}>Acción</th><th scope="col" className={th}>Recurso</th><th scope="col" className={th}>Resultado</th>
                            </tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {logs.data!.data.map((l) => (
                                    <tr key={l.id}>
                                        <td className={`${td} tabular-nums text-slate-500`}>{l.secuencia}</td>
                                        <td className={`${td} whitespace-nowrap`}>{new Date(l.ocurrido_en).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'medium' })}</td>
                                        <td className={td}>{l.usuario ?? <span className="text-slate-500">—</span>}</td>
                                        <td className={td}><p className="font-semibold">{l.accion}</p><p className="text-xs text-slate-500">{l.modulo}</p></td>
                                        <td className={`${td} text-xs`}>{l.recurso ?? '—'}{Object.keys(l.detalle).length > 0 && (
                                            <details className="mt-1"><summary className="cursor-pointer text-nido-700">Detalle</summary>
                                                <pre className="mt-1 max-w-xs overflow-x-auto rounded bg-slate-50 p-2">{JSON.stringify(l.detalle, null, 2)}</pre></details>
                                        )}</td>
                                        <td className={td}><Badge tone={tone[l.resultado]}>{l.resultado}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                {logs.data && <Pagination meta={logs.data.meta} onPage={setPage} />}
            </Card>
        </>
    );
}
