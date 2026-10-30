// Datos FICTICIOS del prototipo (mismos códigos y fechas que el Micro-HIS). No es información clínica.
window.CATALOGO_DATOS = {
  hoy: '2026-10-15',
  etiquetas: {
    formas: { TABLETA: 'Tableta', CAPSULA: 'Cápsula', SUSPENSION: 'Suspensión', SOLUCION_INYECTABLE: 'Solución inyectable', JARABE: 'Jarabe', CREMA: 'Crema' },
    vias: { ORAL: 'Oral', INTRAVENOSA: 'Intravenosa', INTRAMUSCULAR: 'Intramuscular', TOPICA: 'Tópica' },
    unidades: ['mg', 'g', 'mcg', 'UI', 'mL', '%', 'mg/mL', 'mg/5mL'],
  },
  usados: ['MED-000101'], // usados en prescripciones ficticias (RN-02)
  versiones: [
    { codigo: 'MED-000101', version: 1, nombre: 'Paracetamol 500 mg tableta', principio: 'Paracetamol', valor: '500', unidad: 'mg', forma: 'TABLETA', via: 'ORAL', presentacion: 'Caja con 100 tabletas', desde: '2026-01-01', hasta: null, motivo: null, creadoPor: 'carga.ejemplo' },
    { codigo: 'MED-000102', version: 1, nombre: 'Amoxicilina 500 mg cápsula', principio: 'Amoxicilina', valor: '500', unidad: 'mg', forma: 'CAPSULA', via: 'ORAL', presentacion: 'Frasco con 21 cápsulas', desde: '2026-01-01', hasta: null, motivo: null, creadoPor: 'carga.ejemplo' },
    { codigo: 'MED-000103', version: 1, nombre: 'Amoxicilina 250 mg/5 mL suspensión', principio: 'Amoxicilina', valor: '250', unidad: 'mg/5mL', forma: 'SUSPENSION', via: 'ORAL', presentacion: 'Frasco de 100 mL', desde: '2026-01-01', hasta: null, motivo: null, creadoPor: 'carga.ejemplo' },
    { codigo: 'MED-000104', version: 1, nombre: 'Ibuprofeno 400 mg tableta', principio: 'Ibuprofeno', valor: '400', unidad: 'mg', forma: 'TABLETA', via: 'ORAL', presentacion: 'Caja con 20 tabletas', desde: '2026-01-01', hasta: '2026-07-01', motivo: 'Reemplazada por la versión 2', creadoPor: 'carga.ejemplo' },
    { codigo: 'MED-000104', version: 2, nombre: 'Ibuprofeno 400 mg tableta', principio: 'Ibuprofeno', valor: '400', unidad: 'mg', forma: 'TABLETA', via: 'ORAL', presentacion: 'Caja con 30 tabletas', desde: '2026-07-01', hasta: null, motivo: null, creadoPor: 'carga.ejemplo' },
    { codigo: 'MED-000105', version: 1, nombre: 'Omeprazol 20 mg cápsula', principio: 'Omeprazol', valor: '20', unidad: 'mg', forma: 'CAPSULA', via: 'ORAL', presentacion: 'Caja con 14 cápsulas', desde: '2026-01-01', hasta: '2026-09-01', motivo: 'Sustituido en el formulario (dato ficticio)', creadoPor: 'carga.ejemplo' },
    { codigo: 'MED-000106', version: 1, nombre: 'Cloruro de sodio 0,9 % solución inyectable', principio: 'Cloruro de sodio', valor: '0.9', unidad: '%', forma: 'SOLUCION_INYECTABLE', via: 'INTRAVENOSA', presentacion: 'Bolsa de 500 mL', desde: '2026-01-01', hasta: null, motivo: null, creadoPor: 'carga.ejemplo' },
  ],
};
