import { useMemo, useState, type FormEvent } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { ArrowDown, ArrowLeft, ArrowRight, ArrowUp, CheckCircle2, Clock, Info, Lock, RefreshCw, Search, TriangleAlert } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import axios from 'axios';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, ruleError } from '@/lib/api';
import { addDays, formatDate, formatTime, todayIn } from '@/lib/format';
import { cn } from '@/lib/cn';
import { formatCount, formatDifference, formatIndicator, share, type Unit } from '@/modules/analytics/format';
import { Alert, Badge, Button, Card, EmptyState, Field, Input, Select, Spinner, td, th } from '@/components/ui';

interface Dimension { clave: string; etiqueta: string }
interface Meta { clave: string; nombre: string; area: string; unidad: Unit; descripcion: string; formula: string; dimensiones: Dimension[]; solo_citas_propias: boolean; modulos: string[] }
interface Pending { nombre: string; area: string; modulo: string; descripcion: string }
interface Catalog { data: Meta[]; pendientes: Pending[]; granularidades: Record<string, string>; max_dias: number }
interface Comparison { periodo: string; valor: number | null; diferencia: number | null }
interface SummaryItem extends Meta { estado: 'exito' | 'sin_datos'; valor: number | null; numerador: number | null; denominador: number | null; comparacion: Comparison | null; solo_medico: string | null }
interface Part { parte: string; valor: number; casos: number; grupos?: number }
interface Breakdown { dimension: string; etiqueta: string; unidad_partes: string; partes: Part[]; total: number; verificado: boolean; anonimizado: boolean; agrupadas: string[] }
interface Records { parte: string; columnas: { clave: string; etiqueta: string }[]; filas: Record<string, string | number | null>[]; total: number; suma: number; valor_parte: number; pagina: number; paginas: number }
interface Point { desde: string; hasta: string; parcial: boolean; valor: number | null; numerador: number | null }
interface Result {
    estado: 'exito' | 'sin_datos';
    mensaje?: string;
    meta: Meta;
    datos: null | {
        indicador: { nombre: string; valor: number; unidad: Unit; numerador: number; denominador: number | null; etiqueta_numerador: string; etiqueta_denominador: string | null };
        trazabilidad: Record<string, string>;
        comparacion?: Comparison;
        tendencia?: { granularidad: string; puntos: Point[] };
        desglose?: Breakdown;
        registros?: Records;
    };
}
interface Period { desde: string; hasta: string }

const CHART = '#146469';

