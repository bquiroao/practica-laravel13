# Semana 6 – Primera evaluación parcial: defensa de la arquitectura y cambio práctico

Módulo: Reportes operativos exportables (módulo 27, reasignado dentro del grupo G1) · Grupo G1 · Autor: Joshua Eduardo García Reyes · Cubre las semanas 1 a 5.
Tema defendido: «filtrado y exportación reproducible de un reporte operativo».

## 1. Presentación breve (7 diapositivas, 8 minutos)

Archivo: `semana-06/presentacion/semana-06-defensa-reportes-operativos.pptx` (y su PDF).

| # | Diapositiva | Mensaje en una frase | Evidencia que se muestra |
|---|---|---|---|
| 1 | Qué se defiende | Una jefatura filtra un reporte operativo y lo exporta. El archivo lleva una huella SHA-256 y se puede reproducir después para demostrar que no cambió. | pruebas APP-01 y APP-02 en `micro-his-reportes/tests/ejecutar.php` |
| 2 | Actores y casos de uso (semana 1) | Jefatura, administración y auditoría piden reportes; el sistema los genera y registra cada exportación. Los casos de uso están en el UML de la semana 1. | carpeta `semana-01/` |
| 3 | Requisitos y SOLID (semana 2) | RF y RNF con criterios de aceptación. El formato de salida cumple OCP: JSON o PDF entran como clase nueva, sin editar el exportador. | `semana-02/ejemplo-ocp/verificar.php` |
| 4 | Capas (semana 3) | Dominio, Aplicación, Persistencia y Presentación. Las dependencias van hacia adentro: el Dominio no conoce a nadie y la prueba ARQ-01 lo vigila. | prueba ARQ-01 en `micro-his-reportes/tests/ejecutar.php` |
| 5 | MVC y Repository (semana 4) | El caso de uso depende de `RepositorioExportaciones`, lo que escribe, y de `FuenteReportes`, lo que lee de otros módulos. PDO y memoria dan el mismo archivo y la misma huella (REP-01). | `micro-his-reportes/src/Aplicacion/Puertos.php` |
| 6 | API y microservicio (semana 5) | Contrato de la API de reportes y evaluación de la frontera de microservicio con criterios medibles antes de dividir nada. | carpeta `semana-05/` |
| 7 | Cambio práctico y límites | Guardar el registro de exportaciones en un archivo se resuelve con un adaptador nuevo. Límites: no hay vista previa en la API (RF-04) ni corte de 30 segundos (RNF-03). | `semana-06/demostrar-cambio.php` |

## 2. Matriz decisión → RF/RNF → evidencia

Todas las rutas son relativas a `docs/modulos/reportes-operativos-exportables/`; `semana-06/demostrar-cambio.php` comprueba que existen y que las pruebas citadas están en `tests/ejecutar.php` (las carpetas de las semanas 1 a 5 que no estén en la copia que se revisa se cuentan aparte).

