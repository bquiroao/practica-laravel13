# Recorrido navegable · Prototipo de la semana 11

Abrir `../semana-11/prototipo/index.html`. Cada paso indica qué se hace, qué se ve y qué decisión demuestra.

| # | Acción | Resultado esperado | Demuestra |
|---|---|---|---|
| 1 | Rol «Jefe de área», reporte «Ocupación de camas», «Octubre 2026», Calcular | «Calculando…» y luego 78,0 % con 39 de 50 camas | Estado de carga y precisión única |
| 2 | Abrir la ficha de trazabilidad | Fórmula, período, filtros, fuente y hora | RN-05 |
| 3 | «¿De dónde sale?» | Medicina 18, Cirugía 14, Pediatría 7 y «La suma coincide con el total» | RN-07 |
| 4 | Abrir Medicina | 18 registros con cama y código de paciente; «Cargar más» | RN-09 y paginación |
| 5 | Usar las migas para volver a «Desglose» | Vuelve sin repetir la consulta | H-05 |
| 6 | Recorrer la tabla solo con Tab | Foco visible en cada botón | WCAG 2.4.7 |
| 7 | Poner «Desde» 31/10/2026 y «Hasta» 01/10/2026 | Mensaje junto al campo antes de enviar | RN-02, H-06 |
| 8 | Marcar «Simular caída de la fuente de datos» y Calcular | Error con código de seguimiento, filtros conservados y «Reintentar» | Error crítico y H-03 |
| 9 | Desmarcar y Reintentar | Vuelve el resultado | Recuperación |
| 10 | «Asistencia a citas» con «Septiembre 2026» | «Sin datos para este período» | RN-06 |
| 11 | Cambiar el rol a «Médico» | Solo aparece «Asistencia a citas» | RN-01, RN-08 |
| 12 | F12 → vista de dispositivo a 360 px y repetir los pasos 1 a 4 | Tarjetas apiladas y botones de 44 px | Semana 10 |
