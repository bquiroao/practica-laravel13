/** Datos de la sesión que devuelve /auth/login y /auth/me. */
export interface Session {
    user: { id: string; name: string; email: string };
    hospital: { id: string; code: string; name: string; timezone: string };
    roles: string[];
    permissions: string[];
}

export interface TokenResponse {
    access_token: string;
    token_type: 'bearer';
    expires_in: number;
    session?: Session;
}

const TOKEN = 'nido.token';
const HOSPITAL = 'nido.hospital';
const EXPIRES = 'nido.expires';

// sessionStorage: el token muere al cerrar la pestaña (equipo compartido en el hospital).
function read(key: string): string | null {
    try {
        return sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

function write(key: string, value: string | null): void {
    try {
        if (value === null) sessionStorage.removeItem(key);
        else sessionStorage.setItem(key, value);
    } catch {
        /* almacenamiento no disponible: la sesión dura lo que la página */
    }
}

export const session = {
    token: () => read(TOKEN),
    hospitalId: () => read(HOSPITAL),
    expiresAt: () => Number(read(EXPIRES) ?? 0),
    store(token: TokenResponse, hospitalId?: string) {
        write(TOKEN, token.access_token);
        write(EXPIRES, String(Date.now() + token.expires_in * 1000));
        if (hospitalId) write(HOSPITAL, hospitalId);
    },
    setHospital: (id: string) => write(HOSPITAL, id),
    clear() {
        write(TOKEN, null);
        write(EXPIRES, null);
    },
};
