import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FlaskConical } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { flagLabel, itemStatus, range, type LabOrder, type LabTest } from '@/modules/clinical/lab';
import { Alert, Badge, Button, Card, EmptyState, Field, Select, Spinner, Textarea } from '@/components/ui';

/** Pestaña «Laboratorio» del expediente: pedir exámenes (módulo órdenes desde EMR) y ver sus resultados. */
export function LabTab({ patientId }: { patientId: string }) {
    const { can, session } = useAuth();
    const queryClient = useQueryClient();
    const [creating, setCreating] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const orders = useQuery({ queryKey: ['lab', 'paciente', patientId], queryFn: async () => (await api.get<{ data: LabOrder[] }>(`/expediente/${patientId}/laboratorio`)).data.data });

    return (
        <>
            {notice && <div className="mb-4"><Alert tone="success">{notice}</Alert></div>}
            {can('ordenes_lab.crear') && !creating && <Button className="mb-4" onClick={() => { setNotice(null); setCreating(true); }}><FlaskConical className="size-4" aria-hidden /> Pedir exámenes</Button>}
            {creating && <OrderForm patientId={patientId} onClose={() => setCreating(false)} onDone={(n) => { setCreating(false); setNotice(`Orden enviada al laboratorio con ${n} prueba${n === 1 ? '' : 's'}.`); queryClient.invalidateQueries({ queryKey: ['lab'] }); }} />}
            {orders.isLoading ? <Spinner /> : orders.isError ? <Alert tone="error">{errorMessage(orders.error)}</Alert> : orders.data!.length === 0 ? (
                <Card><EmptyState title="Sin exámenes de laboratorio." /></Card>
            ) : (
                <ul className="space-y-4">
                    {orders.data!.map((o) => (
                        <li key={o.id}>
                            <Card className="p-5">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="font-semibold text-slate-900">{formatDate(o.created_at, session?.hospital.timezone)} · {o.ordered_by}</p>
                                        <p className="text-sm text-slate-600">Indicación: {o.indication}</p>
                                    </div>
                                    <div className="flex gap-2">{o.priority === 'urgent' && <Badge tone="critical">Urgente</Badge>}<Badge tone={o.status === 'completed' ? 'ok' : 'nido'}>{o.status === 'completed' ? 'Completa' : 'En curso'}</Badge></div>
                                </div>
                                <table className="mt-3 min-w-full text-sm">
                                    <caption className="sr-only">Pruebas de la orden</caption>
                                    <thead><tr className="text-left text-xs uppercase text-slate-500"><th scope="col" className="py-1">Prueba</th><th scope="col">Resultado</th><th scope="col" className="hidden sm:table-cell">Referencia</th><th scope="col">Estado</th></tr></thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {o.items.map((i) => (
                                            <tr key={i.id}>
                                                <td className="py-2 font-semibold text-slate-800">{i.test_name}</td>
                                                <td>{i.result && i.status === 'validated' ? <span className={flagLabel[i.result.flag].className}>{i.result.value} {i.unit}{i.result.flag !== 'normal' && ` (${flagLabel[i.result.flag].label})`}</span>
                                                    : <span className="text-slate-500">{i.status === 'rejected' ? i.rejection_reason : 'Pendiente'}</span>}</td>
                                                <td className="hidden text-slate-600 sm:table-cell">{range(i)}</td>
                                                <td><Badge tone={itemStatus[i.status].tone}>{itemStatus[i.status].label}</Badge></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}

function OrderForm({ patientId, onClose, onDone }: { patientId: string; onClose: () => void; onDone: (n: number) => void }) {
    const [selected, setSelected] = useState<string[]>([]);
    const [indication, setIndication] = useState('');
    const [priority, setPriority] = useState('routine');
    const tests = useQuery({ queryKey: ['lab', 'pruebas'], queryFn: async () => (await api.get<{ data: LabTest[] }>('/laboratorio/pruebas')).data.data });
    const save = useMutation({ mutationFn: () => api.post(`/expediente/${patientId}/laboratorio`, { tests: selected, indication, priority }), onSuccess: () => onDone(selected.length) });
    const toggle = (code: string) => setSelected((s) => (s.includes(code) ? s.filter((c) => c !== code) : [...s, code]));

    return (
        <Card className="mb-6 p-5 ring-2 ring-nido-300">
            <h2 className="font-bold text-nido-900">Pedir exámenes de laboratorio</h2>
            <form className="mt-4 space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
                <fieldset>
                    <legend className="text-sm font-semibold text-slate-700">Pruebas ({selected.length} seleccionada{selected.length === 1 ? '' : 's'})</legend>
                    {tests.isLoading ? <Spinner /> : (
                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                            {tests.data?.map((t) => (
                                <label key={t.code} className="flex items-start gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-slate-200 hover:bg-slate-50">
                                    <input type="checkbox" className="mt-0.5 accent-nido-700" checked={selected.includes(t.code)} onChange={() => toggle(t.code)} />
                                    <span><strong>{t.name}</strong><span className="block text-xs text-slate-600">{t.specimen_type}</span></span>
                                </label>
                            ))}
                        </div>
                    )}
                </fieldset>
                <div className="grid gap-4 sm:grid-cols-[1fr_12rem]">
                    <Field label="Indicación clínica" htmlFor="lab-ind" hint="Para qué se pide; lo verá el laboratorio."><Textarea id="lab-ind" rows={2} value={indication} onChange={(e) => setIndication(e.target.value)} placeholder="Ej.: Control de anemia" /></Field>
                    <Field label="Prioridad" htmlFor="lab-pri"><Select id="lab-pri" value={priority} onChange={(e) => setPriority(e.target.value)}><option value="routine">Rutina</option><option value="urgent">Urgente</option></Select></Field>
                </div>
                {save.isError && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={save.isPending} disabled={selected.length === 0}>Enviar al laboratorio</Button>
                </div>
            </form>
        </Card>
    );
}
