import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { session as storage, type Session, type TokenResponse } from '@/lib/session';

interface AuthState {
    session: Session | null;
    loading: boolean;
    login: (hospitalId: string, email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    switchHospital: (hospitalId: string) => Promise<void>;
    can: (permission: string) => boolean;
}

const AuthContext = createContext<AuthState | null>(null);

// El JWT dura 15 minutos: se renueva un minuto antes de vencer mientras la pestaña siga abierta.
const REFRESH_MARGIN_MS = 60_000;

export function AuthProvider({ children }: { children: ReactNode }) {
    const queryClient = useQueryClient();
    const [current, setCurrent] = useState<Session | null>(null);
    const [loading, setLoading] = useState(Boolean(storage.token()));

    const reset = useCallback(() => {
        storage.clear();
        setCurrent(null);
        queryClient.clear();
    }, [queryClient]);

    // Al recargar la página: si hay token, se recupera la sesión desde la API.
    useEffect(() => {
        if (!storage.token()) return;
        api.get<Session>('/auth/me')
            .then(({ data }) => setCurrent(data))
            .catch(reset)
            .finally(() => setLoading(false));
    }, [reset]);

    useEffect(() => {
        const onExpired = () => {
            setCurrent(null);
            queryClient.clear();
        };
        window.addEventListener('nido:session-expired', onExpired);
        return () => window.removeEventListener('nido:session-expired', onExpired);
    }, [queryClient]);

    useEffect(() => {
        if (!current) return;
        const delay = Math.max(5_000, storage.expiresAt() - Date.now() - REFRESH_MARGIN_MS);
        const timer = window.setTimeout(async () => {
            try {
                const { data } = await api.post<TokenResponse>('/auth/refresh');
                storage.store(data);
                setCurrent((s) => (s ? { ...s } : s));
            } catch {
                reset();
            }
        }, delay);
        return () => window.clearTimeout(timer);
    }, [current, reset]);

    const login = useCallback(async (hospitalId: string, email: string, password: string) => {
        storage.setHospital(hospitalId);
        const { data } = await api.post<TokenResponse>('/auth/login', { email, password });
        storage.store(data, hospitalId);
        setCurrent(data.session ?? null);
    }, []);

    const logout = useCallback(async () => {
        try {
            await api.post('/auth/logout');
        } finally {
            reset();
        }
    }, [reset]);

    const switchHospital = useCallback(async (hospitalId: string) => {
        const { data } = await api.post<TokenResponse>('/auth/cambiar-hospital', { hospital_id: hospitalId });
        storage.store(data, hospitalId);
        queryClient.clear();
        setCurrent(data.session ?? null);
    }, [queryClient]);

    const value = useMemo<AuthState>(() => ({
        session: current,
        loading,
        login,
        logout,
        switchHospital,
        can: (permission) => current?.permissions.includes(permission) ?? false,
    }), [current, loading, login, logout, switchHospital]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth debe usarse dentro de AuthProvider');
    return ctx;
}
