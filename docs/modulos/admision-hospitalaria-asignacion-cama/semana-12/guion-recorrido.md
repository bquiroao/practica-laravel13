# Guion del recorrido navegable (segundo parcial)

Se usa el prototipo `semana-11/prototipo/index.html` en Chrome. Con F12 → modo dispositivo, se muestra también a 375 px. Duración aproximada: 4 minutos.

| # | Acción | Qué se demuestra | Qué decir |
|---|---|---|---|
| 1 | Escribir `PAC-451` y salir del campo con Tab | Validación temprana y error asociado | «El error aparece junto al campo y el lector de pantalla lo anuncia (B-02, B-03)» |
| 2 | Corregir a `PAC-00451` → Buscar camas | Estado de carga y lista | «Solo camas operativas, libres y compatibles (RN-03, RN-04)» |
| 3 | Elegir B-201 → Continuar | Confirmación | «Nada se registra sin confirmar (B-01)» |
| 4 | Confirmar | Sin doble envío y éxito | «El botón queda deshabilitado; resultado con código y cama» |
| 5 | Ver detalle con el rol Supervisor(a) | Trazabilidad | «Eventos ADMISION_CREADA y CAMA_ASIGNADA, solo lectura (RN-05)» |
| 6 | Marcar «Simular…», admitir PAC-00452 en B-201 | **Error crítico** | «Otra admisión ganó la cama; el aviso la nombra y ofrece B-202 (RN-01, CU-05)» |
| 7 | Desmarcar y confirmar con B-202 | Recuperación | «Se conservaron los datos; un solo paso para recuperarse» |
| 8 | Cambiar el rol a Médico | Roles | «No hay acción de admitir; solo consulta (RN-06)» |
| 9 | Vista a 375 px | Movilidad | «Tarjetas, botones a todo el ancho y barra fija solo en Confirmar (D-03 corregido)» |

**Plan B** si el proyector falla: mostrar las capturas `semana-11/capturas/` en el mismo orden.
