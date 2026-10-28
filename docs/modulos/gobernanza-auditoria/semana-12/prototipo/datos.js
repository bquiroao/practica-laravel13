// Datos del prototipo de Auditoría (semana 11). Todo es ficticio. Las personas, tokens y permisos son los de
// micro-his-auditoria/config/config.php; la semilla se registra en el MISMO orden en la API simulada y en el
// kernel real (herramientas/paridad.php), así los ids coinciden en los dos.
(function (raiz) {
  const GT = -6 * 60; // America/Guatemala: UTC-6 todo el año (sin horario de verano)
  const dosDig = (n) => String(n).padStart(2, '0');
  /** Fecha y hora local de Guatemala en formato ATOM, «minutos» antes de «ahora» (ms). */
  const local = (ahora, minutosAntes) => {
    const d = new Date(ahora - minutosAntes * 60000 + GT * 60000);
    return `${d.getUTCFullYear()}-${dosDig(d.getUTCMonth() + 1)}-${dosDig(d.getUTCDate())}T${dosDig(d.getUTCHours())}:${dosDig(d.getUTCMinutes())}:${dosDig(d.getUTCSeconds())}-06:00`;
  };
  const hoyLocal = (ahora) => local(ahora, 0).slice(0, 10);

  /** Registros de la semilla: lo que los módulos emisores ya enviaron hoy. */
  function semilla(ahora) {
    const regs = [];
    const usuarios = ['u-ana', 'u-luis', 'u-marta', 'u-jose', 'u-sofia', 'u-pedro'];
    // 1–52: consultas rutinarias de fichas (más de una página de 50)
    for (let i = 1; i <= 52; i++) {
      regs.push({ token: 'token-svc-pacientes-oriente', tenant: 'hosp-oriente', cuerpo: {
        event_id: `rut-${i}`, hospital: 'hosp-oriente', usuario: usuarios[i % 6], modulo: 'pacientes', accion: 'PACIENTE_CONSULTADO',
        ocurrido_en: local(ahora, 200 - i), resultado: 'exito', recurso: `paciente:P-${String(200 + i).padStart(6, '0')}` } });
    }
    const p = (event_id, usuario, accion, minutos, resultado, recurso, extra = {}) => ({ token: 'token-svc-pacientes-oriente', tenant: 'hosp-oriente',
      cuerpo: { event_id, hospital: 'hosp-oriente', usuario, modulo: 'pacientes', accion, ocurrido_en: local(ahora, minutos), resultado, recurso, ...extra } });
    regs.push(p('ev-reg', 'u-ana', 'PACIENTE_REGISTRADO', 60, 'exito', 'paciente:P-000123'));             // 53
    regs.push(p('ev-cons', 'u-luis', 'PACIENTE_CONSULTADO', 55, 'exito', 'paciente:P-000001'));           // 54
    regs.push(p('c-1', 'u-equivocado', 'PACIENTE_CONSULTADO', 50, 'exito', 'paciente:P-000001'));          // 55: atribuido mal
    regs.push(p('c-2', 'u-correcto', 'PACIENTE_CONSULTADO', 45, 'exito', 'paciente:P-000001', {          // 56: su corrección (semana 6)
      corrige_evento: 'c-1', motivo_correccion: 'El integrador atribuyó la consulta al usuario equivocado' }));
    regs.push(p('ev-den-1', 'u-ana', 'PACIENTE_ACTUALIZADO', 40, 'denegado', 'paciente:P-000007'));       // 57
    regs.push({ token: 'token-svc-rbac-oriente', tenant: 'hosp-oriente', cuerpo: {                          // 58
      event_id: 'ev-rol', hospital: 'hosp-oriente', usuario: 'u-admin', modulo: 'rbac', accion: 'ROL_ASIGNADO',
      ocurrido_en: local(ahora, 35), resultado: 'exito', recurso: 'usuario:U-0042' } });
    regs.push(p('ev-den-2', 'u-pedro', 'PACIENTE_ACTUALIZADO', 30, 'denegado', 'paciente:P-000031'));     // 59
    // 60–63: la cadena de Norte. La 61 (secuencia 2) la «borra alguien directo en la base»: el error crítico.
    [['n-1', 'u-rosa', 25], ['n-2', 'u-rosa', 20], ['n-3', 'u-tomas', 15], ['n-4', 'u-tomas', 10]].forEach(([e, u, m]) =>
      regs.push({ token: 'token-svc-pacientes-norte', tenant: 'hosp-norte', cuerpo: {
        event_id: e, hospital: 'hosp-norte', usuario: u, modulo: 'pacientes', accion: 'PACIENTE_CONSULTADO',
        ocurrido_en: local(ahora, m), resultado: 'exito', recurso: 'paciente:P-000900' } }));
    return regs;
  }

  raiz.DATOS = {
    semilla,
    hoyLocal,
    local,
    // Lo que «alguien» borró directo en la base, saltándose el trigger (solo para demostrar ALTERADA)
    borrado: { emisor: 'svc-pacientes', event_id: 'n-2' },
    hospitales: { 'hosp-oriente': 'Hospital Regional de Oriente', 'hosp-norte': 'Hospital Regional del Norte' },
    cortos: { 'hosp-oriente': 'H. R. Oriente', 'hosp-norte': 'H. R. Norte' },
    tokens: {
      'token-svc-pacientes-oriente': ['svc-pacientes', 'hosp-oriente'], 'token-svc-pacientes-norte': ['svc-pacientes', 'hosp-norte'],
      'token-svc-rbac-oriente': ['svc-rbac', 'hosp-oriente'], 'token-auditor-oriente': ['auditor-oriente', 'hosp-oriente'],
      'token-superadmin': ['superadmin', '*'],
    },
    permisos: {
      'auditor-oriente|hosp-oriente': ['auditoria.consultar'],
      'superadmin|hosp-oriente': ['auditoria.consultar'], 'superadmin|hosp-norte': ['auditoria.consultar'],
      'superadmin|*': ['auditoria.consultar_red', 'auditoria.verificar'],
    },
    personas: {
      'auditor-oriente': { nombre: 'Auditor de Oriente', rol: 'Auditor', iniciales: 'AO', token: 'token-auditor-oriente', hospital: 'hosp-oriente' },
      superadmin: { nombre: 'Super administrador', rol: 'Super admin', iniciales: 'SA', token: 'token-superadmin', hospital: 'hosp-oriente' },
    },
    // Etiquetas legibles de las acciones (semana 9, B-13). El código sigue siendo el dato.
    acciones: {
      PACIENTE_REGISTRADO: 'Registro de paciente', PACIENTE_ACTUALIZADO: 'Edición de paciente', PACIENTE_CONSULTADO: 'Consulta de paciente',
      PACIENTE_BUSCADO: 'Búsqueda de pacientes', ROL_ASIGNADO: 'Asignación de rol', ROL_REVOCADO: 'Revocación de rol',
      ACCESO_DENEGADO: 'Acceso denegado', AUDITORIA_CONSULTADA: 'Consulta de la bitácora', INTEGRIDAD_VERIFICADA: 'Verificación de integridad',
    },
    contactoSeguridad: 'seguridad@red-his.example',
  };
})(typeof window !== 'undefined' ? window : globalThis);
