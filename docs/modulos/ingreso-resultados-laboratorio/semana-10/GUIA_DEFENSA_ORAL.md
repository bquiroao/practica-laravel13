# Defensa oral — Semana 10

Presento cinco pantallas de captura y corrección para 320–430 px, derivadas del diseño UX de semana 8. La tabla se transforma en tarjetas y el formulario usa una columna, contexto mínimo, unidad fija y acción principal al final. La corrección exige estado pendiente, versión vigente y motivo. La confirmación informa que registrar no valida el resultado.

Demostrar M1 → M2 → M4 para captura y M1 → M3 → M4 para corrección. Abrir variantes 320 y 430. Explicar M5: un timeout deja el resultado desconocido; primero se comprueba y luego se reintenta con idempotencia. Ante 409 se recarga y compara; un validado queda inmutable.

Las imágenes son propuestas estáticas. Se verificó estructura SVG, no comportamiento en navegador ni integración. La adaptación CSS, pruebas con teclado/zoom y APIs quedan como criterios explícitos de implementación.