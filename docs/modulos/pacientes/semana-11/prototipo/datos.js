// Datos ficticios del prototipo navegable de Pacientes (semana 11).
// Tokens y permisos: los mismos de micro-his-pacientes/config/config.php.
// Semilla: los pacientes de database/datos-demo.php más Sofía, registrada como protegida por supervisión.
(function (raiz) {
  const basicos = ['pacientes.registrar', 'pacientes.buscar', 'pacientes.leer', 'pacientes.editar'];
  raiz.DATOS = {
    hoy: '2026-10-21', // el mismo RelojFijo de las pruebas del Micro-HIS
    hospitales: { 'hosp-oriente': 'Hospital Regional de Oriente', 'hosp-norte': 'Hospital del Norte' },
    tokens: {
      'token-admision-oriente': ['admision-01', 'hosp-oriente'],
      'token-clinico-oriente': ['clinico-01', 'hosp-oriente'],
      'token-supervisor-oriente': ['supervisor-01', 'hosp-oriente'],
      'token-admision-norte': ['admision-02', 'hosp-norte'],
    },
    permisos: {
      'admision-01|hosp-oriente': basicos,
      'clinico-01|hosp-oriente': ['pacientes.buscar', 'pacientes.leer'],
      'supervisor-01|hosp-oriente': [...basicos, 'pacientes.confirmar_duplicado', 'pacientes.editar_restringido',
        'pacientes.proteger', 'pacientes.leer_protegido'],
      'admision-02|hosp-norte': basicos,
    },
    // Quién puede ingresar al prototipo (solo para mostrar nombre y rol en la cabecera)
    personas: {
      'admision-01': { nombre: 'Marta López', rol: 'Admisión', iniciales: 'ML', token: 'token-admision-oriente', color: '#1F6B66' },
      'clinico-01': { nombre: 'Luis Ortiz', rol: 'Enfermería', iniciales: 'LO', token: 'token-clinico-oriente', color: '#5B7083' },
      'supervisor-01': { nombre: 'Rosa Díaz', rol: 'Supervisión', iniciales: 'RD', token: 'token-supervisor-oriente', color: '#7A5AA6' },
    },
    // [token, cuerpo del POST]: se registran al iniciar, en este orden (P-000001…P-000005)
    semilla: [
      ['token-admision-oriente', { nombres: 'Ana Lucía', apellidos: 'López Pérez', fecha_nacimiento: '1985-03-14', sexo: 'female', telefono: '+502 5555 1234', documento: { tipo: 'DPI', numero: '2547896320101' } }],
      ['token-admision-oriente', { nombres: 'Luis Fernando', apellidos: 'García Ruiz', fecha_nacimiento: '1978-11-02', sexo: 'male', documento: { tipo: 'DPI', numero: '1876543210901' } }],
      ['token-admision-oriente', { nombres: 'Juan', apellidos: 'Desconocido', fecha_nacimiento: '2000-01-01', sexo: 'male' }],
      ['token-admision-norte', { nombres: 'María José', apellidos: 'Pérez Cano', fecha_nacimiento: '1992-07-30', sexo: 'female', documento: { tipo: 'PASAPORTE', numero: 'X1234567', pais: 'MEX' } }],
      ['token-supervisor-oriente', { nombres: 'Sofía', apellidos: 'Morales Díaz', fecha_nacimiento: '1994-08-09', sexo: 'female', telefono: '+502 5123 4567', documento: { tipo: 'DPI', numero: '2233445560101' }, identidad_protegida: true, motivo: 'Orden judicial de protección' }],
    ],
  };
})(typeof window !== 'undefined' ? window : globalThis);
