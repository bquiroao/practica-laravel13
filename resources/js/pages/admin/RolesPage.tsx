import { Fragment, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, Pencil } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage } from '@/lib/api';
import { Alert, Badge, Button, Card, Dialog, PageHeader, Spinner, Table } from '@/components/ui';

interface Matrix {
    roles: { code: string; name: string; scope: 'hospital' | 'network'; permissions: string[] }[];
    permissions: { code: string; module: string; description: string }[];
}

/** Módulo RBAC: matriz rol × permiso agrupada por módulo. Solo el administrador de red la cambia. */
export function RolesPage() {
    const { can } = useAuth();
    const [editing, setEditing] = useState<Matrix['roles'][number] | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const matrix = useQuery({ queryKey: ['roles', 'matriz'], queryFn: async () => (await api.get<Matrix>('/roles')).data });

    if (matrix.isLoading) return <Spinner />;
    if (matrix.isError) return <Alert tone="error">{errorMessage(matrix.error)}</Alert>;
    const { roles, permissions } = matrix.data!;
    const modules = [...new Set(permissions.map((p) => p.module))];

    return (
        <>
            <PageHeader title="Roles y permisos" description="Cada permiso viene de un módulo del sistema. Un usuario recibe la suma de los permisos de sus roles vigentes en el hospital." />
            {notice && <div className="mb-4"><Alert tone="success">{notice}</Alert></div>}
            <Card>
                <Table caption="Matriz de roles y permisos">
                    <thead className="sticky top-16 bg-slate-50">
                        <tr>
                            <th scope="col" className="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Permiso</th>
                            {roles.map((r) => (
                                <th key={r.code} scope="col" className="px-2 py-3 text-center align-bottom text-xs font-bold text-slate-600">
                                    <span className="block whitespace-nowrap">{r.name}</span>
                                    {r.scope === 'network' && <Badge tone="nido">red</Badge>}
                                    {can('roles.configurar') && (
                                        <button onClick={() => setEditing(r)} className="mx-auto mt-1 flex rounded p-1 text-nido-700 hover:bg-nido-50" aria-label={`Editar permisos de ${r.name}`}>
                                            <Pencil className="size-3.5" aria-hidden />
                                        </button>
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {modules.map((module) => (
                            <Fragment key={module}>
                                <tr className="bg-nido-50/60">
                                    <th colSpan={roles.length + 1} scope="colgroup" className="px-4 py-2 text-left text-xs font-bold text-nido-800">{module}</th>
                                </tr>
                                {permissions.filter((p) => p.module === module).map((p) => (
                                    <tr key={p.code} className="border-t border-slate-100">
                                        <th scope="row" className="px-4 py-2 text-left font-normal">
                                            <span className="block text-slate-800">{p.description}</span>
                                            <code className="text-xs text-slate-500">{p.code}</code>
                                        </th>
                                        {roles.map((r) => (
                                            <td key={r.code} className="px-2 py-2 text-center">
                                                {r.permissions.includes(p.code)
                                                    ? <Check className="mx-auto size-4 text-normal" aria-label="Sí" />
                                                    : <span className="text-slate-300" aria-label="No">·</span>}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </Fragment>
                        ))}
                    </tbody>
                </Table>
            </Card>
            {editing && <EditRoleDialog role={editing} permissions={permissions} onClose={() => setEditing(null)}
                onSaved={() => { setNotice(`Se actualizaron los permisos de ${editing.name}.`); setEditing(null); }} />}
        </>
    );
}

function EditRoleDialog({ role, permissions, onClose, onSaved }: {
    role: Matrix['roles'][number]; permissions: Matrix['permissions']; onClose: () => void; onSaved: () => void;
}) {
    const queryClient = useQueryClient();
    const [selected, setSelected] = useState(new Set(role.permissions));
    const save = useMutation({
        mutationFn: () => api.put(`/roles/${role.code}/permisos`, { permissions: [...selected] }),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['roles'] }); onSaved(); },
    });
    const toggle = (code: string) => {
        const next = new Set(selected);
        if (next.has(code)) next.delete(code);
        else next.add(code);
        setSelected(next);
    };
    return (
        <Dialog open onClose={onClose} title={`Permisos de ${role.name}`}>
            <form onSubmit={(e) => { e.preventDefault(); save.mutate(); }} className="space-y-4">
                {save.isError && <Alert tone="error">{errorMessage(save.error)}</Alert>}
                <fieldset className="max-h-[55vh] space-y-1 overflow-y-auto pr-2">
                    <legend className="sr-only">Permisos</legend>
                    {permissions.map((p) => (
                        <label key={p.code} className="flex items-start gap-2 rounded px-2 py-1.5 text-sm hover:bg-slate-50">
                            <input type="checkbox" className="mt-0.5 accent-nido-700" checked={selected.has(p.code)} onChange={() => toggle(p.code)} />
                            <span>{p.description} <code className="text-xs text-slate-500">{p.code}</code></span>
                        </label>
                    ))}
                </fieldset>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={save.isPending}>Guardar</Button>
                </div>
            </form>
        </Dialog>
    );
}
