import { lazy, Suspense, useState, type FormEvent } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Download, Printer } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { addDays, formatDate, formatTime, todayIn } from '@/lib/format';
import { cn } from '@/lib/cn';
import { Alert, Button, Card, EmptyState, Field, Input, PageHeader, Select, Spinner } from '@/components/ui';

interface Report { type: string; title: string; columns: string[]; rows: string[][]; cutoff: string; from: string; to: string; total: number }

// Módulo reportes y analytics básicos: se descarga aparte, con sus gráficas, al abrir la pestaña.
const IndicatorsPanel = lazy(() => import('./IndicatorsPanel').then((m) => ({ default: m.IndicatorsPanel })));

/** Reportes: «Exportar» (módulo reportes operativos exportables) e «Indicadores» (módulo reportes y analytics básicos). */
export function ReportsPage() {
    const { can } = useAuth();
    // El Médico solo tiene el indicador de sus citas; los reportes operativos no son para su rol.
    const operational = can('reportes.ver') || can('reportes.exportar');
    const indicators = can('reportes.ver') || can('reportes.citas_propias');
    const [tab, setTab] = useState<'exportar' | 'indicadores'>(operational ? 'exportar' : 'indicadores');
    const tabs = ([['exportar', 'Reportes operativos', operational], ['indicadores', 'Indicadores', indicators]] as const).filter(([, , allowed]) => allowed);
    return (
        <>
            <PageHeader title="Reportes" description={tab === 'exportar' ? 'Elija el reporte y las fechas, revise la vista previa y descárguelo.'
                : 'Indicadores del hospital para un período. Abra «¿De dónde sale?» para bajar del número a los registros que lo forman.'} />
            {tabs.length > 1 && (
                <div role="tablist" aria-label="Secciones" className="mb-4 inline-flex rounded-lg bg-white p-1 ring-1 ring-slate-200 print:hidden">
                    {tabs.map(([k, l]) => (
                        <button key={k} role="tab" aria-selected={tab === k} onClick={() => setTab(k)}
                            className={cn('rounded-md px-4 py-1.5 text-sm font-semibold', tab === k ? 'bg-nido-700 text-white' : 'text-slate-600 hover:bg-slate-100')}>{l}</button>
                    ))}
                </div>
            )}
            {tab === 'exportar' ? <OperationalReports /> : <Suspense fallback={<Spinner label="Cargando indicadores…" />}><IndicatorsPanel /></Suspense>}
        </>
    );
}

function OperationalReports() {
    const { can, session } = useAuth();
    const tz = session?.hospital.timezone;
    const today = todayIn(tz);
    const [form, setForm] = useState({ tipo: 'citas', desde: addDays(today, -30), hasta: addDays(today, 7) });
    const [query, setQuery] = useState<typeof form | null>(null);
    const [downloaded, setDownloaded] = useState<string | null>(null);
    const [downloadError, setDownloadError] = useState<string | null>(null);
    const types = useQuery({ queryKey: ['reportes', 'tipos'], queryFn: async () => (await api.get<{ data: { key: string; title: string }[] }>('/reportes/operativos/tipos')).data.data });
    const report = useQuery({
        queryKey: ['reportes', query],
        queryFn: async () => (await api.get<Report>('/reportes/operativos', { params: query })).data,
        enabled: query !== null, retry: false,
    });
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => { const v = e.target.value; setForm((f) => ({ ...f, [k]: v })); };
    const submit = (e: FormEvent) => { e.preventDefault(); setDownloaded(null); setQuery({ ...form }); };

    const download = async () => {
        setDownloadError(null);
        try {
            const res = await api.get('/reportes/operativos/exportar', { params: query, responseType: 'blob' });
            const url = URL.createObjectURL(res.data as Blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${query!.tipo}_${query!.desde}_${query!.hasta}.csv`;
            a.click();
            URL.revokeObjectURL(url);
            setDownloaded(String(res.headers['x-report-sha256'] ?? ''));
        } catch (e) {
            setDownloadError(errorMessage(e));
        }
    };

    const r = report.data;
    return (
        <>
            <Card className="mb-4 p-4 print:hidden">
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_11rem_11rem_auto] lg:items-end">
                    <Field label="Reporte" htmlFor="rp-type">
                        <Select id="rp-type" value={form.tipo} onChange={set('tipo')}>{types.data?.map((t) => <option key={t.key} value={t.key}>{t.title}</option>)}</Select>
                    </Field>
                    <Field label="Desde" htmlFor="rp-from"><Input id="rp-from" type="date" value={form.desde} onChange={set('desde')} /></Field>
                    <Field label="Hasta" htmlFor="rp-to"><Input id="rp-to" type="date" value={form.hasta} onChange={set('hasta')} /></Field>
                    <Button type="submit">Ver vista previa</Button>
                </form>
                <p className="mt-2 text-xs text-slate-600">Rango máximo: 92 días. Los datos llegan hasta el momento de la consulta (fecha de corte).</p>
            </Card>

            {report.isFetching && <Spinner label="Generando reporte…" />}
            {report.isError && <Alert tone="error">{errorMessage(report.error)}</Alert>}
            {r && !report.isFetching && (
                <Card>
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h2 className="text-lg font-bold text-nido-900">{r.title}</h2>
                            <p className="text-sm text-slate-600">{session?.hospital.name} · del {formatDate(r.from)} al {formatDate(r.to)} · corte {formatDate(r.cutoff, tz, { dateStyle: 'short' })} {formatTime(r.cutoff, tz)} · {r.total} fila{r.total === 1 ? '' : 's'}</p>
                        </div>
                        <div className="flex gap-2 print:hidden">
                            <Button variant="secondary" onClick={() => window.print()} disabled={r.total === 0}><Printer className="size-4" aria-hidden /> Imprimir o guardar PDF</Button>
                            {can('reportes.exportar') && <Button onClick={download} disabled={r.total === 0}><Download className="size-4" aria-hidden /> Descargar CSV (Excel)</Button>}
                        </div>
                    </div>
                    {downloaded !== null && <div className="px-5 pt-3 print:hidden"><Alert tone="success">Descargado. La exportación quedó registrada en la bitácora con su huella SHA-256 {downloaded.slice(0, 12)}…</Alert></div>}
                    {downloadError && <div className="px-5 pt-3"><Alert tone="error">{downloadError}</Alert></div>}
                    {r.total === 0 ? <EmptyState title="No hay datos para esos filtros.">Pruebe con otro rango de fechas.</EmptyState> : (
                        <div className="overflow-x-auto" tabIndex={0} role="region" aria-label={r.title}>
                            <table className="min-w-full text-sm">
                                <caption className="sr-only">{r.title}</caption>
                                <thead><tr className="bg-slate-50 text-left text-xs uppercase text-slate-500">{r.columns.map((c) => <th key={c} scope="col" className="whitespace-nowrap px-4 py-2">{c}</th>)}</tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {r.rows.map((row, i) => <tr key={i}>{row.map((cell, j) => <td key={j} className="px-4 py-2 text-slate-700">{cell}</td>)}</tr>)}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            )}
        </>
    );
}
