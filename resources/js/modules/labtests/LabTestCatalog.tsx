import { useState, type FormEvent } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Plus, Search } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { formatDate, todayIn } from '@/lib/format';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, Select, Spinner } from '@/components/ui';
import { LabTestFormDialog } from './LabTestFormDialog';
import { hours, stateStyle, type CatalogLabTest, type LabCatalogOptions } from './types';

/** Cambio de la semana 12: el aviso lleva texto, no solo color. El servidor decide si está vigente. */
function ExpiringNotice({ test }: { test: CatalogLabTest }) {
    if (!test.expiring || !test.valid_to) return null;
    const days = test.expires_in_days === 0 ? 'hoy es su último día' : test.expires_in_days === 1 ? 'en 1 día' : `en ${test.expires_in_days} días`;
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
            <AlertTriangle className="size-3.5" aria-hidden /> Vence el {formatDate(test.valid_to, undefined, { dateStyle: 'medium' })} ({days})
        </span>
    );
}

/**
 * Módulo catálogo de pruebas de laboratorio (CU-04 y CU-05): las pruebas vigentes en una fecha con sus requisitos de
 * muestra y, para el jefe de laboratorio, el alta y las versiones nuevas. Reemplaza la pestaña «Pruebas disponibles».
 */
export function LabTestCatalog() {
    const { can, session } = useAuth();
    const today = todayIn(session?.hospital.timezone);
    const manage = can('pruebas_lab.gestionar');
    const queryClient = useQueryClient();
    const [draft, setDraft] = useState({ q: '', seccion: '', muestra: '', fecha: today });
    const [filters, setFilters] = useState(draft);
    const [open, setOpen] = useState<string | null>(null);
    const [adding, setAdding] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    const options = useQuery({ queryKey: ['catalogo-lab', 'opciones'], queryFn: async () => (await api.get<{ data: LabCatalogOptions }>('/catalogo/pruebas-laboratorio/opciones')).data.data, staleTime: Infinity });
    const list = useQuery({
        queryKey: ['catalogo-lab', 'lista', filters],
        queryFn: async () => (await api.get<{ data: CatalogLabTest[]; meta: { total: number; fecha: string; limite: number } }>('/catalogo/pruebas-laboratorio', {
            params: { q: filters.q.trim() || undefined, seccion: filters.seccion || undefined, muestra: filters.muestra || undefined, fecha: filters.fecha || undefined },
        })).data,
    });
    const errors = fieldErrors(list.error);
    const changed = (message: string) => {
        setNotice(message);
        // Órdenes lee la misma lista al pedir pruebas desde el expediente.
        queryClient.invalidateQueries({ queryKey: ['catalogo-lab'] });
        queryClient.invalidateQueries({ queryKey: ['lab', 'pruebas'] });
    };

    return (
        <div className="space-y-4">
            <Card className="p-5">
                <form className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_11rem_10rem_10rem_auto] lg:items-end" role="search" aria-label="Buscar en el catálogo de pruebas"
                    onSubmit={(e: FormEvent) => { e.preventDefault(); setNotice(null); setFilters(draft); }}>
                    <Field label="Buscar" htmlFor="lt-q" error={errors.q} hint="Nombre, código o sección.">
                        <Input id="lt-q" value={draft.q} maxLength={60} onChange={(e) => setDraft({ ...draft, q: e.target.value })} aria-invalid={!!errors.q} />
                    </Field>
                    <Field label="Sección" htmlFor="lt-s" error={errors.seccion}>
                        <Select id="lt-s" value={draft.seccion} onChange={(e) => setDraft({ ...draft, seccion: e.target.value })}>
                            <option value="">Todas</option>
                            {options.data?.sections.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Muestra" htmlFor="lt-m" error={errors.muestra}>
                        <Select id="lt-m" value={draft.muestra} onChange={(e) => setDraft({ ...draft, muestra: e.target.value })}>
                            <option value="">Todas</option>
                            {options.data?.specimens.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Vigentes el" htmlFor="lt-d" error={errors.fecha}>
                        <Input id="lt-d" type="date" value={draft.fecha} min="2000-01-01" max="2099-12-31" onChange={(e) => setDraft({ ...draft, fecha: e.target.value })} />
                    </Field>
                    <Button type="submit"><Search className="size-4" aria-hidden /> Buscar</Button>
                </form>
            </Card>

            {notice && <Alert tone="success">{notice}</Alert>}

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                    <p className="text-sm text-slate-600" aria-live="polite">
                        {list.data ? <>
                            {list.data.meta.total === 1 ? '1 prueba vigente' : `${list.data.meta.total} pruebas vigentes`} el {formatDate(list.data.meta.fecha)}
                            {list.data.meta.total > list.data.meta.limite && ` · se muestran las primeras ${list.data.meta.limite}`}
                        </> : 'Catálogo de pruebas del hospital'}
                    </p>
                    {manage && options.data && <Button size="sm" onClick={() => { setNotice(null); setAdding(true); }}><Plus className="size-4" aria-hidden /> Registrar prueba</Button>}
                </div>
                {list.isLoading ? <Spinner /> : list.isError ? (
                    <div className="p-5"><Alert tone="error">{Object.keys(errors).length ? 'Revise los filtros de la búsqueda.' : errorMessage(list.error)}</Alert></div>
                ) : list.data!.data.length === 0 ? (
                    <EmptyState title={`No hay pruebas vigentes el ${formatDate(filters.fecha || today)} con esos filtros.`}>
                        {manage ? 'Si falta una prueba, regístrela.' : 'Pruebe con otro texto u otra fecha.'}
                    </EmptyState>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {list.data!.data.map((t) => (
                            <li key={t.id}>
                                <button type="button" onClick={() => { setNotice(null); setOpen(t.code); }}
                                    className="flex w-full flex-wrap items-start justify-between gap-2 px-5 py-3 text-left text-sm hover:bg-slate-50 focus-visible:bg-slate-50">
                                    <span>
                                        <span className="font-semibold text-slate-900">{t.name}</span>{' '}
                                        <span className="font-mono text-xs text-slate-500">{t.code}{t.version > 1 ? ` · v${t.version}` : ''}</span>
                                        <span className="block text-slate-600">
                                            {t.specimen_label} · {t.container} · {t.min_volume_ml} mL{t.fasting_hours > 0 ? ` · ayuno de ${hours(t.fasting_hours)}` : ' · sin ayuno'}
                                        </span>
                                    </span>
                                    <span className="flex flex-wrap items-center gap-2">
                                        <Badge tone="neutral">{t.section_label}</Badge>
                                        <ExpiringNotice test={t} />
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {open && options.data && <LabTestDetail code={open} date={filters.fecha || today} options={options.data} onClose={() => setOpen(null)} onChanged={changed} />}
            {adding && options.data && <LabTestFormDialog options={options.data} onClose={() => setAdding(false)}
                onDone={(t) => { setAdding(false); changed(`${t.name} quedó registrada como ${t.code}, vigente desde el ${formatDate(t.valid_from)}.`); }} />}
        </div>
    );
}

/** CU-05: requisitos de la versión vigente en la fecha consultada, y el historial de versiones (RN-08). */
function LabTestDetail({ code, date, options, onClose, onChanged }: { code: string; date: string; options: LabCatalogOptions; onClose: () => void; onChanged: (m: string) => void }) {
    const { can } = useAuth();
    const [versioning, setVersioning] = useState(false);
    const history = useQuery({
        queryKey: ['catalogo-lab', 'versiones', code, date],
        queryFn: async () => (await api.get<{ data: CatalogLabTest[] }>(`/catalogo/pruebas-laboratorio/${code}/versiones`, { params: { fecha: date } })).data.data,
    });
    const versions = history.data ?? [];
    const shown = versions.find((v) => v.valid_from <= date && (v.valid_to === null || v.valid_to >= date)) ?? versions[versions.length - 1];
    const latest = versions[versions.length - 1];
    const next = shown && versions.find((v) => v.version === shown.version + 1);
    const range = (low: number | null, high: number | null) => (low === null && high === null ? 'Sin definir' : `${low ?? '—'} a ${high ?? '—'} ${shown?.unit}`);

    // El formulario de la versión nueva va fuera de la ficha: si estuviera dentro, al cerrarse cerraría también la ficha.
    return (
        <>
            <Dialog open onClose={onClose} title={shown ? `${shown.name} · ${shown.code}` : code}>
                {history.isLoading ? <Spinner /> : history.isError ? <Alert tone="error">{errorMessage(history.error)}</Alert> : (
                    <div className="space-y-4 text-sm">
                        <div className="flex flex-wrap gap-2">
                            <Badge tone={stateStyle[shown!.state].tone}>{stateStyle[shown!.state].label}</Badge>
                            <Badge tone="neutral">Versión {shown!.version} de {latest!.version}</Badge>
                            <ExpiringNotice test={shown!} />
                        </div>
                        {next && shown!.valid_to && (
                            <p className="text-slate-600">Desde el {formatDate(next.valid_from)} rige la versión {next.version}.</p>
                        )}
                        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            {([
                                ['Sección', shown!.section_label],
                                ['Tiempo de respuesta', hours(shown!.turnaround_hours)],
                                ['Muestra', `${shown!.specimen_label} · ${shown!.container}`],
                                ['Volumen mínimo', `${shown!.min_volume_ml} mL`],
                                ['Ayuno', shown!.fasting_hours > 0 ? hours(shown!.fasting_hours) : 'No requiere'],
                                ['Conservación', `${shown!.storage_label} · estable ${hours(shown!.stability_hours)}`],
                                ['Referencia', range(shown!.ref_low, shown!.ref_high)],
                                ['Valores críticos', range(shown!.critical_low, shown!.critical_high)],
                                ['Vigente', `Desde el ${formatDate(shown!.valid_from)}${shown!.valid_to ? ` hasta el ${formatDate(shown!.valid_to)}, incluido` : ', sin fecha de cierre'}`],
                            ] as const).map(([label, value]) => (
                                <div key={label}><dt className="font-semibold text-slate-500">{label}</dt><dd className="mt-0.5 text-slate-900">{value}</dd></div>
                            ))}
                        </dl>
                        {shown!.preparation_instructions && <Alert tone="info">Preparación del paciente: {shown!.preparation_instructions}</Alert>}

                        {can('pruebas_lab.gestionar') && (
                            <div className="border-t border-slate-200 pt-4">
                                <Button size="sm" variant="secondary" onClick={() => setVersioning(true)}>Nueva versión</Button>
                                <p className="mt-1 text-xs text-slate-500">Una versión no se edita: para cambiar un dato o cerrar la prueba, registre una versión nueva.</p>
                            </div>
                        )}

                        <section aria-labelledby="lt-history" className="border-t border-slate-200 pt-4">
                            <h3 id="lt-history" className="font-bold text-nido-900">Historial de versiones</h3>
                            <ol className="mt-2 space-y-2">
                                {versions.map((v) => (
                                    <li key={v.id} className="rounded-lg bg-slate-50 px-3 py-2">
                                        <p className="flex flex-wrap items-center gap-2 font-semibold text-slate-900">Versión {v.version} <Badge tone={stateStyle[v.state].tone}>{stateStyle[v.state].label}</Badge></p>
                                        <p className="text-slate-600">{v.specimen_label} · {v.min_volume_ml} mL · desde el {formatDate(v.valid_from, undefined, { dateStyle: 'medium' })}
                                            {v.valid_to ? ` hasta el ${formatDate(v.valid_to, undefined, { dateStyle: 'medium' })}` : ''}</p>
                                        <p className="text-xs text-slate-500">Registró: {v.created_by}{v.closed_by ? ` · cerró: ${v.closed_by}` : ''}</p>
                                    </li>
                                ))}
                            </ol>
                        </section>
                    </div>
                )}
            </Dialog>
            {versioning && latest && <LabTestFormDialog base={latest} options={options} onClose={() => setVersioning(false)}
                onDone={(t) => { setVersioning(false); onChanged(`${t.code} tiene la versión ${t.version}, vigente desde el ${formatDate(t.valid_from)}.`); }} />}
        </>
    );
}
