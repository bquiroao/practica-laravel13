// Mismos nombres que config/nido.php (la API los devuelve en /roles; aquí se usan para la barra superior).
const names: Record<string, string> = {
    admin_red: 'Administrador de red',
    admin_hospital: 'Administrador de hospital',
    direccion: 'Dirección médica',
    medico: 'Médico',
    enfermeria: 'Enfermería',
    recepcion: 'Recepción y admisión',
    laboratorio: 'Técnico de laboratorio',
    bioquimico: 'Bioquímico',
    farmacia: 'Farmacia',
    calidad: 'Calidad y QA',
};

export const roleName = (code: string): string => names[code] ?? code;