| Decisión de diseño | RF / RNF | Evidencia |
|---|---|---|
| El catálogo fija la vista y las columnas; el usuario no elige tablas | RF-01, RNF-05 | `micro-his-reportes/src/Dominio/Reportes.php` (función `catalogo`) |
| Rango obligatorio, desde ≤ hasta, máximo 92 días y sin fechas futuras | RF-02, RF-10 | `micro-his-reportes/src/Dominio/Reportes.php` (`Filtros::desde`), prueba DOM-01 en `micro-his-reportes/tests/ejecutar.php` |
| Solo se aceptan los filtros opcionales que el reporte declara, con valores de su lista | RF-03, RF-10 | `micro-his-reportes/src/Dominio/Reportes.php`, prueba DOM-01 |
| El formato es una interfaz (OCP): CSV hoy y otro formato sin tocar el exportador | RF-05, RNF-04 | `semana-02/semana-02-ocp-requerimientos-diseno.md`, `semana-02/ejemplo-ocp/verificar.php` |
| El corte de fecha se fija al exportar y las filas salen en orden estable | RF-06, RNF-01 | `micro-his-reportes/src/Dominio/Reportes.php` (`Archivo::generar`), prueba APP-02 |
| Cada exportación se registra con filtros, corte, formato y SHA-256 antes de entregarse | RF-07 | `micro-his-reportes/src/Aplicacion/ExportarReporte.php`, prueba APP-01 |
| Reproducir una exportación y decir si el hash coincide | RF-08, RNF-01 | `micro-his-reportes/src/Aplicacion/ExportarReporte.php` (`reproducir`), prueba APP-02 |
| Sin permiso se enmascaran paciente y documento; solo se ven datos del hospital del usuario | RF-09, RNF-02 | `micro-his-reportes/src/Persistencia/ReportesPdo.php`, pruebas DOM-02 y REP-01 |
| Las reglas comunes viven en un solo lugar y no en el controlador | RNF-05 | prueba ARQ-01 en `micro-his-reportes/tests/ejecutar.php` |
| El caso de uso depende de interfaces: PDO y memoria dan el mismo archivo y el doble que falla da 500 sin entregar archivo | RNF-06, RNF-01 | `micro-his-reportes/src/Aplicacion/Puertos.php`, pruebas REP-01 y PER-02 |
| La vista previa (RF-04) y el límite de 30 segundos de la consulta (RNF-03) **no existen** y se declaran como límites | RF-04, RNF-03 | `micro-his-reportes/src/Presentacion/Enrutador.php` (solo las rutas que existen), prueba MVC-01 |

## 3. Recorrido (lo que se hace frente al docente)

1. Correr `php micro-his-reportes/tests/ejecutar.php` desde la carpeta del módulo: todas las líneas en `PASA`.
2. Mostrar `semana-02/ejemplo-ocp/verificar.php`: un formato nuevo no obliga a editar el exportador.
3. Mostrar `src/Aplicacion/Puertos.php`: lo que el módulo escribe y lo que solo lee.
4. Correr `php semana-06/demostrar-cambio.php` y mostrar las líneas `PASA` del cambio práctico.

## 4. Registro del cambio práctico

| Campo | Contenido |
|---|---|
| Cambio pedido (simulado en la defensa) | «Auditoría quiere el registro de exportaciones en un archivo que pueda entregarse al auditor.» |
| Impacto en requisitos | RF-07 y RF-08 no cambian: se sigue registrando antes de entregar y se puede reproducir; RNF-01 se mantiene porque la huella sale del mismo archivo. |
| Decisión | Un adaptador nuevo `ExportacionesEnArchivo` que implementa `RepositorioExportaciones` (una exportación por línea JSON) y se entrega a `ExportarReporte` desde `public/index.php`. |
| Qué se cambia | Una clase nueva y el cableado de quien arma `ExportarReporte`. |
| Qué **no** se cambia | `ExportarReporte`, `Archivo`, `Filtros`, `Csv`, el catálogo, el controlador, la vista, el esquema SQL y las pruebas existentes. |
| Evidencia | `semana-06/demostrar-cambio.php`: C-01 mismo archivo, huella y registro que con memoria, C-02 reproducir coincide desde una instancia nueva, C-03 archivo no escribible → `ErrorPersistencia` y no se entrega archivo, C-04 `src/` no menciona la clase nueva. |
| Riesgo y límite | Un archivo no garantiza orden ni unicidad si dos procesos escriben a la vez; sirve para auditoría puntual, no para reemplazar la tabla. Las vistas de datos fuente siguen en la base. |
| Conclusión | Depender de interfaces (semana 4) permitió el cambio extendiendo, no modificando; los pasos de código quedan para otro PR si el docente lo aprueba. |

## 5. Preguntas probables

- **¿Cómo se demuestra que un archivo no cambió?** Con el SHA-256 y el corte guardados: reproducir vuelve a generar el archivo y compara (APP-02).
- **¿Por qué un auditor ve `***`?** Porque sin permiso de datos personales el Dominio enmascara paciente y documento (DOM-02).
- **¿Qué falta?** La vista previa en la API (RF-04) y el límite de 30 segundos de la consulta (RNF-03).
