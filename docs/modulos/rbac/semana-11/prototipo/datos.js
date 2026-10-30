// Fuente ÚNICA de datos, nombres, estados y mensajes del prototipo (semana 11).
// Escritorio y móvil usan este mismo archivo: así un rol o un estado no puede llamarse distinto en dos pantallas.
// La semilla es copia de micro-his-rbac/database/datos-demo.php (mismos usuarios, roles, permisos y membresías).
(function (raiz) {
  const DATOS = {
    ahora: '2026-11-15T10:00:00-06:00', // mismo instante fijo que usan las pruebas del Micro-HIS
    hospitales: {
      'hosp-oriente': { nombre: 'Hospital Regional de Oriente', corto: 'H. R. Oriente' },
      'hosp-norte': { nombre: 'Hospital del Norte', corto: 'H. del Norte' },
    },
    semilla: {
      usuarios: {
        'admin-01': 'active', 'admin-02': 'active', ana: 'active', marta: 'active',
        pedro: 'active', lucia: 'active', 'nuevo-01': 'active', jorge: 'inactive',
      },
      permisosPorRol: {
        admin_hospital: ['roles.asignar', 'roles.revocar', 'auditoria.consultar'],
        medico: ['prescripciones.crear', 'signos_vitales.registrar', 'expediente.leer'],
        enfermeria: ['signos_vitales.registrar', 'expediente.leer'],
        laboratorio: ['resultados.registrar'],
      },
      // [usuario, hospital, rol, estado, valido_desde, valido_hasta]
      membresias: [
        ['admin-01', 'hosp-oriente', 'admin_hospital', 'active', '2026-01-01T00:00:00-06:00', null],
        ['admin-02', 'hosp-norte', 'admin_hospital', 'active', '2026-01-01T00:00:00-06:00', null],
        ['ana', 'hosp-oriente', 'enfermeria', 'active', '2026-01-01T00:00:00-06:00', null],
        ['marta', 'hosp-oriente', 'medico', 'active', '2026-02-01T00:00:00-06:00', null],
        ['pedro', 'hosp-oriente', 'medico', 'active', '2026-01-01T00:00:00-06:00', '2026-06-30T00:00:00-06:00'],
        ['lucia', 'hosp-oriente', 'enfermeria', 'revoked', '2026-01-01T00:00:00-06:00', null],
      ],
      // Tokens de demostración: los mismos de tests/Dobles/Escenario.php, más uno para nuevo-01.
      tokens: {
        'token-admin-oriente': ['admin-01', 'hosp-oriente'],
        'token-admin-norte': ['admin-02', 'hosp-norte'],
        'token-ana': ['ana', 'hosp-oriente'],
        'token-marta': ['marta', 'hosp-oriente'],
        'token-nuevo-01': ['nuevo-01', 'hosp-oriente'],
      },
    },

    // Nombres para mostrar (brecha B-3: la API real solo devuelve identificadores). Ficticios.
    // Colores de avatar con texto blanco ≥ 4.5:1 (axe encontró #2E8B87 = 4.07 y #B5651D = 4.34; se oscurecieron).
    personas: {
      'admin-01': { nombre: 'Carlos Méndez', servicio: 'Administración', iniciales: 'CM', color: '#5B7083', token: 'token-admin-oriente' },
      'admin-02': { nombre: 'Sofía Ruiz', servicio: 'Administración', iniciales: 'SR', color: '#5B7083', token: 'token-admin-norte' },
      ana: { nombre: 'Ana Pérez', servicio: 'Enfermería, piso 3', iniciales: 'AP', color: '#237370', token: 'token-ana' },
      marta: { nombre: 'Marta Solís', servicio: 'Medicina interna', iniciales: 'MS', color: '#7A5AA6', token: 'token-marta' },
      pedro: { nombre: 'Pedro Lima', servicio: 'Medicina interna', iniciales: 'PL', color: '#7A5AA6' },
      lucia: { nombre: 'Lucía Ortiz', servicio: 'Enfermería, piso 2', iniciales: 'LO', color: '#237370' },
      'nuevo-01': { nombre: 'Luis García', servicio: 'Enfermería de cobertura', iniciales: 'LG', color: '#9A5518', token: 'token-nuevo-01' },
      jorge: { nombre: 'Jorge Vela', servicio: 'Sin servicio (cuenta inactiva)', iniciales: 'JV', color: '#5B7083' },
    },

    // Un solo nombre por rol, en todas las pantallas.
    roles: {
      admin_hospital: 'Administración',
      medico: 'Médico',
      enfermeria: 'Enfermería',
      laboratorio: 'Laboratorio',
    },
    // Un solo texto por permiso, en lenguaje claro.
    permisos: {
      'roles.asignar': 'asignar roles',
      'roles.revocar': 'revocar roles',
      'auditoria.consultar': 'consultar la auditoría',
      'prescripciones.crear': 'crear prescripciones',
      'signos_vitales.registrar': 'registrar signos vitales',
      'expediente.leer': 'leer el expediente',
      'resultados.registrar': 'registrar resultados de laboratorio',
    },
    // Estados de membresía: icono + texto (WCAG 1.4.1), mismos en tabla y tarjeta.
    estados: {
      activo: { texto: 'Activo', icono: '●', clase: 'activo' },
      vencido: { texto: 'Vencido', icono: '◌', clase: 'vencido' },
      revocado: { texto: 'Revocado', icono: '⊘', clase: 'revocado' },
      futuro: { texto: 'Aún no vigente', icono: '◔', clase: 'vencido' },
    },
    // Motivos de denegación: solo los ve quien tiene auditoria.consultar (R-08).
    motivos: {
      MEMBRESIA_NO_VIGENTE: ['Rol no vigente', 'La persona no tiene un rol vigente en este hospital (vencido o revocado).'],
      PERMISO_NO_ASIGNADO: ['Permiso no incluido', 'Ningún rol vigente de la persona incluye este permiso.'],
      TENANT_NO_COINCIDE: ['Hospital distinto', 'La sesión es de un hospital distinto al de la solicitud.'],
      SIN_REGLAS_CONFIGURADAS: ['No se pudo evaluar', 'El sistema no pudo evaluar y denegó por seguridad.'],
      ERROR_EVALUACION: ['No se pudo evaluar', 'El sistema no pudo evaluar y denegó por seguridad.'],
    },
    // Catálogo de mensajes M-xx de la semana 8 (con los ajustes de las semanas 9 y 10).
    mensajes: {
      'M-01': 'Completa este campo.',
      'M-02': 'No encontramos a esa persona en este hospital.',
      'M-03': 'Esta persona ya tiene ese rol activo.',
      'M-04': 'La fecha de fin debe ser posterior al inicio.',
      'M-05': 'Esta cuenta está inactiva; no se le pueden asignar roles.',
      'M-06': 'Escribe al menos 10 caracteres.',
      'M-07': 'Este rol ya no está activo. Actualizamos la lista.',
      'M-08': 'No puedes revocar tus propios roles. Pide a otro administrador.',
      'M-09': 'No puedes asignarte roles a ti mismo.',
      'M-10': 'Acceso denegado',
      'M-11': 'Si necesitas este acceso, comparte esta referencia con tu administrador.',
      'M-12': 'No pudimos completar la operación. Inténtalo de nuevo.',
      'M-13': 'No pudimos verificar tus permisos. Las acciones quedan deshabilitadas.',
      'M-20': 'Rol asignado',
      'M-21': 'Rol revocado',
      'M-30': 'Usa solo letras, números y guiones (máx. 64).',
      'M-31': 'No hay un evento con esa referencia en este hospital.',
    },
  };

  if (typeof module !== 'undefined' && module.exports) module.exports = DATOS;
  else raiz.DATOS = DATOS;
})(typeof window !== 'undefined' ? window : globalThis);
