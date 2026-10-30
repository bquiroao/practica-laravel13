// API simulada del Micro-HIS de Médicos (semana 11): mismas reglas, códigos y estados que el servidor.
// Lo marcado «propuesto» (503 por Citas, simulación de ausencia) está en el contrato de la semana 5 pero no en el código aún.
window.ApiSimulada = (() => {
  const HOY = '2026-10-28', RETARDO = 350;
  const db = {
    usuarios: { 'u-ana': 'Dra. Ana Pérez', 'u-luis': 'Dr. Luis Gómez', 'u-sofia': 'Dra. Sofía Ruiz' },
    especialidades: { 'esp-mi': { codigo: 'MI', nombre: 'Medicina interna', activa: true }, 'esp-ped': { codigo: 'PED', nombre: 'Pediatría', activa: true }, 'esp-car': { codigo: 'CAR', nombre: 'Cardiología', activa: false } },
    medicos: [{ id: 'med-ana', usuario: 'u-ana', colegiado: '11111', asig: [{ esp: 'esp-mi', desde: '2026-01-01', hasta: null }] },
              { id: 'med-luis', usuario: 'u-luis', colegiado: '22222', asig: [{ esp: 'esp-mi', desde: '2026-01-01', hasta: null }] }],
    horarios: { 'med-ana': { 1: [['08:00', '10:00']], 2: [['09:00', '10:00']] }, 'med-luis': { 2: [['10:00', '11:00']] }, 'med-sofia': { 1: [['08:00', '09:00']] } },   // los carga otro proceso: el módulo aún no los escribe
    citas: { 'med-ana': ['2026-11-02 08:00', '2026-11-03 09:30'] }, ausencias: [], simular: { citasCaida: false, sinConexion: false },
  };
  const fin = (inicio) => { const [f, h] = inicio.split(' '); const m = +h.slice(0, 2) * 60 + +h.slice(3) + 30; return `${f} ${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`; };
  const fechaOk = (f) => /^\d{4}-\d{2}-\d{2}$/.test(f || '') && !isNaN(Date.parse(f));
  const dias = (a, b) => (Date.parse(b) - Date.parse(a)) / 864e5;
  const dow = (f) => new Date(f + 'T00:00:00Z').getUTCDay() || 7;
  const sumar = (f, n) => new Date(Date.parse(f) + n * 864e5).toISOString().slice(0, 10);
  const r = (estado, cuerpo) => ({ estado, cuerpo });
  const err = (estado, codigo, mensaje) => r(estado, { codigo, mensaje });
  const vigente = (a, f) => a.desde <= f && (!a.hasta || f < a.hasta);
  const cruza = (a, b) => a.desde < (b.hasta || '9999-12-31') && b.desde < (a.hasta || '9999-12-31');
  const llamar = (rol, permitidos, f) => new Promise((ok, mal) => setTimeout(() => {
    if (db.simular.sinConexion) { return mal(new Error('sin conexión')); }
    ok(permitidos.includes(rol) ? f() : err(403, 'acceso_denegado', 'Su rol no puede hacer esta operación'));
  }, RETARDO));
  const nuevoId = (p) => `${p}-${Math.random().toString(36).slice(2, 8)}`;
  const nombre = (m) => db.usuarios[m.usuario];
  return {
    db, HOY, miMedico: () => 'med-ana',   // el médico que inició sesión (demostración)
    
    usuariosLibres: () => Object.entries(db.usuarios).filter(([u]) => !db.medicos.some((m) => m.usuario === u)),
    listarMedicos: () => db.medicos.map((m) => ({ id: m.id, nombre: nombre(m), colegiado: m.colegiado, especialidades: m.asig.map((a) => db.especialidades[a.esp].nombre) })),
    registrarMedico: (rol, e) => llamar(rol, ['admin'], () => {
      const colegiado = (e.colegiado || '').trim();
      if (!colegiado || !db.usuarios[e.usuario_id]) { return err(409, 'usuario_no_disponible', 'Falta el colegiado, o el usuario no existe o no está activo'); }
      if (db.medicos.some((m) => m.colegiado === colegiado || m.usuario === e.usuario_id)) { return err(409, 'medico_duplicado', 'Ese colegiado o ese usuario ya tienen perfil de médico'); }
      const esp = db.especialidades[e.especialidad_id];
      if (e.especialidad_id && !(esp && esp.activa)) { return err(422, 'especialidad_invalida', 'La especialidad no existe o no está activa'); }
      const id = 'med-' + e.usuario_id.slice(2);
      db.medicos.push({ id, usuario: e.usuario_id, colegiado, asig: e.especialidad_id ? [{ esp: e.especialidad_id, desde: e.desde || HOY, hasta: null }] : [] });
      return r(201, { medico_id: id, especialidad_principal: e.especialidad_id || null });
    }),
    asociarEspecialidad: (rol, id, e) => llamar(rol, ['admin'], () => {
      const m = db.medicos.find((x) => x.id === id), esp = db.especialidades[e.especialidad_id];
      if (!m) { return err(404, 'medico_no_encontrado', 'El médico no existe o no está activo'); }
      if (!(esp && esp.activa)) { return err(422, 'especialidad_invalida', 'La especialidad no existe o no está activa'); }
      if (!fechaOk(e.desde) || (e.hasta && (!fechaOk(e.hasta) || e.hasta <= e.desde))) { return err(422, 'vigencia_invalida', 'Vigencia con fechas inválidas'); }
      const nueva = { esp: e.especialidad_id, desde: e.desde, hasta: e.hasta || null };
      if (m.asig.some((a) => a.esp === nueva.esp && cruza(a, nueva))) { return err(409, 'especialidad_duplicada', 'Ya tiene esa especialidad en esas fechas'); }
      m.asig.push(nueva); return r(201, { asignacion_id: nuevoId('asg') });
    }),
    disponibilidad: (rol, codigo, desde, hasta) => llamar(rol, ['admin', 'recepcion', 'medico'], () => {
      if (!fechaOk(desde) || !fechaOk(hasta) || desde < HOY || hasta < desde || dias(desde, hasta) > 14) { return err(422, 'rango_invalido', 'El rango debe empezar hoy o después y durar como máximo 14 días'); }
      if (db.simular.citasCaida) { return err(503, 'citas_no_disponible', 'Citas no respondió; no se muestran franjas que podrían estar ocupadas'); }   // propuesto (CA-03)
      const franjas = [];
      for (let f = desde; f <= hasta; f = sumar(f, 1)) {
        for (const m of db.medicos) {
          if (!m.asig.some((a) => db.especialidades[a.esp].codigo === codigo && db.especialidades[a.esp].activa && vigente(a, f))) { continue; }
          if (db.ausencias.some((a) => a.medico === m.id && f >= a.desde && f < a.hasta)) { continue; }
          for (const [ini, fn] of (db.horarios[m.id] || {})[dow(f)] || []) {
            for (let t = ini; t < fn; t = fin(`${f} ${t}`).slice(11)) {
              const a = `${f} ${t}`;
              if (!(db.citas[m.id] || []).some((c) => c < fin(a) && a < fin(c))) { franjas.push({ medico_id: m.id, medico: nombre(m), inicio: a }); }
            }
          }
        }
      }
      return r(200, { franjas, total: franjas.length });
    }),
    registrarAusencia: (rol, id, e, simular = false) => llamar(rol, ['admin', 'medico'], () => {
      if (rol === 'medico' && id !== 'med-ana') { return err(403, 'acceso_denegado', 'Solo puede registrar sus propias ausencias'); }   // cambio de la semana 12
      const m = db.medicos.find((x) => x.id === id);
      if (!m) { return err(404, 'medico_no_encontrado', 'El médico no existe o no está activo'); }
      if (!fechaOk(e.desde) || !fechaOk(e.hasta) || e.hasta <= e.desde) { return err(422, 'vigencia_invalida', 'La ausencia necesita fecha de fin posterior'); }
      const conflictos = (db.citas[id] || []).filter((c) => c.slice(0, 10) >= e.desde && c.slice(0, 10) < e.hasta);
      if (simular) { return r(200, { citas_en_conflicto: conflictos }); }   // propuesto: simulación sin guardar (la pide la pantalla antes de confirmar)
      db.ausencias.push({ medico: id, desde: e.desde, hasta: e.hasta, motivo: e.motivo || 'sin motivo' });
      return r(201, { ausencia_id: nuevoId('aus'), citas_en_conflicto: conflictos });
    }),
  };
})();