/** Indicadores (módulo reportes y analytics básicos): del número a los registros que lo forman, con los mismos filtros. */
export function IndicatorsPanel() {
    const { session } = useAuth();
    const tz = session?.hospital.timezone;
    const today = todayIn(tz);
    const [form, setForm] = useState<Period>({ desde: addDays(today, -29), hasta: today });
    const [period, setPeriod] = useState<Period>(form);
    const [formError, setFormError] = useState<string | null>(null);
    const [selected, setSelected] = useState<string | null>(null);

    const catalog = useQuery({ queryKey: ['indicadores', 'catalogo'], queryFn: async () => (await api.get<Catalog>('/reportes/indicadores')).data });
    const maxDays = catalog.data?.max_dias ?? 366;
    const doctorOnly = (catalog.data?.data ?? []).every((m) => m.solo_citas_propias) && (catalog.data?.data.length ?? 0) > 0;

    const ranges: [string, Period][] = useMemo(() => {
        const monthStart = `${today.slice(0, 8)}01`;
        const prevEnd = addDays(monthStart, -1);
        return [
            ['Últimos 7 días', { desde: addDays(today, -6), hasta: today }],
            ['Últimos 30 días', { desde: addDays(today, -29), hasta: today }],
            ['Este mes', { desde: monthStart, hasta: today }],
            ['Mes anterior', { desde: `${prevEnd.slice(0, 8)}01`, hasta: prevEnd }],
            ['Este año', { desde: `${today.slice(0, 4)}-01-01`, hasta: today }],
        ];
    }, [today]);

    // RN-02 en el momento: el botón no calcula con fechas invertidas o un período demasiado largo.
    const validate = (p: Period): string | null => {
        if (!p.desde || !p.hasta) return 'Elija las dos fechas del período.';
        if (p.desde > p.hasta) return 'La fecha inicial no puede ser mayor que la final.';
        const days = (Date.parse(`${p.hasta}T12:00:00Z`) - Date.parse(`${p.desde}T12:00:00Z`)) / 86_400_000 + 1;
        return days > maxDays ? `El período puede ser de hasta ${maxDays} días.` : null;
    };
    const apply = (p: Period) => {
        const error = validate(p);
        setFormError(error);
        if (!error) setPeriod(p);
    };
    const submit = (e: FormEvent) => { e.preventDefault(); apply(form); };
    const meta = catalog.data?.data.find((m) => m.clave === selected);

    if (catalog.isLoading) return <Spinner label="Cargando indicadores…" />;
    if (catalog.isError) return <Alert tone="error">{errorMessage(catalog.error)}</Alert>;

    return (
        <>
            <Card className="mb-4 p-4">
                <form onSubmit={submit} noValidate className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[11rem_11rem_auto] lg:items-end">
                    <Field label="Desde" htmlFor="in-from"><Input id="in-from" type="date" value={form.desde} aria-invalid={!!formError}
                        onChange={(e) => setForm((f) => ({ ...f, desde: e.target.value }))} onBlur={() => setFormError(validate(form))} /></Field>
                    <Field label="Hasta" htmlFor="in-to"><Input id="in-to" type="date" value={form.hasta} aria-invalid={!!formError}
                        onChange={(e) => setForm((f) => ({ ...f, hasta: e.target.value }))} onBlur={() => setFormError(validate(form))} /></Field>
                    <Button type="submit" disabled={!!validate(form)}><Search className="size-4" aria-hidden /> Calcular</Button>
                </form>
                {formError && <p className="mt-2 text-sm font-medium text-red-700" role="alert">{formError}</p>}
                <div className="mt-3 flex flex-wrap gap-2" aria-label="Períodos rápidos">
                    {ranges.map(([label, p]) => (
                        <button key={label} type="button" onClick={() => { setForm(p); apply(p); }}
                            className={cn('rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset',
                                p.desde === period.desde && p.hasta === period.hasta ? 'bg-nido-700 text-white ring-nido-700' : 'bg-white text-nido-800 ring-nido-200 hover:bg-nido-50')}>{label}</button>
                    ))}
                </div>
                <p className="mt-2 text-xs text-slate-600">
                    Calculando del {formatDate(period.desde)} al {formatDate(period.hasta)}. Cada cifra se compara con el período anterior del mismo largo.
                </p>
            </Card>

            {doctorOnly && (
                <div className="mb-4"><Alert><Lock className="mr-1 inline size-4" aria-hidden />Usted ve el indicador de citas, solo con sus propias citas.</Alert></div>
            )}

            {meta ? <IndicatorDetail key={meta.clave} meta={meta} period={period} granularities={catalog.data!.granularidades} onBack={() => setSelected(null)} />
                : <Overview period={period} pending={catalog.data!.pendientes} onOpen={setSelected} />}
        </>
    );
}

