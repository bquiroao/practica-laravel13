import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Bell, LogOut, Menu, Search, X } from 'lucide-react';
import { useAuth } from '@/auth/AuthContext';
import { api } from '@/lib/api';
import { cn } from '@/lib/cn';
import { sections } from '@/modules/catalog';
import { Logo, Select } from '@/components/ui';
import { roleName } from '@/modules/roles';
import { AlertBell } from '@/modules/alerts/AlertBell';

interface HospitalOption { id: string; code: string; name: string }

export function AppShell() {
    const { session, can, logout, switchHospital } = useAuth();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const [switching, setSwitching] = useState(false);
    const hospitals = useQuery({
        queryKey: ['auth', 'hospitales'],
        queryFn: async () => (await api.get<{ data: HospitalOption[] }>('/auth/hospitales')).data.data,
    });

    if (!session) return null;

    const visible = sections
        .map((s) => ({ ...s, items: s.items.filter((i) => i.anyOf.some(can)) }))
        .filter((s) => s.items.length > 0);

    const onSwitch = async (id: string) => {
        setSwitching(true);
        try {
            await switchHospital(id);
            navigate('/inicio');
        } finally {
            setSwitching(false);
        }
    };

    return (
        <div className="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
            <a href="#contenido" className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-white focus:px-3 focus:py-2">
                Saltar al contenido
            </a>

            <aside className={cn('fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-nido-800 text-nido-50 transition lg:static lg:translate-x-0',
                open && 'translate-x-0')} aria-label="Navegación principal">
                <div className="flex items-center justify-between gap-2 px-5 py-5">
                    <div className="flex items-center gap-2.5">
                        <Logo />
                        <div className="leading-tight">
                            <p className="font-extrabold text-white">Hospital Nido</p>
                            <p className="text-xs text-salvia">Protección y cuidado</p>
                        </div>
                    </div>
                    <button className="rounded p-1 lg:hidden" onClick={() => setOpen(false)} aria-label="Cerrar menú"><X className="size-5" /></button>
                </div>
                <nav className="space-y-5 px-3 pb-8">
                    {visible.map((section) => (
                        <div key={section.title}>
                            <p className="px-3 pb-1 text-[0.7rem] font-bold uppercase tracking-wider text-nido-300">{section.title}</p>
                            <ul className="space-y-0.5">
                                {section.items.map(({ path, label, icon: Icon, ready }) => (
                                    <li key={path}>
                                        <NavLink to={path} onClick={() => setOpen(false)}
                                            className={({ isActive }) => cn('flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition',
                                                isActive ? 'bg-white text-nido-800' : 'text-nido-50 hover:bg-nido-700')}>
                                            <Icon className="size-4.5 shrink-0" aria-hidden />
                                            <span className="flex-1">{label}</span>
                                            {!ready && <span className="rounded bg-nido-900 px-1.5 text-[0.65rem] font-bold text-nido-50" title="En construcción">pronto</span>}
                                        </NavLink>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </nav>
            </aside>
            {open && <div className="fixed inset-0 z-30 bg-nido-900/40 lg:hidden" onClick={() => setOpen(false)} aria-hidden />}

            <div className="min-w-0">
                <header className="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-slate-200 bg-white/95 px-3 backdrop-blur sm:gap-3 sm:px-6">
                    <button className="rounded p-2 text-nido-800 lg:hidden" onClick={() => setOpen(true)} aria-label="Abrir menú"><Menu className="size-5" /></button>

                    <label className="sr-only" htmlFor="hospital-actual">Hospital</label>
                    <Select id="hospital-actual" className="w-auto min-w-0 max-w-[10.5rem] font-semibold sm:max-w-56" value={session.hospital.id} disabled={switching || !hospitals.data}
                        onChange={(e) => onSwitch(e.target.value)}>
                        {(hospitals.data ?? [session.hospital]).map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
                    </Select>

                    {can('pacientes.ver') ? (
                        <form role="search" className="relative hidden flex-1 md:block" onSubmit={(e) => {
                            e.preventDefault();
                            const q = new FormData(e.currentTarget).get('q')?.toString().trim() ?? '';
                            navigate(q ? `/pacientes?q=${encodeURIComponent(q)}` : '/pacientes');
                            e.currentTarget.reset();
                        }}>
                            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-500" aria-hidden />
                            <input type="search" name="q" placeholder="Buscar paciente: nombre, DPI o código y Enter" aria-label="Buscar paciente"
                                className="h-10 w-full max-w-md rounded-lg bg-slate-100 pl-9 pr-3 text-sm placeholder:text-slate-500 focus:bg-white focus:ring-2 focus:ring-nido-500" />
                        </form>
                    ) : <div className="flex-1" />}

                    <div className="ml-auto flex items-center gap-2">
                        {can('alertas.ver') && (
                            <NavLink to="/alertas" className="relative rounded-lg p-2 text-nido-800 hover:bg-nido-50" aria-label="Alertas críticas">
                                <Bell className="size-5" aria-hidden />
                                {/* Módulo alertas-criticas-notificaciones-internas: alertas que esperan el acuse de este usuario. */}
                                <AlertBell />
                            </NavLink>
                        )}
                        <div className="hidden text-right leading-tight sm:block">
                            <p className="text-sm font-bold text-slate-800">{session.user.name}</p>
                            <p className="text-xs text-slate-500">{session.roles.map(roleName).join(', ')}</p>
                        </div>
                        <button onClick={() => logout().then(() => navigate('/login'))} className="rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Cerrar sesión" title="Cerrar sesión">
                            <LogOut className="size-5" aria-hidden />
                        </button>
                    </div>
                </header>

                <main id="contenido" className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
