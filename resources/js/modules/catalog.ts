import {
    BedDouble, CalendarDays, FileCode2, FlaskConical, Gauge, HeartPulse, House, Database,
    BellRing, ScrollText, ShieldCheck, Smartphone, Stethoscope, UserCog, Users, Pill, BarChart3, BadgeCheck, type LucideIcon,
} from 'lucide-react';

/**
 * Mapa de la plataforma: cada pantalla dice de qué módulos de docs/modulos/ sale y en qué fase se integra.
 * Los 30 módulos aparecen aquí; ninguno queda fuera.
 */
export interface ModuleEntry {
    path: string;
    label: string;
    icon: LucideIcon;
    /** Basta con tener uno de estos permisos para ver la pantalla. */
    anyOf: string[];
    modules: string[];
    phase: number;
    ready: boolean;
    summary: string;
}

export interface Section {
    title: string;
    items: ModuleEntry[];
}

export const sections: Section[] = [
    {
        title: 'General',
        items: [
            { path: '/inicio', label: 'Inicio', icon: House, anyOf: ['dashboard.ver'], modules: ['dashboard-base'], phase: 1, ready: true,
                summary: 'Tablero de inicio según el rol.' },
        ],
    },
    {
        title: 'Atención',
        items: [
            { path: '/pacientes', label: 'Pacientes', icon: Users, anyOf: ['pacientes.ver'], modules: ['pacientes'], phase: 2, ready: false,
                summary: 'Registro, edición, búsqueda por nombre, DPI o código y detalle del paciente.' },
            { path: '/citas', label: 'Citas', icon: CalendarDays, anyOf: ['citas.ver'], modules: ['citas-medicas'], phase: 2, ready: false,
                summary: 'Agenda por médico y especialidad, sin choques de horario.' },
            { path: '/medicos', label: 'Médicos', icon: Stethoscope, anyOf: ['medicos.ver'], modules: ['medicos-especialidades'], phase: 2, ready: false,
                summary: 'Médicos, especialidades y disponibilidad.' },
            { path: '/camas', label: 'Camas', icon: BedDouble, anyOf: ['camas.ver'],
                modules: ['catalogo-salas-camas', 'admision-hospitalaria-asignacion-cama', 'traslados-altas-camas'], phase: 3, ready: false,
                summary: 'Mapa de salas y camas, admisión con asignación de cama, traslados y altas.' },
            { path: '/ocupacion', label: 'Ocupación', icon: Gauge, anyOf: ['ocupacion.ver'], modules: ['dashboard-ocupacion'], phase: 3, ready: false,
                summary: 'Ocupación hospitalaria por sala y servicio.' },
        ],
    },
    {
        title: 'Clínico',
        items: [
            { path: '/expediente', label: 'Expediente', icon: HeartPulse, anyOf: ['expediente.leer'],
                modules: ['expediente-medico', 'notas-soap-diagnosticos', 'signos-vitales-alertas', 'alergias-clinicas-alerta-visual-expediente'],
                phase: 4, ready: false, summary: 'Expediente con notas SOAP, diagnósticos, signos vitales y alergias siempre visibles.' },
            { path: '/prescripciones', label: 'Prescripciones', icon: Pill, anyOf: ['prescripciones.crear', 'medicamentos.ver'],
                modules: ['prescripciones-alergias', 'catalogo-medicamentos'], phase: 4, ready: false,
                summary: 'Recetas electrónicas que se bloquean ante una alergia, y catálogo de medicamentos.' },
            { path: '/alertas', label: 'Alertas', icon: BellRing, anyOf: ['alertas.ver'], modules: ['alertas-criticas-notificaciones-internas'],
                phase: 4, ready: false, summary: 'Alertas críticas con reconocimiento y escalamiento.' },
        ],
    },
    {
        title: 'Laboratorio',
        items: [
            { path: '/laboratorio', label: 'Laboratorio', icon: FlaskConical, anyOf: ['pruebas_lab.ver', 'ordenes_lab.crear'],
                modules: ['catalogo-pruebas-laboratorio', 'ordenes-laboratorio-emr', 'recepcion-muestras', 'ingreso-resultados-laboratorio',
                    'validacion-resultados-bioquimico'], phase: 5, ready: false,
                summary: 'De la orden a la validación: catálogo, órdenes, muestras con código, resultados y firma del bioquímico.' },
        ],
    },
    {
        title: 'Gestión',
        items: [
            { path: '/reportes', label: 'Reportes', icon: BarChart3, anyOf: ['reportes.ver'],
                modules: ['reportes-analytics-basicos', 'reportes-operativos-exportables'], phase: 6, ready: false,
                summary: 'Indicadores, filtros administrativos y exportación a PDF y Excel.' },
            { path: '/calidad', label: 'Calidad', icon: BadgeCheck, anyOf: ['calidad.gestionar', 'versiones.gestionar'],
                modules: ['qa-regresion-defectos', 'qa-e2e-ci-despliegue'], phase: 7, ready: false,
                summary: 'Campañas de regresión, defectos, ejecuciones E2E, quality gate y despliegues.' },
        ],
    },
    {
        title: 'Administración',
        items: [
            { path: '/admin/usuarios', label: 'Usuarios', icon: UserCog, anyOf: ['usuarios.ver'], modules: ['gestion-usuarios-tenants-autenticacion'],
                phase: 1, ready: true, summary: 'Usuarios del hospital, alta y estado.' },
            { path: '/admin/roles', label: 'Roles y permisos', icon: ShieldCheck, anyOf: ['roles.ver'], modules: ['rbac'], phase: 1, ready: true,
                summary: 'Matriz de roles y permisos.' },
            { path: '/admin/auditoria', label: 'Auditoría', icon: ScrollText, anyOf: ['auditoria.consultar'], modules: ['gobernanza-auditoria'],
                phase: 1, ready: true, summary: 'Bitácora encadenada de movimientos.' },
            { path: '/admin/api', label: 'Contratos API', icon: FileCode2, anyOf: ['contratos_api.ver'], modules: ['contratos-api'], phase: 7, ready: false,
                summary: 'Documentación OpenAPI de la plataforma.' },
            { path: '/admin/base-datos', label: 'Base de datos', icon: Database, anyOf: ['base_datos.verificar'], modules: ['verificacion-bd-postgresql'],
                phase: 7, ready: false, summary: 'Estado e integridad de las bases central y del hospital.' },
            { path: '/movil', label: 'App móvil', icon: Smartphone, anyOf: ['movil.usar'], modules: ['investigacion-prototipo-nativephp'], phase: 8,
                ready: false, summary: 'Extensión móvil y de escritorio con NativePHP.' },
        ],
    },
];

export const allEntries: ModuleEntry[] = sections.flatMap((s) => s.items);