function Overview({ period, pending, onOpen }: { period: Period; pending: Pending[]; onOpen: (key: string) => void }) {
    const summary = useQuery({
        queryKey: ['indicadores', 'resumen', period],
        queryFn: async () => (await api.get<{ data: SummaryItem[] }>('/reportes/indicadores/resumen', { params: period })).data.data,
        placeholderData: keepPreviousData, retry: false,
    });
    if (summary.isLoading) return <Spinner label="Calculando indicadores…" />;
    if (summary.isError) return <QueryError error={summary.error} onRetry={() => summary.refetch()} />;

    const areas = [...new Set(summary.data!.map((i) => i.area))];
    return (
        <div className={cn('space-y-6', summary.isFetching && 'opacity-60')} aria-busy={summary.isFetching}>
            {areas.map((area) => (
                <section key={area} aria-labelledby={`area-${area}`}>
                    <h2 id={`area-${area}`} className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-600">{area}</h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {summary.data!.filter((i) => i.area === area).map((i) => <IndicatorCard key={i.clave} item={i} onOpen={() => onOpen(i.clave)} />)}
                    </div>
                </section>
            ))}
            {pending.length > 0 && (
                <section aria-labelledby="area-pendientes">
                    <h2 id="area-pendientes" className="mb-1 text-sm font-bold uppercase tracking-wide text-slate-600">Próximamente</h2>
                    <p className="mb-3 text-sm text-slate-600">Estos indicadores se activan cuando se integre el módulo que genera sus datos.</p>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {pending.map((p) => (
                            <Card key={p.nombre} className="border border-dashed border-slate-300 p-5 shadow-none">
                                <p className="flex items-center gap-2 font-semibold text-slate-700"><Clock className="size-4" aria-hidden /> {p.nombre}</p>
                                <p className="mt-1 text-sm text-slate-600">{p.descripcion}</p>
                                <p className="mt-2 text-xs text-slate-600">Módulo: <code className="font-mono">{p.modulo}</code></p>
                            </Card>
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}

function IndicatorCard({ item, onOpen }: { item: SummaryItem; onOpen: () => void }) {
    return (
        <Card className="flex flex-col p-5">
            <p className="text-sm font-semibold text-slate-600">{item.nombre}</p>
            <p className={cn('mt-1 text-3xl font-extrabold tabular-nums', item.estado === 'exito' ? 'text-nido-900' : 'text-slate-600')}>
                {item.estado === 'exito' ? formatIndicator(item.valor, item.unidad) : 'Sin datos'}
            </p>
            <p className="mt-1 text-xs text-slate-600">
                {item.estado === 'exito' ? <ComparisonText comparison={item.comparacion} unit={item.unidad} /> : 'No hay registros en este período.'}
            </p>
            <p className="mt-2 flex-1 text-sm text-slate-600">{item.descripcion}</p>
            <Button variant="secondary" size="sm" className="mt-4 self-start" onClick={onOpen} aria-label={`¿De dónde sale? ${item.nombre}`}>
                ¿De dónde sale? <ArrowRight className="size-4" aria-hidden />
            </Button>
        </Card>
    );
}

function ComparisonText({ comparison, unit }: { comparison?: Comparison | null; unit: Unit }) {
    if (!comparison || comparison.diferencia === null) return <>Sin datos del período anterior para comparar.</>;
    const d = comparison.diferencia;
    const Icon = d > 0 ? ArrowUp : d < 0 ? ArrowDown : null;
    return (
        <span className="inline-flex items-center gap-1">
            {Icon && <Icon className="size-3.5" aria-hidden />}
            {d === 0 ? 'Igual que' : formatDifference(d, unit)} {d === 0 ? '' : 'respecto de'} {formatIndicator(comparison.valor, unit)} del período anterior
        </span>
    );
}

function IndicatorDetail({ meta, period, granularities, onBack }: { meta: Meta; period: Period; granularities: Record<string, string>; onBack: () => void }) {
    const { session } = useAuth();
    const tz = session?.hospital.timezone;
    const [granularity, setGranularity] = useState('');
    const [dimension, setDimension] = useState(meta.dimensiones[0]?.clave ?? '');
    const [part, setPart] = useState<string | null>(null);
    const [page, setPage] = useState(1);

    const level1 = useQuery({
        queryKey: ['indicadores', meta.clave, period, granularity],
        queryFn: async () => (await api.get<Result>(`/reportes/${meta.clave}`, { params: { ...period, granularidad: granularity || undefined } })).data,
        placeholderData: keepPreviousData, retry: false,
    });
    const level2 = useQuery({
        queryKey: ['indicadores', meta.clave, 'desglose', period, dimension],
        queryFn: async () => (await api.get<Result>(`/reportes/${meta.clave}/desglose`, { params: { ...period, dimension } })).data,
        enabled: level1.data?.estado === 'exito', placeholderData: keepPreviousData, retry: false,
    });
    const level3 = useQuery({
        queryKey: ['indicadores', meta.clave, 'registros', period, dimension, part, page],
        queryFn: async () => (await api.get<Result>(`/reportes/${meta.clave}/registros`, { params: { ...period, dimension, parte: part, pagina: page } })).data,
        enabled: part !== null && level1.data?.estado === 'exito', placeholderData: keepPreviousData, retry: false,
    });

    const r = level1.data;
    const d = r?.datos;
    const unit = meta.unidad;
    return (
        <div className="space-y-4">
            <button type="button" onClick={onBack} className="inline-flex items-center gap-1 text-sm font-semibold text-nido-700 hover:underline">
                <ArrowLeft className="size-4" aria-hidden /> Todos los indicadores
            </button>
            <div>
                <p className="text-xs font-bold uppercase tracking-wide text-slate-600">{meta.area}</p>
                <h2 className="text-xl font-bold text-nido-900">{meta.nombre}</h2>
                <p className="text-sm text-slate-600">{meta.descripcion}</p>
            </div>

            {level1.isLoading && <Spinner label="Calculando…" />}
            {level1.isError && <QueryError error={level1.error} onRetry={() => level1.refetch()} />}
            {r?.estado === 'sin_datos' && (
                <Card><EmptyState title="Sin datos en este período">{r.mensaje} Cambie las fechas arriba.</EmptyState></Card>
            )}

            {d && (
                <>
                    {/* Nivel 1: valor, comparación y ficha de trazabilidad (RN-05). */}
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,20rem)_1fr]">
                        <Card className="p-5">
                            <p className="text-sm font-semibold text-slate-600">Resultado del período</p>
                            <p className="mt-1 text-4xl font-extrabold tabular-nums text-nido-900">{formatIndicator(d.indicador.valor, unit)}</p>
                            <p className="mt-1 text-sm text-slate-700">{d.trazabilidad.detalle}</p>
                            <p className="mt-2 text-xs text-slate-600"><ComparisonText comparison={d.comparacion} unit={unit} /></p>
                        </Card>
                        <Card className="p-5">
                            <h3 className="mb-2 flex items-center gap-2 font-bold text-nido-900"><Info className="size-4" aria-hidden /> Ficha de trazabilidad</h3>
                            <dl className="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[9rem_1fr]">
                                {([['formula', 'Fórmula'], ['periodo', 'Período'], ['filtros', 'Filtros'], ['fuente', 'Fuente de datos'], ['nota', 'Nota']] as const)
                                    .filter(([k]) => d.trazabilidad[k]).map(([k, label]) => (
                                        <div key={k} className="contents">
                                            <dt className="font-semibold text-slate-600">{label}</dt>
                                            <dd className={cn('text-slate-800', k === 'fuente' && 'font-mono text-xs leading-5')}>{d.trazabilidad[k]}</dd>
                                        </div>
                                    ))}
                                <dt className="font-semibold text-slate-600">Calculado</dt>
                                <dd className="text-slate-800">{formatDate(d.trazabilidad.calculado_en, tz, { dateStyle: 'medium' })} {formatTime(d.trazabilidad.calculado_en, tz)}</dd>
                            </dl>
                        </Card>
                    </div>

                    {d.tendencia && (
                        <Card className="p-5">
                            <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <h3 className="font-bold text-nido-900">Tendencia</h3>
                                    <p className="text-xs text-slate-600">{unit === 'cantidad' ? 'Cada punto es la cantidad de su período.' : 'Cada punto se calcula con su propia fórmula; los puntos no se suman.'}</p>
                                </div>
                                <div className="w-44">
                                    <Field label="Agrupar" htmlFor="in-gran">
                                        <Select id="in-gran" value={granularity} onChange={(e) => setGranularity(e.target.value)}>
                                            <option value="">Automático</option>
                                            {Object.entries(granularities).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
                                        </Select>
                                    </Field>
                                </div>
                            </div>
                            <TrendChart points={d.tendencia.puntos} unit={unit} name={meta.nombre} />
                        </Card>
                    )}

                    {/* Nivel 2: desglose por una dimensión; la suma coincide con el indicador (RN-07). */}
                    <Card>
                        <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h3 className="font-bold text-nido-900">¿De dónde sale?</h3>
                                <p className="text-xs text-slate-600">Reparte los {d.indicador.etiqueta_numerador} y abra cualquier parte para ver sus registros.</p>
                            </div>
                            <div className="w-56">
                                <Field label="Desglosar por" htmlFor="in-dim">
                                    <Select id="in-dim" value={dimension} onChange={(e) => { setDimension(e.target.value); setPart(null); setPage(1); }}>
                                        {meta.dimensiones.map((x) => <option key={x.clave} value={x.clave}>{x.etiqueta}</option>)}
                                    </Select>
                                </Field>
                            </div>
                        </div>
                        {level2.isLoading && <Spinner label="Desglosando…" />}
                        {level2.isError && <div className="p-5"><QueryError error={level2.error} onRetry={() => level2.refetch()} /></div>}
                        {level2.data?.datos?.desglose && (
                            <BreakdownView breakdown={level2.data.datos.desglose} numerator={level2.data.datos.indicador.numerador} selected={part}
                                onOpen={(p) => { setPart(p); setPage(1); }} />
                        )}
                    </Card>

                    {/* Nivel 3: registros de origen con datos mínimos (RN-09). */}
                    {part !== null && (
                        <Card>
                            <div className="border-b border-slate-200 px-5 py-4">
                                <h3 className="font-bold text-nido-900">Registros de «{part}»</h3>
                                <p className="text-xs text-slate-600">Los pacientes aparecen solo con su código, sin datos clínicos.</p>
                            </div>
                            {level3.isLoading && <Spinner label="Buscando registros…" />}
                            {level3.isError && <div className="p-5"><QueryError error={level3.error} onRetry={() => level3.refetch()} /></div>}
                            {level3.data?.datos?.registros && <RecordsView records={level3.data.datos.registros} onPage={setPage} />}
                        </Card>
                    )}
                </>
            )}
        </div>
    );
}

function TrendChart({ points, unit, name }: { points: Point[]; unit: Unit; name: string }) {
    const label = (p: Point) => (p.desde === p.hasta ? formatDate(p.desde, undefined, { day: 'numeric', month: 'short' })
        : `${formatDate(p.desde, undefined, { day: 'numeric', month: 'short' })}–${formatDate(p.hasta, undefined, { day: 'numeric', month: 'short' })}`);
    const data = points.map((p) => ({ etiqueta: label(p) + (p.parcial ? '*' : ''), valor: p.valor }));
    const anyPartial = points.some((p) => p.parcial);
    const Chart = unit === 'cantidad' ? BarChart : LineChart;
    return (
        <>
            <div className="h-64" role="img" aria-label={`Tendencia de ${name}. Los valores están en la tabla de abajo.`}>
                <ResponsiveContainer width="100%" height="100%">
                    <Chart data={data} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                        <XAxis dataKey="etiqueta" tick={{ fontSize: 11, fill: '#475569' }} interval="preserveStartEnd" />
                        <YAxis tick={{ fontSize: 11, fill: '#475569' }} width={48} allowDecimals={unit !== 'cantidad'} domain={unit === '%' ? [0, 100] : [0, 'auto']} />
                        <Tooltip formatter={(v) => [formatIndicator(v as number | null, unit), name]} />
                        {unit === 'cantidad'
                            ? <Bar dataKey="valor" fill={CHART} radius={[4, 4, 0, 0]} isAnimationActive={false} />
                            : <Line dataKey="valor" stroke={CHART} strokeWidth={2} dot={{ r: 3 }} connectNulls={false} isAnimationActive={false} />}
                    </Chart>
                </ResponsiveContainer>
            </div>
            {anyPartial && <p className="mt-1 text-xs text-slate-600">* Período parcial: no tiene todos sus días dentro del rango elegido.</p>}
            <details className="mt-2 text-sm">
                <summary className="cursor-pointer font-semibold text-nido-700">Ver los valores en una tabla</summary>
                <div className="mt-2 max-h-64 overflow-y-auto" tabIndex={0} role="region" aria-label="Valores de la tendencia">
                    <table className="min-w-full text-sm">
                        <thead><tr><th scope="col" className={th}>Período</th><th scope="col" className={cn(th, 'text-right')}>Valor</th></tr></thead>
                        <tbody className="divide-y divide-slate-100">
                            {points.map((p, i) => (
                                <tr key={p.desde}><td className={td}>{data[i].etiqueta}</td><td className={cn(td, 'text-right tabular-nums')}>{formatIndicator(p.valor, unit)}</td></tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </>
    );
}

function BreakdownView({ breakdown: b, numerator, selected, onOpen }: { breakdown: Breakdown; numerator: number; selected: string | null; onOpen: (part: string) => void }) {
    if (b.partes.length === 0) return <EmptyState title={`No hay ${b.unidad_partes} que repartir en este período.`}>El indicador vale cero y no tiene registros que abrir.</EmptyState>;
    const height = Math.max(120, b.partes.length * 36);
    return (
        <div className="grid gap-4 p-5 xl:grid-cols-2">
            <div style={{ height }} role="img" aria-label={`Desglose por ${b.etiqueta.toLowerCase()}. Los valores están en la tabla.`}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={b.partes} layout="vertical" margin={{ top: 0, right: 16, bottom: 0, left: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" horizontal={false} />
                        <XAxis type="number" tick={{ fontSize: 11, fill: '#475569' }} allowDecimals={false} />
                        <YAxis type="category" dataKey="parte" width={150} tick={{ fontSize: 11, fill: '#334155' }}
                            tickFormatter={(v: string) => (v.length > 22 ? `${v.slice(0, 21)}…` : v)} />
                        <Tooltip formatter={(v) => [formatCount(v as number), b.unidad_partes]} />
                        <Bar dataKey="valor" fill={CHART} radius={[0, 4, 4, 0]} isAnimationActive={false} />
                    </BarChart>
                </ResponsiveContainer>
            </div>
            <div>
                <div className="overflow-x-auto" tabIndex={0} role="region" aria-label={`Desglose por ${b.etiqueta}`}>
                    <table className="min-w-full text-sm">
                        <caption className="sr-only">Desglose por {b.etiqueta}</caption>
                        <thead>
                            <tr className="border-b border-slate-200">
                                <th scope="col" className={th}>{b.etiqueta}</th>
                                <th scope="col" className={cn(th, 'text-right')}>{b.unidad_partes}</th>
                                <th scope="col" className={cn(th, 'text-right')}>% del total</th>
                                <th scope="col" className={th}><span className="sr-only">Acción</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {b.partes.map((p) => (
                                <tr key={p.parte} className={cn(selected === p.parte && 'bg-nido-50')}>
                                    <td className={td}>{p.parte}{p.grupos ? <span className="block text-xs text-slate-600">{p.grupos} grupos con menos de 3 casos</span> : null}</td>
                                    <td className={cn(td, 'text-right tabular-nums')}>{formatCount(p.valor)}</td>
                                    <td className={cn(td, 'text-right tabular-nums')}>{share(p.valor, b.total)}</td>
                                    <td className={cn(td, 'text-right')}>
                                        <Button size="sm" variant="ghost" onClick={() => onOpen(p.parte)} aria-label={`Ver registros de ${p.parte}`}>Ver registros</Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t-2 border-slate-300 font-bold">
                                <td className={td}>Total</td>
                                <td className={cn(td, 'text-right tabular-nums')}>{formatCount(b.total)}</td>
                                <td className={td} colSpan={2}>
                                    {b.total === numerator
                                        ? <span className="inline-flex items-center gap-1 text-xs font-semibold text-green-800"><CheckCircle2 className="size-4" aria-hidden /> Coincide con el indicador</span>
                                        : <span className="inline-flex items-center gap-1 text-xs font-semibold text-red-700"><TriangleAlert className="size-4" aria-hidden /> Revisar fuente</span>}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                {b.anonimizado && <p className="mt-2 text-xs text-slate-600">Para proteger la privacidad, los grupos con menos de 3 casos se muestran juntos como «Otros».</p>}
            </div>
        </div>
    );
}

function RecordsView({ records: r, onPage }: { records: Records; onPage: (page: number) => void }) {
    return (
        <>
            <p className="px-5 pt-3 text-sm text-slate-700">
                {formatCount(r.total)} registro{r.total === 1 ? '' : 's'}
                {r.suma !== r.total && <> · suman {formatCount(r.suma)}</>}
                {r.suma === r.valor_parte && <Badge tone="ok">Coincide con el desglose</Badge>}
            </p>
            <div className="overflow-x-auto" tabIndex={0} role="region" aria-label={`Registros de ${r.parte}`}>
                <table className="min-w-full text-sm">
                    <caption className="sr-only">Registros de {r.parte}</caption>
                    <thead><tr className="border-b border-slate-200">{r.columnas.map((c) => <th key={c.clave} scope="col" className={cn(th, 'whitespace-nowrap')}>{c.etiqueta}</th>)}</tr></thead>
                    <tbody className="divide-y divide-slate-100">
                        {r.filas.map((row, i) => (
                            <tr key={i}>{r.columnas.map((c) => <td key={c.clave} className={cn(td, 'whitespace-nowrap', typeof row[c.clave] === 'number' && 'text-right tabular-nums')}>{row[c.clave] ?? '—'}</td>)}</tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {r.paginas > 1 && (
                <nav className="flex items-center justify-between gap-2 border-t border-slate-200 px-5 py-3" aria-label="Páginas de registros">
                    <Button size="sm" variant="secondary" disabled={r.pagina <= 1} onClick={() => onPage(r.pagina - 1)}>Anterior</Button>
                    <span className="text-sm text-slate-700">Página {r.pagina} de {r.paginas}</span>
                    <Button size="sm" variant="secondary" disabled={r.pagina >= r.paginas} onClick={() => onPage(r.pagina + 1)}>Siguiente</Button>
                </nav>
            )}
        </>
    );
}

/** 503 ofrece reintentar con los mismos filtros y muestra el código de seguimiento; 403 no se reintenta. */
function QueryError({ error, onRetry }: { error: unknown; onRetry: () => void }) {
    const rule = ruleError(error);
    const status = axios.isAxiosError(error) ? error.response?.status : undefined;
    const retryable = status === undefined || status >= 500;
    return (
        <Alert tone="error">
            {errorMessage(error)}
            {rule?.details.correlationId ? <span className="block text-xs">Código de seguimiento: {String(rule.details.correlationId)}</span> : null}
            {retryable && <Button size="sm" variant="secondary" className="mt-2" onClick={onRetry}><RefreshCw className="size-4" aria-hidden /> Reintentar</Button>}
        </Alert>
    );
}
