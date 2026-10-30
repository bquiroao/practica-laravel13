import { useState, type FormEvent } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Search, ShieldPlus, X } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api, errorMessage, fieldErrors, type Paginated } from '@/lib/api';
import { roleName } from '@/modules/roles';
import { Alert, Badge, Button, Card, Dialog, EmptyState, Field, Input, PageHeader, Select, Spinner, Table, td, th } from '@/components/ui';
import { Pagination } from '@/components/Pagination';

interface UserRow {
    id: string;
    name: string;
    email: string;
    status: 'active' | 'inactive';
    last_authenticated_at: string | null;
    roles: { membership_id: string; role: string; valid_to: string | null }[];
}
interface RoleOption { code: string; name: string; scope: 'hospital' | 'network' }

const dateTime = (iso: string | null) => (iso ? new Date(iso).toLocaleString('es-GT', { dateStyle: 'medium', timeStyle: 'short' }) : 'Nunca');

/** Módulo usuarios/tenants + RBAC: usuarios del hospital actual, alta, estado, asignación y revocación de roles. */
export function UsersPage() {
    const { can, session } = useAuth();
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState('');
    const [creating, setCreating] = useState(false);
    const [assignTo, setAssignTo] = useState<UserRow | null>(null);
    const [revoke, setRevoke] = useState<{ user: UserRow; membership: string; role: string } | null>(null);
    const [notice, setNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);

    const users = useQuery({
        queryKey: ['usuarios', page, query],
        queryFn: async () => (await api.get<Paginated<UserRow>>('/usuarios', { params: { page, q: query || undefined } })).data,
        placeholderData: keepPreviousData,
    });
    const roles = useQuery({
        queryKey: ['roles'],
        queryFn: async () => (await api.get<{ roles: RoleOption[] }>('/roles')).data.roles,
        enabled: can('roles.ver'),
    });
    const assignable = (roles.data ?? []).filter((r) => r.scope === 'hospital' || can('roles.configurar'));
    // Los roles de red (administrador de red) solo los quita otro administrador de red.
    const isHospitalRole = (code: string) => roles.data?.find((r) => r.code === code)?.scope === 'hospital';

    const done = (text: string) => {
        setNotice({ tone: 'success', text });
        queryClient.invalidateQueries({ queryKey: ['usuarios'] });
    };
    const toggleStatus = useMutation({
        mutationFn: (u: UserRow) => api.patch(`/usuarios/${u.id}/estado`, { status: u.status === 'active' ? 'inactive' : 'active' }),
        onSuccess: (_, u) => done(`${u.name} quedó ${u.status === 'active' ? 'inactivo' : 'activo'}.`),
        onError: (e) => setNotice({ tone: 'error', text: errorMessage(e) }),
    });

    const onSearch = (e: FormEvent) => {
        e.preventDefault();
        setPage(1);
        setQuery(search.trim());
    };

    return (
        <>
            <PageHeader title="Usuarios" description={`Personas con acceso a ${session?.hospital.name}.`}
                actions={can('usuarios.gestionar') && <Button onClick={() => setCreating(true)}><Plus className="size-4" aria-hidden /> Nuevo usuario</Button>} />

            {notice && <div className="mb-4"><Alert tone={notice.tone}>{notice.text}</Alert></div>}

            <Card>
                <form onSubmit={onSearch} className="flex gap-2 border-b border-slate-200 p-4" role="search">
                    <label htmlFor="buscar-usuario" className="sr-only">Buscar usuario</label>
                    <Input id="buscar-usuario" placeholder="Buscar por nombre o correo" value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-sm" />
                    <Button type="submit" variant="secondary"><Search className="size-4" aria-hidden /> Buscar</Button>
                </form>

                {users.isLoading ? <Spinner /> : users.isError ? <div className="p-4"><Alert tone="error">{errorMessage(users.error)}</Alert></div>
                    : users.data!.data.length === 0 ? <EmptyState title="No hay usuarios que coincidan." /> : (
                        <Table caption="Usuarios del hospital">
                            <thead className="bg-slate-50"><tr>
                                <th scope="col" className={th}>Usuario</th><th scope="col" className={th}>Roles</th>
                                <th scope="col" className={th}>Último acceso</th><th scope="col" className={th}>Estado</th>
                                <th scope="col" className={th}><span className="sr-only">Acciones</span></th>
                            </tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {users.data!.data.map((u) => (
                                    <tr key={u.id}>
                                        <td className={td}><p className="font-semibold text-slate-900">{u.name}</p><p className="text-xs text-slate-500">{u.email}</p></td>
                                        <td className={td}>
                                            <div className="flex flex-wrap gap-1">
                                                {u.roles.length === 0 && <span className="text-xs text-slate-400">Sin rol vigente</span>}
                                                {u.roles.map((r) => (
                                                    <span key={r.membership_id} className="inline-flex items-center gap-1 rounded-full bg-nido-100 py-0.5 pl-2 pr-1 text-xs font-semibold text-nido-800">
                                                        {roleName(r.role)}
                                                        {can('roles.revocar') && u.id !== session?.user.id && (isHospitalRole(r.role) || can('roles.configurar')) && (
                                                            <button onClick={() => setRevoke({ user: u, membership: r.membership_id, role: r.role })}
                                                                className="rounded-full p-0.5 hover:bg-nido-200" aria-label={`Revocar ${roleName(r.role)} a ${u.name}`}>
                                                                <X className="size-3" aria-hidden />
                                                            </button>
                                                        )}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className={td}>{dateTime(u.last_authenticated_at)}</td>
                                        <td className={td}>{u.status === 'active' ? <Badge tone="ok">Activo</Badge> : <Badge tone="neutral">Inactivo</Badge>}</td>
                                        <td className={`${td} whitespace-nowrap text-right`}>
                                            {u.id !== session?.user.id && (
                                                <div className="flex justify-end gap-1">
                                                    {can('roles.asignar') && <Button size="sm" variant="ghost" onClick={() => setAssignTo(u)}><ShieldPlus className="size-4" aria-hidden /> Rol</Button>}
                                                    {can('usuarios.gestionar') && (
                                                        <Button size="sm" variant="ghost" loading={toggleStatus.isPending && toggleStatus.variables?.id === u.id} onClick={() => toggleStatus.mutate(u)}>
                                                            {u.status === 'active' ? 'Desactivar' : 'Activar'}
                                                        </Button>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                {users.data && <Pagination meta={users.data.meta} onPage={setPage} />}
            </Card>

            <CreateUserDialog open={creating} roles={assignable} onClose={() => setCreating(false)} onCreated={(name) => { setCreating(false); done(`Se creó la cuenta de ${name}.`); }} />
            <AssignRoleDialog user={assignTo} roles={assignable} onClose={() => setAssignTo(null)} onDone={(text) => { setAssignTo(null); done(text); }} />
            <RevokeDialog target={revoke} onClose={() => setRevoke(null)} onDone={(text) => { setRevoke(null); done(text); }} />
        </>
    );
}

function CreateUserDialog({ open, roles, onClose, onCreated }: { open: boolean; roles: RoleOption[]; onClose: () => void; onCreated: (name: string) => void }) {
    const [form, setForm] = useState({ name: '', email: '', password: '', role: '' });
    const create = useMutation({
        mutationFn: () => api.post('/usuarios', form),
        onSuccess: () => { onCreated(form.name); setForm({ name: '', email: '', password: '', role: '' }); create.reset(); },
    });
    const errors = fieldErrors(create.error);
    const set = (k: keyof typeof form) => (e: { target: { value: string } }) => {
        const value = e.target.value;
        setForm((f) => ({ ...f, [k]: value }));
    };

    return (
        <Dialog open={open} onClose={onClose} title="Nuevo usuario">
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); create.mutate(); }}>
                {create.isError && !Object.keys(errors).length && <Alert tone="error">{errorMessage(create.error)}</Alert>}
                <Field label="Nombre completo" htmlFor="nu-name" error={errors.name}><Input id="nu-name" required value={form.name} onChange={set('name')} /></Field>
                <Field label="Correo" htmlFor="nu-email" error={errors.email}><Input id="nu-email" type="email" required value={form.email} onChange={set('email')} /></Field>
                <Field label="Contraseña inicial" htmlFor="nu-pass" error={errors.password} hint="Al menos 12 caracteres, con letras y números.">
                    <Input id="nu-pass" type="password" autoComplete="new-password" required value={form.password} onChange={set('password')} />
                </Field>
                <Field label="Rol en este hospital" htmlFor="nu-role" error={errors.role}>
                    <Select id="nu-role" required value={form.role} onChange={set('role')}>
                        <option value="">Seleccione…</option>
                        {roles.map((r) => <option key={r.code} value={r.code}>{r.name}</option>)}
                    </Select>
                </Field>
                <div className="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={create.isPending}>Crear usuario</Button>
                </div>
            </form>
        </Dialog>
    );
}

function AssignRoleDialog({ user, roles, onClose, onDone }: { user: UserRow | null; roles: RoleOption[]; onClose: () => void; onDone: (t: string) => void }) {
    const [role, setRole] = useState('');
    const [validTo, setValidTo] = useState('');
    const assign = useMutation({
        mutationFn: () => api.post('/membresias', { user_id: user!.id, role, valid_to: validTo || undefined }),
        onSuccess: () => { onDone(`Se asignó ${roleName(role)} a ${user!.name}.`); setRole(''); setValidTo(''); assign.reset(); },
    });
    return (
        <Dialog open={user !== null} onClose={onClose} title={`Asignar rol a ${user?.name ?? ''}`}>
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); assign.mutate(); }}>
                {assign.isError && <Alert tone="error">{errorMessage(assign.error)}</Alert>}
                <Field label="Rol" htmlFor="as-role">
                    <Select id="as-role" required value={role} onChange={(e) => setRole(e.target.value)}>
                        <option value="">Seleccione…</option>
                        {roles.map((r) => <option key={r.code} value={r.code}>{r.name}</option>)}
                    </Select>
                </Field>
                <Field label="Vigente hasta (opcional)" htmlFor="as-to" hint="Vacío: sin fecha de fin.">
                    <Input id="as-to" type="date" value={validTo} onChange={(e) => setValidTo(e.target.value)} />
                </Field>
                <div className="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" loading={assign.isPending}>Asignar</Button>
                </div>
            </form>
        </Dialog>
    );
}

function RevokeDialog({ target, onClose, onDone }: { target: { user: UserRow; membership: string; role: string } | null; onClose: () => void; onDone: (t: string) => void }) {
    const [motivo, setMotivo] = useState('');
    const revoke = useMutation({
        mutationFn: () => api.post(`/membresias/${target!.membership}/revocar`, { motivo }),
        onSuccess: () => { onDone(`Se revocó ${roleName(target!.role)} a ${target!.user.name}.`); setMotivo(''); revoke.reset(); },
    });
    return (
        <Dialog open={target !== null} onClose={onClose} title="Revocar rol">
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); revoke.mutate(); }}>
                <p className="text-sm text-slate-600">Se quitará <strong>{target && roleName(target.role)}</strong> a <strong>{target?.user.name}</strong>. El cambio aplica de inmediato y queda en la bitácora.</p>
                {revoke.isError && <Alert tone="error">{errorMessage(revoke.error)}</Alert>}
                <Field label="Motivo" htmlFor="rv-motivo" hint="Al menos 10 caracteres.">
                    <Input id="rv-motivo" required minLength={10} value={motivo} onChange={(e) => setMotivo(e.target.value)} />
                </Field>
                <div className="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button type="submit" variant="danger" loading={revoke.isPending}>Revocar</Button>
                </div>
            </form>
        </Dialog>
    );
}
