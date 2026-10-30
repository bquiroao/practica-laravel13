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

const ready: Record<string, ReactNode> = {
    '/inicio': <DashboardPage />,
    '/admin/usuarios': <UsersPage />,
    '/admin/roles': <RolesPage />,
    '/admin/auditoria': <AuditPage />,
};

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
                <Route path="*" element={<NotFoundPage />} />
            </Route>
        </Routes>
    );
}
