# Presentación breve — Semana 12

## 1 · Alcance (40 s)
Orden desde expediente/episodio y seguimiento. Emisor, consultor y origen externo autorizado tienen responsabilidades diferentes. No muestras ni resultados. Datos ficticios y estado sintético.

## 2 · Componentes (50 s)
UI presenta contexto; emisión/consulta/actualización son casos de uso separados; autorización, catálogo y ciclo se inyectan; persistencia guarda unidad completa. Demo web representa experiencia, no infraestructura clínica.

## 3 · UX y movilidad (50 s)
Contexto de lectura, selección mínima, revisar antes de emitir y éxito confirmado. Desktop lateral/móvil columna. Foco, etiquetas y errores textuales; lector de pantalla pendiente de prueba.

## 4 · Recorrido (100 s)
Seleccionar dos pruebas y emitir. Consultor solo lectura. Reiniciar y simular catálogo indisponible/falla: ninguna orden parcial. Simular respuesta perdida y comprobar operación sin duplicar. Mostrar rol sin acceso.

## 5 · Cambio y riesgos (60 s)
CH-01 es un ejemplo simulado: no reenviar ante respuesta desconocida. El backend debe implementar comprobación y contrato idempotente. Faltan permisos/tenant reales, ciclo acordado y persistencia; no afirmar que la demo los implementa.