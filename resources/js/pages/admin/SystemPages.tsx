import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { CheckCircle2, Download, Smartphone, XCircle } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { Alert, Badge, Button, Card, Input, PageHeader, Spinner } from '@/components/ui';

interface OpenApi { info: { title: string; version: string; description: string }; paths: Record<string, Record<string, { tags: string[]; summary: string; 'x-permisos': string[] }>> }

/** Módulo contratos API: el contrato OpenAPI se genera de las rutas reales y cada rol ve lo que puede usar. */
export function ApiDocsPage() {
    const { can } = useAuth();
    const [full, setFull] = useState(false);
    const [filter, setFilter] = useState('');
    const spec = useQuery({ queryKey: ['openapi', full], queryFn: async () => (await api.get<OpenApi>('/sistema/openapi', { params: { completo: full ? 1 : undefined } })).data });
    const ops = useMemo(() => Object.entries(spec.data?.paths ?? {}).flatMap(([path, methods]) => Object.entries(methods).map(([m, op]) => ({ path, method: m.toUpperCase(), ...op })))
        .filter((o) => !filter || `${o.path} ${o['x-permisos'].join(' ')}`.toLowerCase().includes(filter.toLowerCase())), [spec.data, filter]);

    const download = () => {
        const blob = new Blob([JSON.stringify(spec.data, null, 2)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'hospital-nido-openapi.json';
        a.click();
    };
    const tone: Record<string, 'ok' | 'nido' | 'warn' | 'critical' | 'neutral'> = { GET: 'ok', POST: 'nido', PUT: 'warn', PATCH: 'warn', DELETE: 'critical' };

    return (
        <>
            <PageHeader title="Contratos API" description="Contrato OpenAPI 3 generado de las rutas reales: nunca queda desactualizado. Se muestran las operaciones que su rol puede usar."
                actions={spec.data && <Button variant="secondary" onClick={download}><Download className="size-4" aria-hidden /> Descargar openapi.json</Button>} />
            <Card className="mb-4 flex flex-wrap items-center gap-3 p-4">
                <label htmlFor="api-filter" className="sr-only">Filtrar</label>
                <Input id="api-filter" className="max-w-sm" placeholder="Filtrar por ruta o permiso (p. ej. citas)" value={filter} onChange={(e) => setFilter(e.target.value)} />
                {can('contratos_api.ver') && <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="accent-nido-700" checked={full} onChange={(e) => setFull(e.target.checked)} /> Ver el contrato completo (todos los roles)</label>}
                <span className="text-sm text-slate-600">{ops.length} operaciones</span>
            </Card>
            {spec.isLoading ? <Spinner /> : spec.isError ? <Alert tone="error">{errorMessage(spec.error)}</Alert> : (
                <Card>
                    <p className="border-b border-slate-200 px-5 py-3 text-sm text-slate-600">{spec.data!.info.description} Autenticación: <code>Authorization: Bearer &lt;token&gt;</code>.</p>
                    <ul className="divide-y divide-slate-100">
                        {ops.map((o) => (
                            <li key={o.method + o.path} className="flex flex-wrap items-center gap-3 px-5 py-2.5 text-sm">
                                <span className="w-16"><Badge tone={tone[o.method] ?? 'neutral'}>{o.method}</Badge></span>
                                <code className="min-w-0 flex-1 break-all text-slate-900">/api/v1{o.path}</code>
                                <span className="text-xs text-slate-600">{o['x-permisos'].length ? o['x-permisos'].join(' o ') : 'sesión'}</span>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </>
    );
}

interface DbStatus { hospital: { code: string; database: string }; checks: { label: string; ok: boolean; detail: string }[]; tables: { table: string; label: string; rows: number }[] }

/** Módulo verificación de base de datos PostgreSQL: chequeos en vivo de CENTRAL y de la base del hospital. */
export function DatabasePage() {
    const status = useQuery({ queryKey: ['sistema', 'bd'], queryFn: async () => (await api.get<DbStatus>('/sistema/base-datos')).data });
    if (status.isLoading) return <Spinner label="Verificando las bases de datos…" />;
    if (status.isError) return <Alert tone="error">{errorMessage(status.error)}</Alert>;
    const s = status.data!;
    const allOk = s.checks.every((c) => c.ok);
    return (
        <>
            <PageHeader title="Base de datos" description={`Hospital ${s.hospital.code}: base clínica «${s.hospital.database}» y base central de la red.`}
                actions={<Button variant="secondary" onClick={() => status.refetch()} loading={status.isFetching}>Verificar otra vez</Button>} />
            <div className="mb-4">{allOk ? <Alert tone="success">Todo en orden: {s.checks.length} verificaciones correctas.</Alert> : <Alert tone="error">Hay verificaciones con problemas. Revise el detalle.</Alert>}</div>
            <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <Card>
                    <ul className="divide-y divide-slate-100">
                        {s.checks.map((c) => (
                            <li key={c.label} className="flex items-start gap-3 px-5 py-3 text-sm">
                                {c.ok ? <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-green-700" aria-label="Correcto" /> : <XCircle className="mt-0.5 size-5 shrink-0 text-red-700" aria-label="Con problemas" />}
                                <span><strong className="text-slate-900">{c.label}</strong><span className="block text-slate-600">{c.detail}</span></span>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card>
                    <h2 className="border-b border-slate-200 px-5 py-3 font-bold text-nido-900">Registros en este hospital</h2>
                    <ul className="divide-y divide-slate-100">{s.tables.map((t) => <li key={t.table} className="flex justify-between px-5 py-2 text-sm"><span>{t.label}</span><span className="font-semibold tabular-nums">{t.rows}</span></li>)}</ul>
                </Card>
            </div>
        </>
    );
}

/** Módulo investigación y prototipo NativePHP: extensión móvil. Se entrega como aplicación instalable (PWA). */
export function MobilePage() {
    const installed = typeof window !== 'undefined' && window.matchMedia?.('(display-mode: standalone)').matches;
    return (
        <>
            <PageHeader title="App móvil" description="Hospital Nido se puede instalar en el celular o en la computadora y abrir como una aplicación." />
            <div className="grid gap-6 lg:grid-cols-2">
                <Card className="p-6">
                    <Smartphone className="size-10 text-nido-700" aria-hidden />
                    <h2 className="mt-3 text-lg font-bold text-nido-900">{installed ? 'Ya está usando la aplicación instalada' : 'Cómo instalarla'}</h2>
                    <ol className="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-700">
                        <li><strong>Android (Chrome):</strong> menú ⋮ → «Instalar aplicación» o «Agregar a la pantalla principal».</li>
                        <li><strong>iPhone (Safari):</strong> botón Compartir → «Agregar a inicio».</li>
                        <li><strong>Computadora (Chrome o Edge):</strong> icono de instalar en la barra de direcciones.</li>
                    </ol>
                    <p className="mt-3 text-sm text-slate-600">Se abre a pantalla completa, con el ícono de Hospital Nido, y usa la misma cuenta y permisos.</p>
                </Card>
                <Card className="p-6 text-sm text-slate-700">
                    <h2 className="text-lg font-bold text-nido-900">Decisión de la investigación</h2>
                    <p className="mt-2">El módulo evaluó <strong>NativePHP</strong> para empaquetar el sistema como aplicación de escritorio o móvil con un prototipo acotado.</p>
                    <ul className="mt-3 list-disc space-y-1 pl-5">
                        <li>Para esta versión se entrega como <strong>aplicación web instalable (PWA)</strong>: funciona en cualquier teléfono y computadora, sin tiendas ni compilación.</li>
                        <li>El sistema ya separa API y pantallas, así que una app NativePHP puede reutilizar la misma API cuando se necesite acceso nativo (cámara, notificaciones, modo sin conexión).</li>
                        <li>El análisis completo está en <code>docs/modulos/investigacion-prototipo-nativephp</code>.</li>
                    </ul>
                </Card>
            </div>
        </>
    );
}

