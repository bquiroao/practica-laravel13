// Datos del prototipo (semana 11). Ficticios. Lo que viaja a la API simulada es COPIA de micro-his-prescripciones:
// database/datos-demo.php (pacientes, alergias, catálogo, grupos) y config/config.php (tokens y permisos).
// herramientas/paridad.cjs comprueba que son iguales antes de comparar respuestas.
(function (raiz) {
  'use strict';
  raiz.DATOS = {
    // ---------- copia de datos-demo.php ----------
    pacientes: {
      'hosp-norte': ['P-001', 'P-002', 'P-003', 'P-004', 'P-005', 'P-006', 'P-007'],
      'hosp-sur': ['P-900'],
    },
    // [código, nombre, tipo, severidad, estado, reacción]; P-003 no tiene ningún registro (no evaluado)
    alergias: {
      'P-001': [['J01CA04', 'Amoxicilina', 'allergy', 'severe', 'active', 'urticaria generalizada']],
      'P-002': [[null, 'Penicilina', 'allergy', 'moderate', 'active', 'exantema']],
      'P-004': [['N02BA01', 'Ácido acetilsalicílico', 'allergy', 'life_threatening', 'active', 'anafilaxia']],
      'P-005': [['J01CA04', 'Amoxicilina', 'allergy', 'mild', 'resolved', null]],
      'P-006': [[null, 'penicilina', 'unknown', 'unknown', 'active', null]],
      'P-007': [[null, '  AMOXICILINA ', 'allergy', 'moderate', 'active', 'prurito']],
    },
    // [código, nombre, principio activo (código), principio activo, grupo, unidad, dosis máxima diaria, vías, activo]
    medicamentos: [
      ['AMOX500', 'Amoxicilina 500 mg', 'J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000.0, ['oral'], true],
      ['CEFA500', 'Cefalexina 500 mg', 'J01DB01', 'Cefalexina', 'betalactamicos', 'mg', 4000.0, ['oral'], true],
      ['PARA500', 'Paracetamol 500 mg', 'N02BE01', 'Paracetamol', 'anilidas', 'mg', 4000.0, ['oral', 'rectal'], true],
      ['IBU400', 'Ibuprofeno 400 mg', 'M01AE01', 'Ibuprofeno', 'aines', 'mg', 2400.0, ['oral'], true],
      ['RANI150', 'Ranitidina 150 mg', 'A02BA02', 'Ranitidina', 'antih2', 'mg', 300.0, ['oral'], false],
    ],
    grupos: { j01ca04: 'betalactamicos', j01ce01: 'betalactamicos', penicilina: 'betalactamicos', n02ba01: 'aines', m01ae01: 'aines' },

    // ---------- copia de config.php ----------
    tokens: {
      'token-dr-lopez': ['dr-lopez', 'hosp-norte'],
      'token-dr-ruiz': ['dr-ruiz', 'hosp-norte'],
      'token-farmacia-norte': ['farm-norte', 'hosp-norte'],
      'token-dr-sur': ['dr-sur', 'hosp-sur'],
    },
    permisos: {
      'dr-lopez|hosp-norte': ['prescripcion.emitir', 'prescripcion.override', 'prescripcion.consultar', 'prescripcion.anular'],
      'dr-ruiz|hosp-norte': ['prescripcion.emitir', 'prescripcion.consultar', 'prescripcion.anular'],
      'farm-norte|hosp-norte': ['prescripcion.consultar'],
      'dr-sur|hosp-sur': ['prescripcion.emitir', 'prescripcion.override', 'prescripcion.consultar', 'prescripcion.anular'],
    },

    // ---------- solo de la interfaz (la API no los conoce) ----------
    personas: {
      'dr-lopez': { nombre: 'Dr. López', rol: 'Médico', detalle: 'Puede autorizar advertencias', iniciales: 'DL', token: 'token-dr-lopez', hospital: 'hosp-norte' },
      'dr-ruiz': { nombre: 'Dra. Ruiz', rol: 'Médica', detalle: 'Sin permiso para autorizar advertencias', iniciales: 'DR', token: 'token-dr-ruiz', hospital: 'hosp-norte' },
      'farm-norte': { nombre: 'Farmacia Norte', rol: 'Farmacia', detalle: 'Solo consulta', iniciales: 'FN', token: 'token-farmacia-norte', hospital: 'hosp-norte' },
    },
    hospitales: { 'hosp-norte': 'Hospital Regional del Norte', 'hosp-sur': 'Hospital del Sur' },
    cortos: { 'hosp-norte': 'H. R. Norte', 'hosp-sur': 'H. Sur' },
    nombresPacientes: { 'P-001': 'Jorge A.', 'P-002': 'María E.', 'P-003': 'Luis R.', 'P-004': 'Ana G.', 'P-005': 'Pedro M.', 'P-006': 'Rosa T.', 'P-007': 'Carlos V.' },
    // Glosario único de severidad (semana 9, B-09): las mismas palabras en todas las pantallas
    severidades: { mild: 'leve', moderate: 'moderada', severe: 'grave', life_threatening: 'potencialmente mortal', unknown: 'desconocida' },
    // La alergia que «enfermería registra en el Expediente» durante el error crítico
    alergiaNueva: { paciente: 'P-005', alergia: ['J01CA04', 'Amoxicilina', 'allergy', 'severe', 'active', 'urticaria generalizada'] },
  };
})(typeof window !== 'undefined' ? window : globalThis);
