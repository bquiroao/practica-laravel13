import { useState, type FormEvent } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Search } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors } from '@/lib/api';
import { formatDate, todayIn } from '@/lib/format';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, Select, Spinner } from '@/components/ui';
import { MedicationDeleteDialog, MedicationFormDialog, MedicationRetireDialog } from './MedicationDialogs';
import { stateStyle, type CatalogMedication, type CatalogOptions, type MedicationDetailMeta } from './types';

/**
 * Módulo catálogo de medicamentos (CU-02): buscar lo vigente en una fecha y, para farmacia, registrar, versionar, dar de
 * baja o eliminar un registro erróneo. Reemplaza la pestaña «Catálogo de medicamentos» provisional de prescripciones.
 */
export function MedicationCatalog() {
    const { can, session } = useAuth();
    const today = todayIn(session?.hospital.timezone);
    const manage = can('medicamentos.gestionar');
    const queryClient = useQueryClient();
    const [draft, setDraft] = useState({ q: '', forma: '', fecha: today });
    const [filters, setFilters] = useState(draft);
    const [open, setOpen] = useState<string | null>(null);
    const [adding, setAdding] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    const options = useQuery({ queryKey: ['catalogo-medicamentos', 'opciones'], queryFn: async () => (await api.get<{ data: CatalogOptions }>('/catalogo/medicamentos/opciones')).data.data, staleTime: Infinity });
    const list = useQuery({
        queryKey: ['catalogo-medicamentos', 'lista', filters],
        queryFn: async () => (await api.get<{ data: CatalogMedication[]; meta: { total: number; fecha: string; limite: number } }>('/catalogo/medicamentos', {
            params: { q: filters.q.trim() || undefined, forma: filters.forma || undefined, fecha: filters.fecha || undefined },
        })).data,
    });
    const errors = fieldErrors(list.error);
    const changed = (message: string) => {
        setNotice(message);
        // Prescripciones lee la misma lista: un alta o una baja se nota al recetar.
        queryClient.invalidateQueries({ queryKey: ['catalogo-medicamentos'] });
        queryClient.invalidateQueries({ queryKey: ['medicamentos'] });
    };
    const submit = (e: FormEvent) => { e.preventDefault(); setNotice(null); setFilters(draft); };

    return (
        <div className="space-y-4">
            <Card className="p-5">
                <form className="grid gap-4 sm:grid-cols-[1fr_12rem_10rem_auto] sm:items-end" onSubmit={submit} role="search" aria-label="Buscar en el catálogo">
                    <Field label="Buscar" htmlFor="med-q" error={errors.q} hint="Nombre, principio activo o código (MED-000001 o el anterior, AMOX500).">
                        <Input id="med-q" value={draft.q} maxLength={60} onChange={(e) => setDraft({ ...draft, q: e.target.value })} aria-invalid={!!errors.q} />
                    </Field>
                    <Field label="Forma farmacéutica" htmlFor="med-f" error={errors.forma}>
                        <Select id="med-f" value={draft.forma} onChange={(e) => setDraft({ ...draft, forma: e.target.value })}>
                            <option value="">Todas</option>
                            {options.data?.forms.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </Select>
                    </Field>
                    <Field label="Vigentes el" htmlFor="med-d" error={errors.fecha}>
                        <Input id="med-d" type="date" value={draft.fecha} min="2000-01-01" max="2099-12-31" onChange={(e) => setDraft({ ...draft, fecha: e.target.value })} />
                    </Field>
                    <Button type="submit"><Search className="size-4" aria-hidden /> Buscar</Button>
                </form>
            </Card>

            {notice && <Alert tone="success">{notice}</Alert>}

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                    <p className="text-sm text-slate-600" aria-live="polite">
                        {list.data ? <>
                            {list.data.meta.total === 1 ? '1 medicamento vigente' : `${list.data.meta.total} medicamentos vigentes`} el {formatDate(list.data.meta.fecha)}
                            {list.data.meta.total > list.data.meta.limite && ` · se muestran los primeros ${list.data.meta.limite}; afine la búsqueda`}
                        </> : 'Catálogo de medicamentos del hospital'}
                    </p>
                    {manage && options.data && <Button size="sm" onClick={() => { setNotice(null); setAdding(true); }}><Plus className="size-4" aria-hidden /> Registrar medicamento</Button>}
                </div>
                {list.isLoading ? <Spinner /> : list.isError ? (
                    <div className="p-5"><Alert tone="error">{Object.keys(errors).length ? 'Revise los filtros de la búsqueda.' : errorMessage(list.error)}</Alert></div>
                ) : list.data!.data.length === 0 ? (
                    <EmptyState title={filters.q ? `No hay medicamentos vigentes con «${filters.q}» el ${formatDate(filters.fecha || today)}.` : 'No hay medicamentos vigentes en esa fecha.'}>
                        {manage ? 'Si no existe, puede registrarlo.' : 'Pruebe con el principio activo o con otra fecha.'}
                    </EmptyState>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {list.data!.data.map((m) => (
                            <li key={m.id}>
                                <button type="button" onClick={() => { setNotice(null); setOpen(m.code); }}
                                    className="flex w-full flex-wrap items-start justify-between gap-2 px-5 py-3 text-left text-sm hover:bg-slate-50 focus-visible:bg-slate-50">
                                    <span>
                                        <span className="font-semibold text-slate-900">{m.name}</span>{' '}
                                        <span className="font-mono text-xs text-slate-500">{m.code}{m.version > 1 ? ` · v${m.version}` : ''}</span>
                                        <span className="block text-slate-600">{m.active_ingredient} {m.strength} · {m.dosage_form_label} · vía {m.route_label.toLowerCase()} · {m.presentation}</span>
                                    </span>
                                    <span className="flex flex-wrap items-center gap-2">
                                        <Badge tone="neutral">{m.therapeutic_group_label}</Badge>
                                        {m.valid_to && <Badge tone="warn">Hasta el {formatDate(m.valid_to, undefined, { dateStyle: 'medium' })}</Badge>}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {open && options.data && <MedicationDetail code={open} options={options.data} onClose={() => setOpen(null)} onOpenCode={setOpen}
                onChanged={(message, close) => { changed(message); if (close) setOpen(null); }} />}
            {adding && options.data && <MedicationFormDialog options={options.data} onClose={() => setAdding(false)} onOpenCode={(c) => { setAdding(false); setOpen(c); }}
                onDone={(m) => { setAdding(false); changed(`${m.name} quedó registrado como ${m.code}.`); }} />}
        </div>
    );
}

/** La ficha vigente, su estado y el historial de versiones (CU-05), con las acciones de farmacia. */
function MedicationDetail({ code, options, onClose, onOpenCode, onChanged }: {
    code: string; options: CatalogOptions; onClose: () => void; onOpenCode: (code: string) => void; onChanged: (message: string, close: boolean) => void;
}) {
    const { can } = useAuth();
    const manage = can('medicamentos.gestionar');
    const [action, setAction] = useState<'version' | 'retire' | 'delete' | null>(null);
    const detail = useQuery({
        queryKey: ['catalogo-medicamentos', 'ficha', code],
        queryFn: async () => (await api.get<{ data: CatalogMedication; meta: MedicationDetailMeta }>(`/catalogo/medicamentos/${code}`)).data,
    });
    const history = useQuery({
        queryKey: ['catalogo-medicamentos', 'versiones', code],
        queryFn: async () => (await api.get<{ data: CatalogMedication[] }>(`/catalogo/medicamentos/${code}/versiones`)).data.data,
    });
    const families = (groups: string[]) => groups.map((g) => options.allergy_groups.find((o) => o.value === g)?.label ?? g).join(', ') || 'Ninguna';
    const m = detail.data?.data;
    const meta = detail.data?.meta;
    const latest = history.data?.[history.data.length - 1];

    // Los diálogos de cada acción van fuera de la ficha: si estuvieran dentro, al cerrarse cerrarían también la ficha.
    return (
        <>
            <Dialog open onClose={onClose} title={m ? `${m.name} · ${m.code}` : code}>
                {detail.isLoading ? <Spinner /> : detail.isError ? <Alert tone="error">{errorMessage(detail.error)}</Alert> : (
                    <div className="space-y-4 text-sm">
                        <div className="flex flex-wrap gap-2">
                            <Badge tone={stateStyle[m!.state].tone}>{stateStyle[m!.state].label}</Badge>
                            <Badge tone="neutral">Versión {m!.version} de {meta!.ultima_version}</Badge>
                            {m!.legacy_code && <Badge tone="neutral">Código anterior {m!.legacy_code}</Badge>}
                        </div>
                        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            {([
                                ['Principio activo', `${m!.active_ingredient} ${m!.strength}`],
                                ['Forma y vía', `${m!.dosage_form_label} · ${m!.route_label}`],
                                ['Presentación', m!.presentation],
                                ['Registro sanitario', m!.sanitary_registration ?? 'Sin registro'],
                                ['Grupo terapéutico', m!.therapeutic_group_label],
                                ['Familias para alergias cruzadas', families(m!.allergy_groups)],
                                ['Vigente', `Desde el ${formatDate(m!.valid_from)}${m!.valid_to ? ` hasta el ${formatDate(m!.valid_to)} (ese día ya no)` : ''}`],
                                ['Recetas', meta!.recetas === 0 ? 'Nunca se ha recetado' : `Se usó en ${meta!.recetas} ${meta!.recetas === 1 ? 'receta' : 'recetas'}`],
                            ] as const).map(([label, value]) => (
                                <div key={label}><dt className="font-semibold text-slate-500">{label}</dt><dd className="mt-0.5 text-slate-900">{value}</dd></div>
                            ))}
                        </dl>
                        {m!.close_reason && <Alert tone="info">Motivo de la baja: {m!.close_reason}</Alert>}

                        {manage && (
                            <div className="flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                                {meta!.abierto && latest && <Button size="sm" variant="secondary" onClick={() => setAction('version')}>Nueva versión</Button>}
                                {meta!.abierto && latest && <Button size="sm" variant="secondary" onClick={() => setAction('retire')}>Dar de baja</Button>}
                                {meta!.puede_eliminarse
                                    ? <Button size="sm" variant="ghost" onClick={() => setAction('delete')}>Eliminar registro erróneo</Button>
                                    : <p className="self-center text-xs text-slate-600">No se puede eliminar porque ya se recetó: si deja de usarse, dele de baja.</p>}
                            </div>
                        )}

                        <section aria-labelledby="med-history" className="border-t border-slate-200 pt-4">
                            <h3 id="med-history" className="font-bold text-nido-900">Historial de versiones</h3>
                            {history.isLoading ? <Spinner /> : history.isError ? <Alert tone="error">{errorMessage(history.error)}</Alert> : (
                                <ol className="mt-2 space-y-2">
                                    {history.data!.map((v) => (
                                        <li key={v.id} className="rounded-lg bg-slate-50 px-3 py-2">
                                            <p className="flex flex-wrap items-center gap-2 font-semibold text-slate-900">
                                                Versión {v.version} <Badge tone={stateStyle[v.state].tone}>{stateStyle[v.state].label}</Badge>
                                            </p>
                                            <p className="text-slate-600">{v.strength} · {v.presentation} · desde el {formatDate(v.valid_from, undefined, { dateStyle: 'medium' })}
                                                {v.valid_to ? ` hasta el ${formatDate(v.valid_to, undefined, { dateStyle: 'medium' })}` : ''}</p>
                                            <p className="text-xs text-slate-500">Registró: {v.created_by}{v.closed_by ? ` · cerró: ${v.closed_by}` : ''}{v.close_reason ? ` · motivo: ${v.close_reason}` : ''}</p>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    </div>
                )}
            </Dialog>

            {action === 'version' && latest && <MedicationFormDialog base={latest} options={options} onClose={() => setAction(null)} onOpenCode={(c) => { setAction(null); onOpenCode(c); }}
                onDone={(v) => { setAction(null); onChanged(`${v.code} tiene la versión ${v.version}, vigente desde el ${formatDate(v.valid_from)}.`, false); }} />}
            {action === 'retire' && latest && <MedicationRetireDialog medication={latest} onClose={() => setAction(null)}
                onDone={(v) => { setAction(null); onChanged(`${v.code} se dio de baja desde el ${formatDate(v.valid_to!)}.`, false); }} />}
            {action === 'delete' && m && <MedicationDeleteDialog medication={m} onClose={() => setAction(null)}
                onDone={() => { setAction(null); onChanged(`${m.code} se eliminó del catálogo.`, true); }} />}
        </>
    );
}
