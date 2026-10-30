# Semana 12 – Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad

Módulo: Reportes operativos exportables · Grupo G1 · Autor: Joshua Eduardo García Reyes · Cubre las semanas 7 a 11.
Tema defendido: «filtrado y exportación reproducible de un reporte operativo».

## 1. Presentación breve (6 diapositivas, 8 minutos)

| # | Diapositiva | Mensaje en una frase | Evidencia que se muestra |
|---|---|---|---|
| 1 | Qué se defiende | Una jefatura filtra un reporte, lo exporta y puede demostrar después que el archivo no cambió. | `semana-11/prototipo/index.html` |
| 2 | Componentes | Cuatro capas; reportes y formatos son piezas que se conectan al exportador sin modificarlo. | `micro-his-reportes/src/Dominio/Reportes.php` |
| 3 | UX por rol | Jefatura ve datos personales; auditor los ve como `***`; el filtro inválido marca el campo y nada se genera. | `semana-11/prototipo/index.html` |
| 4 | Accesibilidad | Etiqueta por campo, `aria-live`, `role="alert"`, foco al mensaje, tabla con desplazamiento propio. | `semana-11/verificar-prototipo.php` |
| 5 | Móvil | Se usa a 390 px sin scroll horizontal de página; botones de 44 px. | `semana-11/evidencia/movil-camino-feliz.png` |
| 6 | Qué falta y el cambio pedido | La vista previa y el catálogo no tienen ruta en la API; el reporte de ocupación y el formato JSON se agregan sin editar código existente. | `semana-12/demostrar-cambio.php` |

## 2. Matriz decisión → RF/RNF → evidencia

Las rutas son relativas a `docs/modulos/reportes-operativos-exportables/`; `semana-12/demostrar-cambio.php` comprueba que existen (las de la semana 11, que se entrega en otro PR, pueden aparecer como «aún sin fusionar»).

| Decisión de diseño | RF / RNF | Evidencia |
|---|---|---|
| El catálogo fija la vista y las columnas; el usuario no elige tablas | RF-01, RNF-05 | `micro-his-reportes/src/Dominio/Reportes.php` (función `catalogo`), `semana-11/prototipo/index.html` |
| Rango obligatorio, desde ≤ hasta, máximo 92 días, sin futuro | RF-02, RF-10 | `micro-his-reportes/src/Dominio/Reportes.php` (`Filtros::desde`), prueba DOM-01 en `micro-his-reportes/tests/ejecutar.php` |
| Solo filtros opcionales que el reporte declara, con valores de su lista | RF-03, RF-10 | `micro-his-reportes/src/Dominio/Reportes.php`, prueba DOM-02 |
| La vista previa existe en el prototipo; la API **aún no** tiene esa ruta (se declara como límite) | RF-04 | `semana-11/prototipo/index.html` |
| El formato es una interfaz: CSV hoy y JSON sin tocar el exportador | RF-05, RNF-04 | `semana-02/semana-02-ocp-requerimientos-diseno.md`, `semana-02/ejemplo-ocp/verificar.php`, `semana-12/demostrar-cambio.php` |
| Corte de fecha fijado al exportar y orden estable de filas | RF-06, RNF-01 | `micro-his-reportes/src/Dominio/Reportes.php` (`Archivo::generar`), prueba APP-02 |
| Cada exportación se registra con filtros, corte, formato y SHA-256 antes de entregarse | RF-07 | `micro-his-reportes/src/Aplicacion/ExportarReporte.php`, prueba APP-01 |
| Reproducir una exportación y decir si el hash coincide | RF-08, RNF-01 | `micro-his-reportes/src/Aplicacion/ExportarReporte.php` (`reproducir`), `semana-11/verificar-prototipo.php` |
| Enmascarar paciente y documento sin permiso; solo datos del hospital del usuario | RF-09, RNF-02 | `micro-his-reportes/src/Persistencia/ReportesPdo.php`, prueba REP-01 |
| Las reglas comunes viven en un solo lugar (`Archivo`, `Filtros`) y no en el controlador | RNF-05 | `micro-his-reportes/tests/ejecutar.php` (prueba ARQ-01) |
| Todo se verifica con scripts que salen con 0 o 1; el límite de 30 s de la consulta **no está implementado** | RNF-06, RNF-03 | `semana-12/demostrar-cambio.php` |

## 3. Recorrido navegable (lo que se hace frente al docente)

1. Abrir `semana-11/prototipo/index.html`. Pantalla `catalogo`: «Elegir Censo por servicio».
2. En `filtros` escribir `desde` 2026-06-01: bloque rojo, campo marcado, nada generado (**error crítico**).
3. Corregir a 2026-10-01 y «Generar reporte»: 2 filas, huella SHA-256 y número de exportación (**camino feliz**). «Reproducir exportación»: coincide.
4. Cambiar el rol a auditor y generar de nuevo: paciente y documento aparecen como `***`.
5. Reducir la ventana a 390 px: sin scroll horizontal de página. Correr `php semana-12/demostrar-cambio.php` y mostrar las líneas `PASA`.

## 4. Registro del cambio solicitado

| Campo | Contenido |
|---|---|
| Cambio pedido (simulado en la defensa) | «Gerencia pide el reporte de ocupación de camas por servicio y poder bajarlo en JSON.» |
| Impacto en requisitos | Cubre RF-01 (catálogo) y RF-05 (formatos) sin cambiar RF-02, RF-06, RF-07, RF-08 ni RF-09. |
| Decisión | Una entrada nueva en el catálogo (`DefinicionReporte` con su vista, columnas y filtro) y una clase `Json` que implementa `FormatoExportacion`; se entregan al exportador al armarlo: `catalogo() + ['ocupacion' => …]` y `['csv' => new Csv(), 'json' => new Json()]`. |
| Qué se cambia | Una entrada de catálogo, una clase nueva y el cableado de quien arma `ExportarReporte`. |
| Qué **no** se cambia | `ExportarReporte`, `Archivo`, `Filtros`, `Csv`, `ReportesPdo`, el esquema de `exportaciones_reporte` y las pruebas existentes. |
| Evidencia | `semana-12/demostrar-cambio.php`: contrato de los dos formatos, 2 filas del hospital 1, filtro, enmascarado del auditor, reproducción coincidente tras una fila tardía, PDF y reportes inventados siguen rechazados. |
| Riesgo y límite | La vista `vw_ocupacion` pertenece al módulo de Traslados: hasta que la publique, el reporte no tiene datos reales. El JSON hereda el mismo enmascarado porque lo aplica `Archivo` antes del formato; un formato nuevo no puede saltárselo. |
| Conclusión | El diseño de la semana 2 permitió el cambio extendiendo, no modificando; el paso a código queda para el siguiente PR si el docente lo aprueba. |
