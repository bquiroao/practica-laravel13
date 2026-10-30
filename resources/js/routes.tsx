import { lazy, Suspense, type ReactNode } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { useAuth } from '@/auth/AuthContext';
import { AppShell } from '@/layout/AppShell';
import { Spinner } from '@/components/ui';
import { allEntries } from '@/modules/catalog';
import { LoginPage } from '@/pages/LoginPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { ModulePendingPage } from '@/pages/ModulePendingPage';
import { ForbiddenPage, NotFoundPage } from '@/pages/StatusPages';

// Cada pantalla de módulo se descarga cuando se abre, no en el inicio de sesión.
const UsersPage = lazy(() => import('@/pages/admin/UsersPage').then((m) => ({ default: m.UsersPage })));
const RolesPage = lazy(() => import('@/pages/admin/RolesPage').then((m) => ({ default: m.RolesPage })));
const AuditPage = lazy(() => import('@/pages/admin/AuditPage').then((m) => ({ default: m.AuditPage })));
const PatientsPage = lazy(() => import('@/pages/patients/PatientsPage').then((m) => ({ default: m.PatientsPage })));
const PatientDetailPage = lazy(() => import('@/pages/patients/PatientDetailPage').then((m) => ({ default: m.PatientDetailPage })));
const PatientFormPage = lazy(() => import('@/pages/patients/PatientFormPage').then((m) => ({ default: m.PatientFormPage })));
const DoctorsPage = lazy(() => import('@/pages/doctors/DoctorsPage').then((m) => ({ default: m.DoctorsPage })));
const DoctorDetailPage = lazy(() => import('@/pages/doctors/DoctorDetailPage').then((m) => ({ default: m.DoctorDetailPage })));
const AgendaPage = lazy(() => import('@/pages/appointments/AgendaPage').then((m) => ({ default: m.AgendaPage })));
const BedsPage = lazy(() => import('@/pages/beds/BedsPage').then((m) => ({ default: m.BedsPage })));
const OccupancyPage = lazy(() => import('@/pages/beds/OccupancyPage').then((m) => ({ default: m.OccupancyPage })));
const NewAppointmentPage = lazy(() => import('@/pages/appointments/NewAppointmentPage').then((m) => ({ default: m.NewAppointmentPage })));

const ready: Record<string, ReactNode> = {
    '/inicio': <DashboardPage />,
    '/admin/usuarios': <UsersPage />,
    '/admin/roles': <RolesPage />,
    '/admin/auditoria': <AuditPage />,
    '/pacientes': <PatientsPage />,
    '/medicos': <DoctorsPage />,
    '/citas': <AgendaPage />,
    '/camas': <BedsPage />,
    '/ocupacion': <OccupancyPage />,
};

// Pantallas internas de un módulo: mismo permiso que pida la acción.
const subPages: { path: string; anyOf: string[]; element: ReactNode }[] = [
    { path: '/pacientes/nuevo', anyOf: ['pacientes.gestionar'], element: <PatientFormPage /> },
    { path: '/pacientes/:id', anyOf: ['pacientes.ver'], element: <PatientDetailPage /> },
    { path: '/pacientes/:id/editar', anyOf: ['pacientes.gestionar'], element: <PatientFormPage /> },
    { path: '/medicos/:id', anyOf: ['medicos.ver'], element: <DoctorDetailPage /> },
    { path: '/citas/nueva', anyOf: ['citas.gestionar'], element: <NewAppointmentPage /> },
];

function RequireSession({ children }: { children: ReactNode }) {
    const { session, loading } = useAuth();
    const location = useLocation();
    if (loading) return <Spinner label="Recuperando la sesión…" />;
    if (!session) return <Navigate to="/login" replace state={{ from: location.pathname }} />;
    return <>{children}</>;
}

function RequirePermission({ anyOf, children }: { anyOf: string[]; children: ReactNode }) {
    const { can } = useAuth();
    return anyOf.some(can) ? <>{children}</> : <ForbiddenPage />;
}

export function AppRoutes() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route element={<RequireSession><AppShell /></RequireSession>}>
                <Route index element={<Navigate to="/inicio" replace />} />
                {allEntries.map((entry) => (
                    <Route key={entry.path} path={entry.path} element={
                        <RequirePermission anyOf={entry.anyOf}>
                            <Suspense fallback={<Spinner />}>{ready[entry.path] ?? <ModulePendingPage entry={entry} />}</Suspense>
                        </RequirePermission>
                    } />
                ))}
                {subPages.map((p) => (
                    <Route key={p.path} path={p.path} element={
                        <RequirePermission anyOf={p.anyOf}><Suspense fallback={<Spinner />}>{p.element}</Suspense></RequirePermission>
                    } />
                ))}
                <Route path="*" element={<NotFoundPage />} />
            </Route>
        </Routes>
    );
}
