import axios, { AxiosError } from 'axios';
import { session } from './session';

/** Cliente de la API de Laravel: agrega el token y el hospital (X-Tenant-ID) a cada petición. */
export const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

api.interceptors.request.use((config) => {
    const token = session.token();
    const hospitalId = session.hospitalId();
    if (token) config.headers.Authorization = `Bearer ${token}`;
    if (hospitalId) config.headers['X-Tenant-ID'] = hospitalId;
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        // Sesión vencida o rol revocado: se vuelve al inicio de sesión.
        const isLogin = error.config?.url?.endsWith('/auth/login');
        if (!isLogin && (error.response?.status === 401 || (error.response?.status === 403 && isSessionRevoked(error)))) {
            session.clear();
            window.dispatchEvent(new Event('nido:session-expired'));
        }
        return Promise.reject(error);
    },
);

function isSessionRevoked(error: AxiosError): boolean {
    const message = (error.response?.data as { message?: string } | undefined)?.message ?? '';
    return message.startsWith('Ya no tiene acceso') || message.startsWith('El hospital indicado no coincide');
}

export interface ApiErrorBody {
    message?: string;
    code?: string;
    errors?: Record<string, string[]>;
    details?: Record<string, unknown>;
}

/** Código de la regla de negocio que rechazó la operación (p. ej. POSIBLE_DUPLICADO) y sus datos. */
export function ruleError(error: unknown): { code: string; message: string; details: Record<string, unknown> } | null {
    if (axios.isAxiosError<ApiErrorBody>(error) && error.response?.data?.code) {
        const body = error.response.data;
        return { code: body.code!, message: body.message ?? '', details: body.details ?? {} };
    }
    return null;
}

/** Mensaje legible de un error de la API (validación, regla de negocio o red). */
export function errorMessage(error: unknown): string {
    if (axios.isAxiosError<ApiErrorBody>(error)) {
        const body = error.response?.data;
        if (body?.errors) return Object.values(body.errors).flat()[0] ?? 'Datos inválidos.';
        if (body?.message) return body.message;
        if (!error.response) return 'No hay conexión con el servidor.';
    }
    return 'Ocurrió un error inesperado.';
}

export function fieldErrors(error: unknown): Record<string, string> {
    if (axios.isAxiosError<ApiErrorBody>(error) && error.response?.data?.errors) {
        return Object.fromEntries(Object.entries(error.response.data.errors).map(([k, v]) => [k, v[0]]));
    }
    return {};
}

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
}
